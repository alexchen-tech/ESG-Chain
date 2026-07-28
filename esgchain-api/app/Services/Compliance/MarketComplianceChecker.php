<?php

namespace App\Services\Compliance;

use App\Models\MarketComplianceRule;
use App\Models\PcfSnapshot;
use App\Models\RiskAssessment;
use App\Models\SupplierComplianceDoc;
use App\Models\TradeGood;

class MarketComplianceChecker
{
    public function __construct(private readonly ProductUpstreamResolver $upstream) {}

    /**
     * 計算單一商品在指定市場的合規狀態。
     *
     * $supplierIdsOverride：批號情境下應傳入該批次「實際選定」的供應商 ID
     * （見 ProductUpstreamResolver::batchSupplierIds()），而非籠統採用產品
     * BOM 全部上游供應商——批號→選供應商→合規調查，需以批次實際選定的供應商
     * 文件為準。未傳入時（如商品列表頁的一般合規檢視）退回產品層級全部上游供應商。
     *
     * $program：篩選只跑哪個法規範疇（見 MarketComplianceRule::PROGRAMS）的規則，
     * 未傳入時（null）涵蓋該市場全部範疇，維持既有行為。
     */
    public function check(TradeGood $good, string $market, ?array $supplierIdsOverride = null, ?string $program = null): array
    {
        $materialDocTypes = $this->upstream->materialGroupDocTypes($good);

        if ($market === 'EU' && $good->is_cbam_applicable) {
            $materialDocTypes[] = 'CBAM_REPORT';
        }

        $materialDocTypes = array_unique($materialDocTypes);

        // product 層規則（如 DPP/CPSIA）市場內一律適用；material 層依物料群組文件需求觸發
        $rules = MarketComplianceRule::active()
            ->forMarket($market)
            ->when($program, fn ($q) => $q->where('program', $program))
            ->where(function ($q) use ($materialDocTypes) {
                $q->where('scope', 'product')
                  ->orWhere(fn ($q2) => $q2->where('scope', 'material')
                      ->whereIn('doc_type', $materialDocTypes ?: ['__none__']));
            })
            ->get();

        if ($rules->isEmpty()) {
            return $this->emptyResult($market);
        }

        $supplierIds = $supplierIdsOverride ?? $this->upstream->supplierIds($good);

        $docs = SupplierComplianceDoc::where(function ($q) use ($good, $supplierIds) {
            // trade_good_id 路徑保留但實務上幾乎未使用，主要資料流走 supplier_id
            $q->where('trade_good_id', $good->id)
              ->orWhereIn('supplier_id', $supplierIds);
        })->whereIn('doc_type', $rules->pluck('doc_type')->toArray())
          ->orderByDesc('created_at')
          ->get()
          ->groupBy('doc_type');

        $results = $rules->map(function (MarketComplianceRule $rule) use ($docs) {
            $doc    = $docs->get($rule->doc_type)?->first();
            $status = $doc ? $doc->status : 'missing';

            return [
                'doc_type'     => $rule->doc_type,
                'is_mandatory' => $rule->is_mandatory,
                'status'       => $status,
                'expires_at'   => $doc?->expires_at?->toDateString(),
                'supplier_id'   => $doc?->supplier_id,
                'supplier_name' => $doc?->supplier?->name,
            ];
        })->values()->toArray();

        $overall = $this->calcOverall($results);
        $supplierRiskContext = $this->buildSupplierRiskContext($supplierIds, $good->id);

        return [
            'market'                => $market,
            'required'              => $rules->map(fn ($r) => ['doc_type' => $r->doc_type, 'is_mandatory' => $r->is_mandatory])->values()->toArray(),
            'results'               => $results,
            'overall'               => $overall,
            'supplier_risk_context' => $supplierRiskContext,
        ];
    }

