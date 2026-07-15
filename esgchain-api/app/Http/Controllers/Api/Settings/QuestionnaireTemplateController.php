<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Models\SAQQuestion;
use App\Models\SAQTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuestionnaireTemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = SAQTemplate::query()->withCount('questions');

        if ($request->boolean('is_archived', false)) {
            $query->whereNotNull('archived_at');
        } else {
            // 排除 draft（draft 只在詳情頁操作）
            $query->whereNull('archived_at')->where('status', '!=', 'draft');
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }
        }

        if ($request->sasb_industry_id) {
            $query->where('sasb_industry_id', $request->sasb_industry_id);
        }

        $paginated = $query->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $paginated->items(),
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
                'last_page'    => $paginated->lastPage(),
            ],
            'message' => '',
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'              => ['required', 'string', 'max:255'],
            'version'           => ['nullable', 'integer'],
            'description'       => ['nullable', 'string'],
            'scoring_framework' => ['nullable', 'string', 'in:ESG,ISO20400,ISO26000,Geo-Risk,Product-Compliance,multi-framework'],
            'sasb_industry_id'  => ['nullable', 'uuid', 'exists:sasb_industries,id'],
        ]);

        $template = SAQTemplate::create(array_merge($validated, [
            'version'       => $validated['version'] ?? 1,
            'status'        => 'published',
            'is_active'     => false,
            'created_by_id' => $request->user()->id,
        ]));

        return response()->json(['success' => true, 'data' => $template, 'message' => '範本已建立'], 201);
    }

    public function show(SAQTemplate $template): JsonResponse
    {
        $draft = SAQTemplate::where('draft_of', $template->id)->first();

        $seriesCount = \App\Models\AssessmentSeries::where('template_id', $template->id)->count();
        $series      = \App\Models\AssessmentSeries::where('template_id', $template->id)
            ->get(['id', 'name', 'status']);

        $data = $template->load('questions');
        $data->has_draft    = !is_null($draft);
        $data->draft_id     = $draft?->id;
        $data->series_count = $seriesCount;
        $data->series       = $series;

        return response()->json(['success' => true, 'data' => $data, 'message' => '']);
    }

    public function update(Request $request, SAQTemplate $template): JsonResponse
    {
        $validated = $request->validate([
            'name'             => ['sometimes', 'string', 'max:255'],
            'description'      => ['sometimes', 'nullable', 'string'],
            'sasb_industry_id' => ['sometimes', 'nullable', 'uuid', 'exists:sasb_industries,id'],
            'is_active'        => ['sometimes', 'boolean'],
            'scoring_framework' => ['sometimes', 'nullable', 'string', 'in:ESG,ISO20400,ISO26000,Geo-Risk,Product-Compliance,multi-framework'],
        ]);

        // scoring_framework 建立後不可修改
        if (isset($validated['scoring_framework']) && $validated['scoring_framework'] !== $template->scoring_framework) {
            return response()->json([
                'success' => false,
                'message' => '範本框架（scoring_framework）建立後不可修改',
            ], 422);
        }

        $target = $this->ensureDraft($template, $request->user()->id);
        $target->update(array_diff_key($validated, ['scoring_framework' => null]));

        return response()->json([
            'success' => true,
            'data'    => array_merge($target->fresh()->toArray(), ['is_draft' => true]),
            'message' => '已儲存至草稿',
        ]);
    }

    public function destroy(SAQTemplate $template): JsonResponse
    {
        $seriesCount = \App\Models\AssessmentSeries::where('template_id', $template->id)->count();
        if ($seriesCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "此範本已被 {$seriesCount} 個評核系列使用，無法刪除",
            ], 422);
        }

        $template->delete();

        return response()->json(['success' => true, 'data' => null, 'message' => '範本已刪除']);
    }

    public function publish(SAQTemplate $template): JsonResponse
    {
        // $template 必須是 published 版，找其 draft
        $draft = SAQTemplate::where('draft_of', $template->id)->first();

        if (!$draft) {
            return response()->json(['success' => false, 'message' => '此範本沒有草稿可發佈'], 422);
        }

        // multi-framework 範本驗證：需含 iso26k.* 與 iso20400.* slug 各至少一題
        if ($draft->scoring_framework === 'multi-framework') {
            $slugs = \App\Models\QuestionTagAssignment::whereIn(
                'question_id', $draft->questions()->pluck('id')
            )->join('question_tags', 'question_tag_assignments.tag_id', '=', 'question_tags.id')
             ->pluck('question_tags.slug');

            $hasIso26k   = $slugs->some(fn ($s) => str_starts_with($s, 'iso26k.'));
            $hasIso20400 = $slugs->some(fn ($s) => str_starts_with($s, 'iso20400.'));

            if (!$hasIso26k || !$hasIso20400) {
                return response()->json([
                    'success' => false,
                    'message' => 'multi-framework 範本必須包含至少一題 iso26k.* slug 與一題 iso20400.* slug',
                ], 422);
            }
        }

        $newVersion = (int) $template->version + 1;

        // draft 升版為 published
        $draft->update([
            'version'   => $newVersion,
            'status'    => 'published',
            'draft_of'  => null,
            'is_active' => $template->is_active,
        ]);

        // 舊版封存
        $template->update([
            'status'      => 'archived',
            'archived_at' => now(),
            'is_active'   => false,
        ]);

        return response()->json([
            'success' => true,
            'data'    => $draft->fresh()->load('questions'),
            'message' => "範本已發佈為 v{$newVersion}，舊版已封存",
        ]);
    }

    public function clone(Request $request, SAQTemplate $template): JsonResponse
    {
        $newTemplate = SAQTemplate::create([
            'name'             => $template->name . ' (複製)',
            'version'          => 1,
            'status'           => 'published',
            'description'      => $template->description,
            'sasb_industry_id' => $template->sasb_industry_id,
            'is_active'        => false,
            'created_by_id'    => $request->user()->id,
        ]);

        $template->questions()->with('tagAssignments')->each(function (SAQQuestion $q) use ($newTemplate) {
            $newQuestion = SAQQuestion::create([
                'template_id'             => $newTemplate->id,
                'question_text'           => $q->question_text,
                'question_type'           => $q->question_type,
                'weight'                  => $q->weight,
                'is_required'             => $q->is_required,
                'order'                   => $q->order,
                'options'                 => $q->options,
                'sasb_topic_id'           => $q->sasb_topic_id,
                'sasb_metric_code'        => $q->sasb_metric_code,
                'tags'                    => $q->tags,
                'source_bank_question_id' => $q->source_bank_question_id,
                'framework_pillar'        => $q->framework_pillar,
            ]);

            foreach ($q->tagAssignments as $assignment) {
                $newQuestion->tagAssignments()->create(['tag_id' => $assignment->tag_id]);
            }
        });

        return response()->json([
            'success' => true,
            'data'    => $newTemplate->loadCount('questions'),
            'message' => '範本已複製',
        ], 201);
    }

    public function archive(SAQTemplate $template): JsonResponse
    {
        $template->update([
            'archived_at' => now(),
            'status'      => 'archived',
            'is_active'   => false,
        ]);

        return response()->json(['success' => true, 'data' => $template->fresh(), 'message' => '範本已封存']);
    }

    public function unarchive(SAQTemplate $template): JsonResponse
    {
        $template->update(['archived_at' => null, 'status' => 'published']);

        return response()->json(['success' => true, 'data' => $template->fresh(), 'message' => '已取消封存']);
    }

    public function reorder(Request $request, SAQTemplate $template): JsonResponse
    {
        $ids = $request->validate([
            'question_ids'   => ['required', 'array'],
            'question_ids.*' => ['uuid'],
        ])['question_ids'];

        // 排序在 draft 上操作
        $target = $this->ensureDraft($template, $request->user()->id);

        $existing = $target->questions()->pluck('id')->toArray();
        $invalid = array_diff($ids, $existing);
        if ($invalid) {
            return response()->json(['success' => false, 'message' => '包含不屬於此範本的題目 ID'], 422);
        }

        $cases = collect($ids)->map(fn($id, $i) => "WHEN '$id' THEN " . ($i + 1))->implode(' ');
        $target->questions()->whereIn('id', $ids)->update([
            'order' => \Illuminate\Support\Facades\DB::raw("CASE id $cases END"),
        ]);

        return response()->json([
            'success' => true,
            'data'    => $target->questions()->orderBy('order')->get(),
            'message' => '排序已更新',
        ]);
    }

    // 確保 published 版有對應 draft；若無則複製一份，回傳 draft
    private function ensureDraft(SAQTemplate $template, string $userId): SAQTemplate
    {
        // 若傳入的本身就是 draft，直接返回
        if ($template->status === 'draft') {
            return $template;
        }

        $draft = SAQTemplate::where('draft_of', $template->id)->first();

        if ($draft) {
            return $draft;
        }

        // 複製 published → draft
        $draft = SAQTemplate::create([
            'name'             => $template->name,
            'version'          => $template->version,
            'status'           => 'draft',
            'draft_of'         => $template->id,
            'description'      => $template->description,
            'sasb_industry_id' => $template->sasb_industry_id,
            'is_active'        => false,
            'created_by_id'    => $userId,
        ]);

        // 複製題目
        $template->questions()->with('tagAssignments')->each(function (SAQQuestion $q) use ($draft) {
            $newQ = SAQQuestion::create([
                'template_id'             => $draft->id,
                'question_text'           => $q->question_text,
                'question_type'           => $q->question_type,
                'weight'                  => $q->weight,
                'is_required'             => $q->is_required,
                'order'                   => $q->order,
                'options'                 => $q->options,
                'sasb_topic_id'           => $q->sasb_topic_id,
                'sasb_metric_code'        => $q->sasb_metric_code,
                'tags'                    => $q->tags,
                'source_bank_question_id' => $q->source_bank_question_id,
            ]);
            foreach ($q->tagAssignments as $a) {
                $newQ->tagAssignments()->create(['tag_id' => $a->tag_id]);
            }
        });

        return $draft;
    }
}
