<?php

namespace App\Http\Controllers\Api\CAP;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Jobs\NotifyCapAssignedJob;
use App\Models\CAP;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CAPController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $paginated = CAP::with(['supplier', 'findings', 'complianceDoc'])
            ->withCount('attachments')
            ->when($request->supplier_id, fn($q, $v) => $q->where('supplier_id', $v))
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->orderBy('due_date')
            ->paginate(20);

        // 批次附加 topic_label_zh
        $slugs = $paginated->getCollection()
            ->flatMap(fn($c) => $c->findings->pluck('topic_slug'))
            ->filter()->unique()->values()->all();
        $labelMap = $slugs
            ? DB::table('question_tags')->whereIn('slug', $slugs)->pluck('label_zh', 'slug')->all()
            : [];

        $paginated->getCollection()->each(function ($cap) use ($labelMap) {
            $cap->findings->transform(function ($f) use ($labelMap) {
                $f->topic_label_zh = $f->topic_slug ? ($labelMap[$f->topic_slug] ?? null) : null;
                return $f;
            });
        });

        return response()->json([
            'success' => true,
            'data' => $paginated->items(),
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
            ],
            'message' => '',
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'supplier_id'               => ['required', 'uuid', 'exists:suppliers,id'],
            'saq_id'                    => ['nullable', 'uuid', 'exists:saqs,id'],
            'source_type'               => ['nullable', 'in:saq,compliance_doc,manual,risk_assessment'],
            'source_id'                 => ['nullable', 'uuid'],
            'triggered_by_axis'         => ['nullable', 'in:axis1,axis2,axis3,dim_e1,dim_e2,dim_e3,dim_e4,dim_e5,dim_e6'],
            'auto_generated'            => ['nullable', 'boolean'],
            'title'                     => ['required', 'string', 'max:255'],
            'description'               => ['nullable', 'string'],
            'priority'                  => ['nullable', 'in:low,medium,high,critical'],
            'due_date'                  => ['nullable', 'date'],
            'assigned_to'               => ['nullable', 'uuid'],
            'findings'                  => ['nullable', 'array'],
            'findings.*.category'       => ['nullable', 'in:E,S,G'],
            'findings.*.framework'      => ['nullable', 'string', 'max:20'],
            'findings.*.topic_slug'     => ['nullable', 'string', 'max:80'],
            'findings.*.source_score'   => ['nullable', 'numeric'],
            'findings.*.threshold'      => ['nullable', 'numeric'],
            'findings.*.finding'        => ['required_with:findings', 'string'],
        ]);

        $cap = CAP::create(array_merge(
            $validated,
            ['created_by' => $request->user()->id]
        ));

        if (!empty($validated['findings'])) {
            foreach ($validated['findings'] as $f) {
                $cap->findings()->create($f);
            }
        }

        NotifyCapAssignedJob::dispatch($cap->id);

        return response()->json([
            'success' => true,
            'data' => $cap->load('findings'),
            'message' => 'CAP 建立成功',
        ], 201);
    }

    public function show(CAP $cap): JsonResponse
    {
        $cap->load(['supplier', 'saq', 'findings', 'complianceDoc', 'updates.changedBy', 'updates.attachments', 'attachments.uploadedBy']);

        // 為 ISO finding 附加 topic_label_zh
        $slugs = $cap->findings->pluck('topic_slug')->filter()->unique()->values()->all();
        $labelMap = [];
        if (!empty($slugs)) {
            $labelMap = \DB::table('question_tags')
                ->whereIn('slug', $slugs)
                ->pluck('label_zh', 'slug')
                ->all();
        }

        $cap->findings->transform(function ($finding) use ($labelMap) {
            $finding->topic_label_zh = $finding->topic_slug
                ? ($labelMap[$finding->topic_slug] ?? null)
                : null;
            return $finding;
        });

        return response()->json([
            'success' => true,
            'data'    => $cap,
            'message' => '',
        ]);
    }

    public function update(Request $request, CAP $cap): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', 'in:open,in_progress,completed,overdue,closed'],
            'priority' => ['sometimes', 'in:low,medium,high,critical'],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'assigned_to' => ['sometimes', 'nullable', 'uuid'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $notes = $validated['notes'] ?? null;
        unset($validated['notes']);

        $cap->update($validated);

        // 狀態變更或填了備註時，落地成一筆歷程紀錄（稽核軌跡），而非直接丟棄
        if (array_key_exists('status', $validated) || $notes) {
            $cap->updates()->create([
                'status'     => $validated['status'] ?? $cap->status,
                'notes'      => $notes,
                'changed_by' => $request->user()->id,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $cap->fresh(['findings', 'updates.changedBy', 'updates.attachments']),
            'message' => 'CAP 已更新',
        ]);
    }

    public function destroy(CAP $cap): JsonResponse
    {
        $cap->delete();

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'CAP 已刪除',
        ]);
    }
}