    /**
     * 批次計算多筆商品的合規狀態，單次查詢避免 N+1，最多 100 筆。
     *
     * $program：篩選只跑哪個法規範疇的規則，語意同 check()，未傳入時（null）
     * 涵蓋全部範疇，維持既有行為、向下相容。
     */
    public function checkBatch(array $goodIds, string $market, ?string $program = null): array
    {
        $goodIds = array_slice($goodIds, 0, 100);

        $goods = TradeGood::whereIn('id', $goodIds)->get()->keyBy('id');

        $rules = MarketComplianceRule::active()
            ->forMarket($market)
            ->when($program, fn ($q) => $q->where('program', $program))
            ->get()->keyBy('doc_type');

        if ($rules->isEmpty()) {
            return collect($goodIds)->mapWithKeys(fn ($id) => [$id => $this->emptyResult($market)])->toArray();
        }

        $supplierIdsByGood = $goods->mapWithKeys(fn ($g) => [$g->id => $this->upstream->supplierIds($g)]);
        $allSupplierIds = collect($supplierIdsByGood)->flatten()->unique()->values()->toArray();
        $allTradeGoodIds = $goodIds;

        $allDocs = SupplierComplianceDoc::where(function ($q) use ($allTradeGoodIds, $allSupplierIds) {
            // trade_good_id 路徑保留但實務上幾乎未使用，主要資料流走 supplier_id
            $q->whereIn('trade_good_id', $allTradeGoodIds)
              ->orWhereIn('supplier_id', $allSupplierIds);
        })->whereIn('doc_type', $rules->keys()->toArray())
          ->with('supplier:id,name')
          ->orderByDesc('created_at')
          ->get()
          ->groupBy(['doc_type', fn ($d) => $d->trade_good_id ?? $d->supplier_id]);

        $output = [];
        foreach ($goodIds as $goodId) {
            $good = $goods->get($goodId);
            if (!$good) {
                $output[$goodId] = $this->emptyResult($market);
                continue;
            }

            $materialDocTypes = $this->upstream->materialGroupDocTypes($good);
            if ($market === 'EU' && $good->is_cbam_applicable) {
                $materialDocTypes[] = 'CBAM_REPORT';
            }
            $materialDocTypes = array_unique($materialDocTypes);

            $applicableRules = $rules->filter(fn ($r) =>
                $r->scope === 'product' || in_array($r->doc_type, $materialDocTypes));

            if ($applicableRules->isEmpty()) {
                $output[$goodId] = $this->emptyResult($market);
                continue;
            }

            $supplierIds = $supplierIdsByGood->get($goodId, []);

            $results = $applicableRules->map(function (MarketComplianceRule $rule) use ($allDocs, $goodId, $supplierIds) {
                $docByGood     = $allDocs->get($rule->doc_type)?->get($goodId)?->first();
                $docBySupplier = null;
                foreach ($supplierIds as $sid) {
                    $docBySupplier = $allDocs->get($rule->doc_type)?->get($sid)?->first();
                    if ($docBySupplier) break;
                }
                $doc    = $docByGood ?? $docBySupplier;
                $status = $doc ? $doc->status : 'missing';

                return [
                    'doc_type'      => $rule->doc_type,
                    'is_mandatory'  => $rule->is_mandatory,
                    'status'        => $status,
                    'expires_at'    => $doc?->expires_at?->toDateString(),
                    'supplier_name' => $doc?->supplier?->name,
                ];
            })->values()->toArray();

            $output[$goodId] = [
                'market'   => $market,
                'required' => $applicableRules->map(fn ($r) => ['doc_type' => $r->doc_type, 'is_mandatory' => $r->is_mandatory])->values()->toArray(),
                'results'  => $results,
                'overall'  => $this->calcOverall($results),
            ];
        }

        return $output;
    }

    private function buildSupplierRiskContext(array $supplierIds, string $tradeGoodId): array
    {
        if (empty($supplierIds)) return [];

        // 取各供應商最新 RiskAssessment 的 dim_e6 + axis1_score（axis1 保留向下相容）
        $latestRisks = RiskAssessment::whereIn('supplier_id', $supplierIds)
            ->select('supplier_id', 'axis1_score', 'dim_e6', 'assessed_at')
            ->orderByDesc('assessed_at')
            ->get()
            ->unique('supplier_id')
            ->keyBy('supplier_id');

        // 取適用法規（從 TradeGood inferred_regulations）
        $tg = TradeGood::where('id', $tradeGoodId)->first();
        $regulations = $tg?->inferred_regulations ?? [];

        // 取最新 PcfSnapshot 的供應商碳排明細
        $snapshot  = PcfSnapshot::where('sales_product_id', $tradeGoodId)->orderByDesc('created_at')->first();
        $breakdown = $snapshot?->breakdown ?? [];

        return collect($supplierIds)->map(function (string $supplierId) use ($latestRisks, $breakdown, $regulations) {
            $risk  = $latestRisks->get($supplierId);
            $dimE6 = $risk?->dim_e6 !== null ? (float) $risk->dim_e6 : null;
            $co2   = $breakdown[$supplierId]['co2_kg'] ?? null;

            $e6Status = $this->calcE6Status($dimE6, $regulations);

            return [
                'supplier_id'  => $supplierId,
                'axis1_score'  => $risk?->axis1_score,  // 保留向下相容
                'dim_e6'       => $dimE6,
                'e6_status'    => $e6Status,
                'has_data_gap' => $e6Status === 'gap',
                'emission_kg'  => $co2,
            ];
        })->values()->toArray();
    }

    /**
     * E6 法規準備狀態四態判斷：
     *   ok           → dim_e6 有資料（任何值）
     *   not_applicable → dim_e6 null 且無適用法規
     *   gap          → dim_e6 null 且有適用法規（資料缺口）
     *   low          → dim_e6 有資料但低於閾值（50）
     *
     * @param  string[]  $regulations
     */
    private function calcE6Status(?float $dimE6, array $regulations): string
    {
        $e6Threshold = 50.0;

        if ($dimE6 === null) {
            return count($regulations) > 0 ? 'gap' : 'not_applicable';
        }

        return $dimE6 < $e6Threshold ? 'low' : 'ok';
    }

    private function calcOverall(array $results): string
    {
        $overall = 'pass';
        foreach ($results as $r) {
            $bad = in_array($r['status'], ['missing', 'expired'], true);
            if ($bad && ($r['is_mandatory'] ?? true)) return 'fail';   // 強制缺失 → fail
            if ($bad || $r['status'] === 'expiring_soon') $overall = 'warning'; // 建議缺失/即期 → warning
        }
        return $overall;
    }

    private function emptyResult(string $market): array
    {
        return ['market' => $market, 'required' => [], 'results' => [], 'overall' => 'pass'];
    }
}
