## Context

供應商端已有 `SupplierComplianceStatusService::syncSupplierApplicableRegulations()`，邏輯是：

```
Supplier → BomLines → MaterialGroup.required_doc_types → 對應 regulation key
```

BuyerProduct 有 `applicable_regulations[]`（JSON），目前完全人工維護，且 UI 已顯示於 DPP 頁籤。
ESPR 的適用條件是「產品銷往歐盟市場」，無法從 BOM 推算，只能人工聲明。

## Goals / Non-Goals

**Goals:**
- 新增 `inferred_regulations` 欄位，系統自動從 BOM 推算 EUDR/UFLPA/CMRT/SDS/CE
- `applicable_regulations` 保留為人工聲明欄位（主要用於 ESPR 及邊緣案例）
- 手動觸發 endpoint（Option A）：採購商可即時重算單一產品
- 每日排程（Option C）：凌晨批量重算所有產品，確保資料不超過 T+1 偏差
- 前端視覺區分推算來源 vs 人工聲明

**Non-Goals:**
- 即時 BomLine 寫入觸發（Option B Model Event）— 效能風險，先不實作
- ESPR 自動推算（需銷售地區資料，不在此次範圍）
- 推算歷史版本追蹤

## Decisions

### D1: 雙欄位模型而非覆蓋式

`inferred_regulations`（系統）+ `applicable_regulations`（人工）分開儲存，`displayed = union(兩者)`。

**理由**：若合併成單欄位，人工編輯後下次重算會覆蓋掉手動內容，無法區分哪些是系統推算、哪些是刻意人工標記。

**替代方案考慮**：`overridden_regulations[]` + `base_regulations[]` — 更複雜，UI 難理解。

### D2: doc_type → regulation key 的對應由常數定義

```php
const DOC_TYPE_TO_REGULATION = [
    'eudr' => 'EUDR',
    'uflpa' => 'UFLPA',
    'cmrt'  => 'CMRT',
    'sds'   => 'SDS',
    'ce'    => 'CE',
];
```

放在 `SupplierComplianceStatusService`（或抽取到 shared constant），與供應商端邏輯共用。

**理由**：單一真相來源，前後端 regulation key 統一。

### D3: 排程使用 Laravel Artisan Command + schedule()

新增 `app/Console/Commands/SyncProductRegulations.php`，在 `app/Console/Kernel.php` 排程 `->daily()`。

**理由**：標準 Laravel 方式，易於手動觸發 `php artisan sync:product-regulations` 做測試。

### D4: 推算邏輯放在 Service 層

`SupplierComplianceStatusService::syncProductInferredRegulations(BuyerProduct)` 回傳推算結果並儲存。

**理由**：Controller 保持薄，邏輯可被 Artisan Command 與 HTTP endpoint 共用。

## Risks / Trade-offs

- **推算結果 T+1 延遲**：排程在每日凌晨執行，白天 BOM 異動不會即時反映。→ 手動觸發按鈕補足即時需求。
- **BOM 無 MaterialGroup 的料號**：`material_group_id` 為 null 的 BomLine 無法推算。→ 跳過，不推算，UI 顯示「部分未分類」提示。
- **大量產品批量推算效能**：若 BuyerProduct 數量很大，每日排程可能需要分批。→ 初版不分批，量大時再加 chunk()。

## Migration Plan

1. 新增 migration：`buyer_products` 加 `inferred_regulations JSON NULL`
2. 執行 `php artisan migrate`
3. 執行 `php artisan sync:product-regulations` 做首次全量推算
4. 部署前端（視覺區分標籤）
5. Rollback：`down()` 移除欄位，前端 fallback 到 `applicable_regulations` 顯示

## Open Questions

- 是否需要在產品 edit modal 加 ESPR 勾選框（現在 `applicable_regulations` 是 free-form JSON）？建議：改為 checkbox list，限定 `['EUDR','UFLPA','CMRT','SDS','CE','ESPR']` 可選值。
