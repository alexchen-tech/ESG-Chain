<?php

namespace App\Http\Controllers\Api\Compliance;

use App\Http\Controllers\Controller;
use App\Models\SalesProduct;
use App\Models\Supplier;
use App\Services\Compliance\SupplierComplianceStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ComplianceDashboardController extends Controller
{
    public function __construct(
        private readonly SupplierComplianceStatusService $service,
    ) {}

    public function supplierSummary(Supplier $supplier): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->service->getSupplierSummary($supplier),
        ]);
    }

    public function productStatus(SalesProduct $salesProduct): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->service->getProductCompliance($salesProduct),
        ]);
    }

    public function supplierDashboard(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->service->getSupplierDashboard(),
        ]);
    }

    public function productDashboard(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->service->getProductDashboard(),
        ]);
    }

    public function supplierBomRequirements(Supplier $supplier): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->service->getSupplierBomRequirements($supplier),
        ]);
    }

    public function matrixData(Request $request): JsonResponse
    {
        $tier         = $request->query('tier') !== null ? (int) $request->query('tier') : null;
        $riskScoreMin = $request->query('risk_score_min') !== null ? (float) $request->query('risk_score_min') : null;
        $page         = (int) $request->query('page', 1);
        $perPage      = (int) $request->query('per_page', 20);

        $result = $this->service->getMatrixData($request->query('supplier_group_id'), $tier, $riskScoreMin, $page, $perPage);
        $pagination = $result['pagination'];
        unset($result['pagination']);

        return response()->json([
            'success'    => true,
            'data'       => $result,
            'pagination' => $pagination,
        ]);
    }

    public function matrixDrill(Request $request): JsonResponse
    {
        $tier         = $request->query('tier') !== null ? (int) $request->query('tier') : null;
        $riskScoreMin = $request->query('risk_score_min') !== null ? (float) $request->query('risk_score_min') : null;

        return response()->json([
            'success' => true,
            'data'    => $this->service->getMatrixDrill(
                $request->query('material_group_id'),
                $request->query('doc_type'),
                $request->query('supplier_group_id'),
                $tier,
                $riskScoreMin,
            ),
        ]);
    }

    public function pendingVerifications(Request $request): JsonResponse
    {
        $page    = (int) $request->query('page', 1);
        $perPage = (int) $request->query('per_page', 20);

        $result = $this->service->getPendingVerifications($page, $perPage);

        return response()->json([
            'success'    => true,
            'data'       => $result['data'],
            'pagination' => $result['pagination'],
        ]);
    }

    public function dppReadiness(Request $request): JsonResponse
    {
        $page    = (int) $request->query('page', 1);
        $perPage = (int) $request->query('per_page', 20);

        $result = $this->service->getDppReadinessList($page, $perPage);

        return response()->json([
            'success'    => true,
            'data'       => $result['data'],
            'pagination' => $result['pagination'],
        ]);
    }

    public function dppReadinessDetail(SalesProduct $salesProduct): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->service->getDppReadinessDetail($salesProduct),
        ]);
    }

    public function syncProductRegulations(SalesProduct $salesProduct): JsonResponse
    {
        $inferred = $this->service->syncProductInferredRegulations($salesProduct);
        $salesProduct->refresh();

        return response()->json([
            'success'  => true,
            'data'     => [
                'id'                     => $salesProduct->id,
                'inferred_regulations'   => $inferred,
                'applicable_regulations' => $salesProduct->applicable_regulations ?? [],
            ],
            'message'  => '法規推算完成',
        ]);
    }
}
