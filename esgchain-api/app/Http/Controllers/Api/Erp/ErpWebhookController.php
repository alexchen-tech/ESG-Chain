<?php

namespace App\Http\Controllers\Api\Erp;

use App\Http\Controllers\Controller;
use App\Services\Erp\ErpSyncService;
use App\Services\ProductionBatch\ProductionBatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ErpWebhookController extends Controller
{
    private const SUPPORTED_ENTITIES = ['suppliers', 'materials', 'bom-lines', 'shipments'];

    public function __construct(
        private ProductionBatchService $service,
        private ErpSyncService $erpSyncService,
    ) {}

    public function productionBatch(Request $request): JsonResponse
    {
        $authMode = config('erp.auth_mode', 'hmac');

        if ($authMode === 'hmac') {
            $secret    = config('erp.webhook_secret');
            $signature = $request->header('X-ERP-Signature', '');
            $expected  = 'sha256=' . hash_hmac('sha256', $request->getContent(), $secret);
            if (!hash_equals($expected, $signature)) {
                return response()->json(['message' => 'Invalid signature'], 401);
            }
        } else {
            $apiKey    = config('erp.api_key');
            $provided  = str_replace('Bearer ', '', $request->header('Authorization', ''));
            if (!hash_equals($apiKey, $provided)) {
                return response()->json(['message' => 'Invalid API key'], 401);
            }
        }

        $data = $request->validate([
            'erp_batch_no'    => ['required', 'string', 'max:100'],
            'erp_order_no'    => ['nullable', 'string', 'max:100'],
            'erp_product_code'=> ['nullable', 'string', 'max:100'],
            'supplier_code'   => ['nullable', 'string', 'max:100'],
            'production_date' => ['nullable', 'date'],
            'quantity'        => ['required', 'numeric', 'min:0'],
            'unit'            => ['nullable', 'string', 'max:20'],
            'lot_pcf'         => ['nullable', 'numeric', 'min:0'],
            'lot_pcf_source'  => ['nullable', 'in:calculated,reported,estimated'],
        ]);

        $batch = $this->service->upsertFromPayload(array_merge($data, [
            'source'        => 'webhook',
            'erp_synced_at' => now(),
        ]));

        return response()->json(['success' => true, 'data' => $batch], 201);
    }

    /**
     * 通用 ERP Webhook 接收端點（已由 VerifyErpHmacSignature middleware 驗證簽章）
     * POST /api/v1/erp/webhook/{entity}
     */
    public function receive(Request $request, string $entity): JsonResponse
    {
        if (!in_array($entity, self::SUPPORTED_ENTITIES, true)) {
            return response()->json(['message' => "Unsupported entity: {$entity}"], 422);
        }

        $payload = $request->json()->all();

        if (empty($payload)) {
            return response()->json(['message' => 'Empty payload'], 422);
        }

        // 確保 payload 是資料列表格式
        $rows = isset($payload[0]) ? $payload : [$payload];

        $result = match ($entity) {
            'suppliers'  => $this->erpSyncService->syncSuppliers(null, 'webhook'),
            'materials'  => $this->erpSyncService->syncMaterials(null, 'webhook'),
            'bom-lines'  => $this->erpSyncService->syncBomLines(null, 'webhook'),
            'shipments'  => $this->erpSyncService->syncShipments(null, 'webhook'),
        };

        return response()->json([
            'success' => true,
            'entity'  => $entity,
            'result'  => $result,
        ], 200);
    }
}
