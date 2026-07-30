<?php

namespace App\Http\Controllers\Api\SAQ;

use App\Http\Controllers\Controller;
use App\Jobs\DispatchSaqScoringJob;
use App\Models\AssessmentSeries;
use App\Models\ProjectQuestion;
use App\Models\SAQ;
use App\Models\SAQTemplate;
use App\Models\SaqProject;
use App\Models\Supplier;
use App\Services\SAQ\ProjectQuestionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SaqProjectController extends Controller
{

    public function __construct(
        private readonly ProjectQuestionService $snapshotService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $status = $request->input('status');

        $query = SaqProject::with(['template', 'series'])->orderByDesc('created_at');
        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        $paginated = $query->paginate($request->input('per_page', 20), ['*'], 'page', $request->input('page', 1));

        $statusCounts = SaqProject::selectRaw('status, count(*) as cnt')->groupBy('status')->pluck('cnt', 'status');

        return response()->json([
            'success' => true,
            'data'    => $paginated->items(),
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
                'last_page'    => $paginated->lastPage(),
            ],
            'status_counts' => [
                'all'    => (int) $statusCounts->sum(),
                'draft'  => (int) ($statusCounts['draft'] ?? 0),
                'active' => (int) ($statusCounts['active'] ?? 0),
                'closed' => (int) ($statusCounts['closed'] ?? 0),
            ],
        ]);
    }

    public function show(SaqProject $project): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $project->load(['template', 'projectQuestions']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:120'],
            'series_id'   => ['required', 'uuid', 'exists:assessment_series,id'],
            'status'      => ['nullable', 'string'],
            'start_date'  => ['nullable', 'date'],
            'due_date'    => ['nullable', 'date'],
            'description' => ['nullable', 'string'],
        ]);

        $series = AssessmentSeries::findOrFail($validated['series_id']);

        abort_if(
            $series->status === 'archived',
            422,
            'Series 已封存，無法加入新專案'
        );

        abort_if(
            !$series->template_id,
            422,
            '此 Series 尚未綁定評核範本，請更新 Series 後再建立專案'
        );

        // 同系列同年度期間重疊檢查
        if ($validated['due_date'] ?? null) {
            $overlap = $this->findPeriodOverlap(
                $validated['series_id'],
                $validated['start_date'] ?? null,
                $validated['due_date'],
            );
            abort_if($overlap, 422, "同系列同年度已有期間重疊的專案：「{$overlap->name}」（{$overlap->start_date} ~ {$overlap->due_date}）");
        }

        $template = SAQTemplate::findOrFail($series->template_id);

        abort_if(
            $template->status === 'draft',
            422,
            'Draft 範本不可建立問卷專案，請先發佈範本。'
        );

        // 計算可比性：同系列下是否已有不同版本的 Project
        $existingVersions = SaqProject::where('series_id', $series->id)
            ->whereNotNull('template_version')
            ->distinct()
            ->pluck('template_version');
        $isComparable = $existingVersions->isEmpty()
            || ($existingVersions->count() === 1 && $existingVersions->first() === $template->version);

        $project = SaqProject::create(array_merge(array_intersect_key($validated, array_flip(['name', 'series_id', 'status', 'start_date', 'due_date', 'description'])), [
            'template_id'          => $template->id,
            'template_ref_id'      => $template->id,
            'template_ref_version' => (int) $template->version ?: 1,
            'template_version'     => $template->version,
            'is_comparable'        => $isComparable,
        ]));

        $this->snapshotService->snapshot($project, $template);

        return response()->json([
            'success' => true,
            'data'    => $project->load(['template', 'projectQuestions']),
        ], 201);
    }

    public function update(Request $request, SaqProject $project): JsonResponse
    {
        $validated = $request->validate([
            'name'        => ['sometimes', 'string', 'max:120'],
            'status'      => ['sometimes', 'string'],
            'start_date'  => ['sometimes', 'nullable', 'date'],
            'due_date'    => ['sometimes', 'nullable', 'date'],
            'description' => ['sometimes', 'nullable', 'string'],
        ]);

        // 若更新了日期，重新檢查同系列同年度期間重疊
        $newDue   = $validated['due_date']   ?? $project->due_date;
        $newStart = $validated['start_date'] ?? $project->start_date;
        if ($newDue) {
            $overlap = $this->findPeriodOverlap($project->series_id, $newStart, $newDue, $project->id);
            abort_if($overlap, 422, "同系列同年度已有期間重疊的專案：「{$overlap->name}」（{$overlap->start_date} ~ {$overlap->due_date}）");
        }

        $project->update($validated);

        return response()->json([
            'success' => true,
            'data'    => $project->fresh()->load('template'),
        ]);
    }

    public function destroy(SaqProject $project): JsonResponse
    {
        if ($project->status === 'active') {
            return response()->json([
                'success' => false,
                'message' => '進行中的專案無法刪除',
            ], 422);
        }

        $project->delete();

        return response()->json(['success' => true, 'message' => '專案已刪除']);
    }

    public function send(Request $request, SaqProject $project): JsonResponse
    {
        if ($project->status === 'closed') {
            return response()->json(['success' => false, 'message' => '已結案的專案不能再發送'], 422);
        }

        $supplierIds = $request->validate([
            'supplier_ids'   => ['required', 'array', 'min:1'],
            'supplier_ids.*' => ['uuid', 'exists:suppliers,id'],
        ])['supplier_ids'];

        $existing = $project->saqs()->pluck('supplier_id')->toArray();
        $created  = 0;
        $skipped  = 0;
        $saqs     = [];

        foreach ($supplierIds as $supplierId) {
            if (in_array($supplierId, $existing, true)) {
                $skipped++;
                continue;
            }

            $saq = SAQ::create([
                'project_id'  => $project->id,
                'supplier_id' => $supplierId,
                'template_id' => $project->template_id,
                'status'      => 'sent',
                'sent_at'     => Carbon::now(),
            ]);

            $saq->reviewHistories()->create([
                'action'   => 'send',
                'acted_by' => $request->user()->id,
                'comment'  => '問卷已發送',
            ]);

            $saqs[] = $saq;
            $created++;
        }

        if ($project->status === 'draft' && $created > 0) {
            $project->transitionStatus('active');
        }

        return response()->json([
            'success' => true,
            'created' => $created,
            'skipped' => $skipped,
            'data'    => $saqs,
        ]);
    }

    /**
     * 取得專案題目列表（含 weight）
     */
    public function questions(SaqProject $project): JsonResponse
    {
        $questions = ProjectQuestion::where('project_id', $project->id)
            ->orderBy('order')
            ->get(['id', 'order', 'question_text', 'question_type', 'weight', 'is_required', 'source_template_question_id']);

        return response()->json(['success' => true, 'data' => $questions]);
    }

    /**
     * 更新題目權重（正規化後存入），並對進行中 SAQ 重新計分
     */
    public function updateWeights(Request $request, SaqProject $project): JsonResponse
    {
        $validated = $request->validate([
            'weights'         => ['required', 'array', 'min:1'],
            'weights.*.id'    => ['required', 'uuid'],
            'weights.*.weight' => ['required', 'numeric', 'min:0'],
        ]);

        $rawMap = collect($validated['weights'])->pluck('weight', 'id')->toArray();

        try {
            $this->snapshotService->updateWeights($project, $rawMap);
        } catch (\DomainException|\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        // 對需要重新計分的 SAQ 派遣 Job
        $rescoringCount = 0;
        $project->saqs()
            ->whereIn('status', ['submitted', 'under_review', 'review_returned'])
            ->each(function (SAQ $saq) use (&$rescoringCount) {
                DispatchSaqScoringJob::dispatch($saq->id);
                $rescoringCount++;
            });

        return response()->json([
            'success'          => true,
            'message'          => '題目權重已更新' . ($rescoringCount > 0 ? "，已觸發 {$rescoringCount} 份問卷重新計分" : ''),
            'rescoring_count'  => $rescoringCount,
            'data'             => ProjectQuestion::where('project_id', $project->id)
                ->orderBy('order')
                ->get(['id', 'order', 'question_text', 'weight']),
        ]);
    }

    /**
     * 檢查同系列同年度是否有期間重疊的專案。
     * 重疊定義：A.start <= B.due AND A.due >= B.start
     * start_date 若為 null，以 due_date 所在年份 1/1 計。
     */
    private function findPeriodOverlap(
        string  $seriesId,
        ?string $startDate,
        string  $dueDate,
        ?string $excludeId = null,
    ): ?SaqProject {
        $year      = Carbon::parse($dueDate)->year;
        $rangeStart = $startDate ? Carbon::parse($startDate) : Carbon::create($year, 1, 1);
        $rangeEnd   = Carbon::parse($dueDate);

        return SaqProject::where('series_id', $seriesId)
            ->whereYear('due_date', $year)
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
            ->where(function ($q) use ($rangeStart, $rangeEnd) {
                $q->whereRaw(
                    'COALESCE(start_date, DATE_FORMAT(due_date, "%Y-01-01")) <= ? AND due_date >= ?',
                    [$rangeEnd->toDateString(), $rangeStart->toDateString()]
                );
            })
            ->first(['id', 'name', 'start_date', 'due_date']);
    }

    public function close(SaqProject $project): JsonResponse
    {
        $project->transitionStatus('closed');

        return response()->json([
            'success' => true,
            'data'    => $project->fresh()->load('template'),
            'message' => '專案已結案',
        ]);
    }
}
