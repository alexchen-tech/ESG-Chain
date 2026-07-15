<?php

namespace App\Services\SAQ;

use App\Models\AssessmentSeries;
use App\Models\AssessmentSeriesWeight;
use App\Models\ProjectQuestion;
use App\Models\SAQ;
use App\Models\SAQTemplate;
use App\Models\SaqProject;
use App\Models\SaqScoreSnapshot;
use App\Models\QuestionTag;
use App\Services\Settings\SystemSettingsService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AssessmentSeriesService
{
    public function list(): Collection
    {
        return AssessmentSeries::withCount('projects')
            ->with(['projects' => fn($q) => $q->latest()->limit(1), 'template'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function (AssessmentSeries $s) {
                $s->latest_project_date = $s->projects->first()?->created_at?->toDateString();
                unset($s->projects);
                return $s;
            });
    }

    public function create(array $data, string $userId): AssessmentSeries
    {
        $template   = SAQTemplate::findOrFail($data['template_id']);
        $dimWeights = app(SystemSettingsService::class)->getDimWeightDefaults();

        return AssessmentSeries::create([
            'name'                         => $data['name'],
            'description'                  => $data['description'] ?? null,
            'template_id'                  => $template->id,
            'template_version_at_creation' => $template->version,
            'status'                       => 'active',
            'created_by_id'                => $userId,
            'dim_weights'                  => $dimWeights,
            'dim_weights_source'           => 'default',
        ]);
    }

    public function show(AssessmentSeries $series): array
    {
        $series->loadCount('projects');
        $series->load('template');

        $versions = $series->projects()->distinct()->pluck('template_version')->filter()->values();
        $hasMixed = $versions->count() > 1;

        return array_merge($series->toArray(), [
            'has_mixed_versions'       => $hasMixed,
            'comparable_versions_count' => $versions->count(),
        ]);
    }

    public function update(AssessmentSeries $series, array $data): AssessmentSeries
    {
        $series->update(array_filter([
            'name'        => $data['name'] ?? null,
            'description' => array_key_exists('description', $data) ? $data['description'] : $series->description,
        ], fn($v) => $v !== null));

        return $series->fresh();
    }

    public function archive(AssessmentSeries $series): AssessmentSeries
    {
        $series->update(['status' => 'archived']);
        return $series->fresh();
    }

    public function getWeights(AssessmentSeries $series): Collection
    {
        return $series->weights()->get();
    }

    public function setWeights(AssessmentSeries $series, array $weights): Collection
    {
        foreach ($weights as $item) {
            AssessmentSeriesWeight::updateOrCreate(
                [
                    'series_id'                    => $series->id,
                    'source_template_question_id'  => $item['source_template_question_id'],
                ],
                ['weight' => $item['weight']]
            );
        }

        // 刪除不在新列表中的舊 weight
        $newIds = collect($weights)->pluck('source_template_question_id');
        $series->weights()->whereNotIn('source_template_question_id', $newIds)->delete();

        return $series->weights()->get();
    }

    public function getProjects(AssessmentSeries $series): Collection
    {
        return $series->projects()
            ->withCount('saqs')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function (SaqProject $p) {
                $saqs = $p->saqs ?? SAQ::where('project_id', $p->id)->get();
                $p->avg_score   = $saqs->avg('score');
                $p->saqs_count  = $saqs->count();
                return $p;
            });
    }

    public function getComparison(AssessmentSeries $series, array $supplierIds): array
    {
        $projects = $series->projects()->orderBy('created_at')->get();

        // 預載供應商名稱
        $supplierNames = \App\Models\Supplier::whereIn('id', $supplierIds)
            ->pluck('name', 'id');

        $suppliers = [];
        foreach ($supplierIds as $supplierId) {
            $scoresByProject    = [];
            $questionTrendsMap  = [];

            foreach ($projects as $project) {
                $saq = SAQ::where('project_id', $project->id)
                    ->where('supplier_id', $supplierId)
                    ->first();

                $scoresByProject[$project->id] = $saq ? [
                    'total_score' => $saq->score,
                    'grade'       => $saq->grade,
                ] : null;

                if ($saq) {
                    $responses = $saq->responses()
                        ->whereNotNull('project_question_id')
                        ->get()
                        ->keyBy('project_question_id');

                    $projectQuestions = ProjectQuestion::where('project_id', $project->id)
                        ->orderBy('order')
                        ->get();

                    foreach ($projectQuestions as $pq) {
                        // 優先用 source_template_question_id 做跨 project key；無則 fallback 到 order
                        $key = $pq->source_template_question_id ?? ('order:' . $pq->order);
                        if (!isset($questionTrendsMap[$key])) {
                            $questionTrendsMap[$key] = [
                                'source_template_question_id' => $key,
                                'question_text'               => $pq->question_text,
                                'scores'                      => [],
                            ];
                        }
                        $resp = $responses[$pq->id] ?? null;
                        $questionTrendsMap[$key]['scores'][$project->id] = $resp?->raw_score;
                    }
                }
            }

            $suppliers[] = [
                'supplier_id'       => $supplierId,
                'supplier_name'     => $supplierNames[$supplierId] ?? null,
                'scores_by_project' => $scoresByProject,
                'question_trends'   => array_values($questionTrendsMap),
            ];
        }

        // 取每個 project 下所有 SAQ 最新 snapshot 的 scoring_model_id
        $projectModelMap = [];
        foreach ($projects as $project) {
            $saqIds = SAQ::where('project_id', $project->id)->pluck('id');
            $snap   = SaqScoreSnapshot::whereIn('saq_id', $saqIds)
                ->orderByDesc('scored_at')
                ->first();
            $projectModelMap[$project->id] = $snap?->scoring_model_id;
        }

        // 判斷相鄰 project scoring_model 是否一致
        $projectList    = $projects->values();
        $inconsistent   = false;
        for ($i = 1; $i < $projectList->count(); $i++) {
            $prevModel = $projectModelMap[$projectList[$i - 1]->id] ?? null;
            $currModel = $projectModelMap[$projectList[$i]->id]     ?? null;
            if ($prevModel !== null && $currModel !== null && $prevModel !== $currModel) {
                $inconsistent = true;
                break;
            }
        }

        return [
            'series_id'                  => $series->id,
            'scoring_model_inconsistent' => $inconsistent,
            'projects'                   => $projects->map(fn($p) => [
                'id'               => $p->id,
                'name'             => $p->name,
                'created_at'       => $p->created_at,
                'scoring_model_id' => $projectModelMap[$p->id] ?? null,
            ])->toArray(),
            'suppliers' => $suppliers,
        ];
    }

    // ── 框架 → 可用 pillar slug prefix 清單 ─────────────────────────────────

    private const FRAMEWORK_PILLARS = [
        'ESG' => [
            'esg.env'      => '環境管理',
            'esg.sc_env'   => '供應鏈環境',
            'esg.labor'    => '勞工人權',
            'esg.ohs'      => '職場安全',
            'esg.comm'     => '社區與消費者',
            'esg.gov'      => '公司治理',
        ],
        'ISO20400' => [
            'iso20400.policy' => '採購政策',
            'iso20400.perf'   => '績效評估',
            'iso20400.risk'   => '風險管理',
        ],
        'ISO26000' => [
            'iso26k.gov'      => '組織治理',
            'iso26k.hr'       => '人權',
            'iso26k.labor'    => '勞工',
            'iso26k.env'      => '環境',
            'iso26k.fair'     => '公平營運',
            'iso26k.consumer' => '消費者',
            'iso26k.comm'     => '社區',
        ],
        'Geo-Risk' => [
            'geo_risk.geopo'      => '地緣政治風險',
            'geo_risk.logistics'  => '物流/天災',
        ],
    ];

    public function getScoringConfig(string $id): array
    {
        $series = AssessmentSeries::with('template')->findOrFail($id);
        $framework = $series->template?->scoring_framework;

        // 從 question_tags L2 節點取得可用 pillar 列表（key = UUID）
        $l2Nodes = $framework
            ? QuestionTag::l2()->active()->where('l1_domain', $framework)
                ->orderBy('sort_order')
                ->get(['id', 'slug', 'label_zh', 'default_weight'])
            : collect();

        $pillarWeights = $series->pillar_weights;
        $isDefault = false;

        if ($pillarWeights === null && $l2Nodes->isNotEmpty()) {
            // 預設加權：key = L2 節點 UUID，value = default_weight
            $pillarWeights = $l2Nodes->pluck('default_weight', 'id')->toArray();
            $isDefault = true;
        }

        return [
            'pillar_weights'            => $pillarWeights,
            'pillar_weights_is_default' => $isDefault,
            'grade_thresholds'          => $series->grade_thresholds,
            'available_pillars'         => $l2Nodes->map(fn($n) => [
                'id'             => $n->id,
                'slug'           => $n->slug,
                'label'          => $n->label_zh,
                'default_weight' => $n->default_weight,
            ])->values()->toArray(),
            'dim_weights'                 => $series->dim_weights,
            'dim_weights_source'          => $series->dim_weights_source ?? 'default',
            'e4_objective_ratio'          => $series->e4_objective_ratio,
            'e4_objective_ratio_effective' => $series->e4_objective_ratio ?? 0.40,
        ];
    }

    public function updateScoringConfig(string $id, array $data): AssessmentSeries
    {
        $series = AssessmentSeries::findOrFail($id);

        if (isset($data['pillar_weights'])) {
            $sum = array_sum($data['pillar_weights']);
            if ($sum < 0.99 || $sum > 1.01) {
                throw new \InvalidArgumentException('pillar_weights 合計須等於 1.0（允許 ±0.01 容差）');
            }
            $validIds = QuestionTag::l2()->active()->pluck('id')->toArray();
            $unknownKeys = array_diff(array_keys($data['pillar_weights']), $validIds);
            if (!empty($unknownKeys)) {
                throw new \InvalidArgumentException('pillar_weights 包含無效的 L2 節點 ID：' . implode(', ', $unknownKeys));
            }
        }

        if (isset($data['dim_weights'])) {
            $dw = $data['dim_weights'];
            $requiredKeys = ['E1','E2','E3','E4','E5','E6'];
            $missingKeys = array_diff($requiredKeys, array_keys($dw));
            if (!empty($missingKeys)) {
                throw new \InvalidArgumentException('dim_weights 缺少維度：' . implode(', ', $missingKeys));
            }
            $sum = array_sum(array_map(fn($k) => $dw[$k] ?? 0, $requiredKeys));
            if ($sum < 0.99 || $sum > 1.01) {
                throw new \InvalidArgumentException('dim_weights 合計須等於 1.0（允許 ±0.01 容差）');
            }
        }

        if (isset($data['grade_thresholds'])) {
            $t = $data['grade_thresholds'];
            $a = $t['A'] ?? null;
            $b = $t['B'] ?? null;
            $c = $t['C'] ?? null;
            $d = $t['D'] ?? null;
            if ($a === null || $b === null || $c === null || $d === null
                || !($a > $b && $b > $c && $c > $d && $d > 0)) {
                throw new \InvalidArgumentException('閾值須遞減且 D > 0（A > B > C > D > 0）');
            }
        }

        $updateData = [
            'pillar_weights'   => $data['pillar_weights'] ?? $series->pillar_weights,
            'grade_thresholds' => $data['grade_thresholds'] ?? $series->grade_thresholds,
        ];

        if (isset($data['dim_weights'])) {
            $updateData['dim_weights']        = $data['dim_weights'];
            $updateData['dim_weights_source'] = 'custom';
        }
        if (isset($data['e4_objective_ratio'])) {
            $updateData['e4_objective_ratio'] = $data['e4_objective_ratio'];
        }

        $series->update($updateData);

        return $series;
    }
}
