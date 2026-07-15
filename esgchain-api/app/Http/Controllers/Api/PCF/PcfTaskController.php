<?php

namespace App\Http\Controllers\Api\PCF;

use App\Http\Controllers\Controller;
use App\Models\PcfTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PcfTaskController extends Controller
{
    private string $aiUrl;

    public function __construct()
    {
        $this->aiUrl = config('services.ai.url', env('AI_SERVICE_URL', 'http://esgchain-ai:8000'));
    }

    /**
     * POST /pcf/calculate — 觸發非同步 PCF 批量計算
     * 立即回傳 202 Accepted + task_id
     */
    public function calculate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'supplier_ids'   => ['required', 'array', 'min:1'],
            'supplier_ids.*' => ['uuid', 'exists:suppliers,id'],
            'product_ids'    => ['nullable', 'array'],
            'product_ids.*'  => ['uuid'],
        ]);

        $task = PcfTask::create([
            'supplier_ids' => $validated['supplier_ids'],
            'product_ids'  => $validated['product_ids'] ?? [],
            'status'       => 'pending',
            'created_by'   => $request->user()->id,
        ]);

        // 觸發 FastAPI Celery 任務
        try {
            $response = Http::timeout(10)->post("{$this->aiUrl}/ai/v1/pcf/batch", [
                'task_id'      => $task->id,
                'supplier_ids' => $task->supplier_ids,
                'product_ids'  => $task->product_ids,
                'callback_url' => url("/api/v1/pcf/tasks/{$task->id}/callback"),
            ]);

            if ($response->successful()) {
                $task->update([
                    'celery_task_id' => $response->json('celery_task_id'),
                    'status'         => 'running',
                ]);
            }
        } catch (\Throwable) {
            // FastAPI 暫時不可用，任務維持 pending 狀態，可重試
        }

        return response()->json([
            'success' => true,
            'data'    => ['task_id' => $task->id],
            'message' => 'PCF 計算任務已建立',
        ], 202);
    }

    /**
     * GET /pcf/status/{task_id} — 查詢任務狀態
     */
    public function status(string $taskId): JsonResponse
    {
        $task = PcfTask::findOrFail($taskId);

        // 若有 celery_task_id，嘗試從 FastAPI 同步最新狀態
        if ($task->celery_task_id && in_array($task->status, ['pending', 'running'])) {
            try {
                $response = Http::timeout(5)
                    ->get("{$this->aiUrl}/ai/v1/pcf/task-status/{$task->celery_task_id}");

                if ($response->successful()) {
                    $remoteStatus = $response->json();
                    $task->update([
                        'status'       => $remoteStatus['status'] ?? $task->status,
                        'progress'     => $remoteStatus['progress'] ?? $task->progress,
                        'result_count' => $remoteStatus['result_count'] ?? $task->result_count,
                        'error_message' => $remoteStatus['error_message'] ?? null,
                    ]);
                    $task->refresh();
                }
            } catch (\Throwable) {
                // 同步失敗不影響回應
            }
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'task_id'       => $task->id,
                'status'        => $task->status,
                'progress'      => $task->progress,
                'result_count'  => $task->result_count,
                'error_message' => $task->error_message,
            ],
            'message' => '',
        ]);
    }

    /**
     * GET /pcf/results/{id} — 取得計算結果
     */
    public function results(string $taskId): JsonResponse
    {
        $task = PcfTask::findOrFail($taskId);

        if ($task->status !== 'completed') {
            return response()->json([
                'success' => false,
                'error_code' => 'BUSINESS_ERROR',
                'message' => '任務尚未完成，status: ' . $task->status,
            ], 400);
        }

        // 從 FastAPI 取回計算結果
        try {
            $response = Http::timeout(30)
                ->get("{$this->aiUrl}/ai/v1/pcf/task-results/{$task->id}");

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'data'    => $response->json('data'),
                    'message' => '',
                ]);
            }
        } catch (\Throwable) {
            // Fall through to error response
        }

        return response()->json([
            'success' => false,
            'error_code' => 'SERVER_ERROR',
            'message' => '無法取得計算結果',
        ], 500);
    }

    /**
     * POST /pcf/tasks/{id}/callback — FastAPI 計算完成後回呼
     */
    public function callback(Request $request, string $taskId): JsonResponse
    {
        $task = PcfTask::findOrFail($taskId);

        $task->update([
            'status'       => $request->input('status', 'completed'),
            'progress'     => 100,
            'result_count' => $request->input('result_count'),
            'error_message' => $request->input('error_message'),
        ]);

        return response()->json(['success' => true, 'message' => '狀態已更新']);
    }
}
