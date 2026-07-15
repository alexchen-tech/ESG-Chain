## Context

**現有程式碼確認：**
- `MaterialItem` 欄位：id/item_code/name/hs_code/unit/material_group_id/description/net_weight/pcr_percentage/is_active
- `MaterialItemEmission.source` enum：`'portal-self'`, `'buyer-input'`, `'ai-estimated'`
- `MaterialItemChemical.source` enum：`'portal_supplier'`, `'buyer_input'`, `'ai_estimated'`
- `ChemicalComplianceAlert` 已有 `GET /api/v1/chemical-compliance-alerts?material_item_id={id}`（index 方法支援 filter）——無需新增 endpoint，只需前端呼叫此既有路由
- `ChemicalComplianceAlertController::scan()` 已掛在 `POST /api/v1/material-items/{id}/chemical-compliance-scan`
- `MaterialItemsView.vue` 展開 section：兩個（碳排 `expandedItemId`、化學 `chemicalExpandedId`），各自在 template 裡是獨立的 `<tr v-if>` 列
- BOM 供應商：無 material_item ↔ supplier 直接 pivot，需從 `ProductBomLine.material_item_id` JOIN `BomLineSupplier.role='primary'` 推算
- `MaterialItem` interface（前端 compliance.ts）目前有 `net_weight`、`pcr_percentage`，需擴充三個新欄位

## Goals / Non-Goals

**Goals:**
- **A. 來源供應商面板**：後端新 API + 前端新展開 section
- **B. 化學合規結果內嵌**：前端新增呼叫既有 alert API，化學展開列底部顯示掃描結果
- **C. 可回收材料細項**：migration 新增三欄位 + 前端新展開 section + 行列 PCR badge + modal 更新

**Non-Goals:**
- 不修改 ESPR/DPP 計算邏輯（pcr_percentage 不重命名）
- 不實作供應商認證（GRS）的 material 層級追蹤（留後續）
- 不新增化學成分 CSV 批次匯入

## Decisions

### A. 來源供應商 API

新增 `GET /api/v1/material-items/{materialItem}/bom-suppliers`，回傳：
```json
[
  {
    "supplier_id": "...",
    "supplier_name": "...",
    "bom_count": 2,
    "latest_emission": { "emissions_value": 3.2, "source": "buyer-input", "is_flagged": false } | null
  }
]
```
由 `ProductBomLine::where('material_item_id', $id)->with('bomLineSuppliers')` 彙整，去重 supplier_id，JOIN MaterialItemEmission 取最新碳排。

掛在 `MaterialItemController` 新方法 `bomSuppliers()`，路由加在既有 material-items routes 群組。

### B. 化學合規結果

前端直接呼叫 `GET /api/v1/chemical-compliance-alerts?material_item_id={id}&per_page=50`，不需新後端路由。
化學展開列底部新增「合規掃描結果」小節：
- loading 中顯示 spinner
- 無警告：`✓ 未偵測到受管制物質`（綠）
- 有警告：依 alert_level 分組顯示，critical → 紅、warning → 橘、info → 藍
- 每筆顯示：受管制清單（reach_svhc / rohs）+ 物質名稱 + 限制備註（截斷 50 字元）
- 「重新掃描」按鈕（呼叫既有 POST chemical-compliance-scan）

前端 state 新增：`chemicalAlerts`, `chemicalAlertsLoading`（在 toggleChemical 時同步載入）

### C. 可回收材料

**Migration**：`add_recycled_fields_to_material_items`
```php
$table->decimal('pir_percentage', 5, 2)->nullable()->after('pcr_percentage');
$table->decimal('bio_based_percentage', 5, 2)->nullable()->after('pir_percentage');
$table->enum('recyclability_rating', ['high','medium','low','not_rated'])->nullable()->after('bio_based_percentage');
```

**前端 UI**：
- 行列（table row）：`pcr_percentage > 0` 時顯示綠底 badge（例如 `PCR 35%`）
- 新增第三個展開 section「可回收材料」，key `recycledExpandedId`，按鈕標籤「回收」
  - 內嵌表單（inline edit）：PCR、PIR、Bio-based 各一個 number input，recyclability_rating 下拉
  - 合計欄：`總回收成分 = PCR + PIR`（自動計算）
  - 儲存按鈕呼叫 `PUT /api/v1/material-items/{id}`（既有 update API，直接傳新欄位）
- 編輯 modal 新增三個欄位（保留原 net_weight 和 pcr_percentage 欄位，額外追加）

**MaterialItem interface** 擴充：
```ts
pir_percentage: number | null
bio_based_percentage: number | null
recyclability_rating: 'high' | 'medium' | 'low' | 'not_rated' | null
```

**MaterialItem model `$fillable`** 追加三個欄位。

## Open Questions

- DPP 就緒計算目前只用 `pcr_percentage`，未來是否要改為「PCR + PIR 合計」？→ 不在本次範圍，本次只加欄位與 UI，不動 ESPR 計算邏輯
