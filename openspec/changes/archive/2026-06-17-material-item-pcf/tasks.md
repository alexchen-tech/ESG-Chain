## 1. 資料庫 Migration

- [x] 1.1 新增 migration：建立 `material_item_emissions` 表（id, material_item_id, supplier_id, emissions_value decimal(12,6), source enum, calculation_method nullable, reported_period nullable, is_estimated boolean, is_flagged boolean, flag_reason nullable, reported_at, timestamps）
- [x] 1.2 新增 migration：建立 `pcf_snapshots` 表（id, buyer_product_id, total_pcf decimal(12,6), functional_unit, iso14067_ready boolean, snapshot_at, lines JSON, timestamps）
- [x] 1.3 同步 migrations 至 Docker 並執行，確認表結構正確

## 2. 後端 Model 與關聯

- [x] 2.1 新增 `MaterialItemEmission` model（HasUuids, fillable, casts, BelongsTo MaterialItem / Supplier）
- [x] 2.2 新增 `PcfSnapshot` model（HasUuids, fillable, casts lines→array, BelongsTo BuyerProduct）
- [x] 2.3 更新 `MaterialItem` model：新增 `emissions()` hasMany 關聯、`latestEmissionForSupplier(supplierId)` scope
- [x] 2.4 更新 `BuyerProduct` model：新增 `pcfSnapshots()` hasMany、`latestPcfSnapshot()` 方法

## 3. 後端 Service — 碳排 MDM

- [x] 3.1 新增 `MaterialEmissionService`：`listForMaterial(materialItemId)` — 依 supplier 分組，含最新值 + 歷史筆數
- [x] 3.2 新增 `MaterialEmissionService::record(materialItemId, supplierId, payload, source)` — 新增一筆記錄，超出合理範圍自動 flag
- [x] 3.3 新增 `MaterialEmissionService::flag(emissionId, reason)` / `unflag(emissionId)` — 異常標記
- [x] 3.4 新增 `MaterialEmissionService::getBestEmissionForSupplier(materialItemId, supplierId)` — 依優先級取值（portal-self > buyer-input > ai-estimated）

## 4. 後端 Service — PCF 計算

- [x] 4.1 新增 `PcfCalculationService::calcForProduct(BuyerProduct)` — 遍歷 BomLine，取 primary supplier 最佳碳排，計算各行小計，加總 total_pcf，判斷 iso14067_ready
- [x] 4.2 新增 `PcfCalculationService::buildSnapshotLines(BuyerProduct)` — 產生 lines JSON 陣列（含 data_quality, is_estimated, emission_source, reported_period）
- [x] 4.3 新增 `PcfCalculationService::snapshot(BuyerProduct)` — 寫入 pcf_snapshots，回傳快照 id

## 5. 後端 Controller 與路由

- [x] 5.1 新增 `MaterialEmissionController`：`index(materialItemId)`、`store(materialItemId)`、`flag(emissionId)`、`unflag(emissionId)` — 買方端 CRUD
- [x] 5.2 新增 `PcfSnapshotController`：`latest(buyerProductId)`、`show(snapshotId)` — 快照查詢
- [x] 5.3 新增路由：`GET/POST /api/v1/material-items/{id}/emissions`、`POST /api/v1/material-emissions/{id}/flag`、`POST /api/v1/material-emissions/{id}/unflag`
- [x] 5.4 新增路由：`GET /api/v1/products/{id}/pcf-latest`、`GET /api/v1/pcf-snapshots/{id}`
- [x] 5.5 新增 Portal routes：`GET /api/v1/portal/material-emissions`（需提報清單）、`POST /api/v1/portal/material-emissions`（提報）、`GET /api/v1/portal/material-items`（搜尋，供主動提報用）
- [x] 5.6 更新 `BomLineSupplier` 相關 Controller：`setRole(primary/alternate)` 端點，切換後觸發 PCF 重算（同步呼叫 PcfCalculationService）
- [x] 5.7 同步所有後端檔案至 Docker，`docker restart esgchain-api`，驗證登入正常

## 6. 後端 Celery Tasks（esgchain-ai）

- [x] 6.1 新增 FastAPI 端點 `POST /ai/v1/material-emission-estimate`（input: hs_code, supplier_id; output: emissions_value, factor_source）
- [x] 6.2 新增 Celery Task `estimate_material_emission`：呼叫 FastAPI 端點，結果寫回 Laravel API（POST /api/v1/material-items/{id}/emissions with source=ai-estimated）
- [x] 6.3 新增 Celery Task `recalc_pcf_for_product(buyer_product_id)`：呼叫 PcfCalculationService，結果寫入 pcf_snapshots
- [x] 6.4 在 BomLineSupplier 新增/切換 primary 時觸發 `estimate_material_emission`（若無碳排記錄）與 `recalc_pcf_for_product`

## 7. 前端 API 模組

- [x] 7.1 在 `api/modules/compliance.ts` 或新建 `api/modules/materialEmissions.ts` 新增 interface `MaterialItemEmission`、`PcfSnapshot`、`PcfSnapshotLine`
- [x] 7.2 新增 API functions：`materialEmissionApi.list(materialItemId)`、`materialEmissionApi.create(materialItemId, payload)`、`materialEmissionApi.flag(emissionId, reason)`
- [x] 7.3 新增 API functions：`pcfSnapshotApi.latest(productId)`、`pcfSnapshotApi.get(snapshotId)`
- [x] 7.4 新增 Portal API：`portalMaterialEmissionApi.list()`（需提報清單）、`portalMaterialEmissionApi.create(payload)`、`portalMaterialEmissionApi.searchMaterials(keyword)`

## 8. 前端 MaterialItemsView — 碳排資料庫分頁

- [x] 8.1 在 MaterialItemsView 的展開列（或點擊進入詳情）新增「碳排資料庫」分頁 tab
- [x] 8.2 分頁顯示 `materialEmissionApi.list()` 回傳資料，依供應商分組，最新值 + 歷史筆數
- [x] 8.3 AI 估算值以灰底虛線框 + 🤖 標記；is_flagged=true 以 ⚠ 標記 + flag_reason tooltip
- [x] 8.4 「代填」按鈕：開啟 modal（emissions_value, reported_period, calculation_method, calculation_note）呼叫 buyer-input 提報
- [x] 8.5 「標記異常」按鈕：輸入原因後呼叫 flag API；已標記顯示「取消標記」按鈕
- [x] 8.6 主動提報供應商顯示「未指定於 BOM」標記

## 9. 前端 BuyerProductsView — PCF 欄位與快照 Drawer

- [x] 9.1 BuyerProduct 清單列新增「PCF」欄位，顯示 `pcfSnapshotApi.latest()` 的 total_pcf 值
- [x] 9.2 PCF 顏色規則：iso14067_ready=true → 深綠；有估算 → 橘色 + X/N 待實測；無資料 → 灰色「—」
- [x] 9.3 點擊 PCF 值展開快照明細 Drawer（或 modal），顯示 lines 表：物料名稱、供應商、碳排/unit、數量、小計、來源 badge、⚠ 異常標記
- [x] 9.4 Drawer 底部顯示 total_pcf、snapshot_at、iso14067_ready 標記

## 10. 前端 BOM 明細 — 主供應商切換 UI

- [x] 10.1 BomLine 供應商 sub-row 中每個 BomLineSupplier 顯示「主要 / 備用」角色 badge，可點擊切換
- [x] 10.2 切換角色呼叫新增的 setRole API，成功後重新載入 BomLine 供應商清單
- [x] 10.3 BomLine 列顯示 primary 供應商的碳排值（kgCO₂e/unit），來源以 🧑 / 🤖 標記

## 11. 前端 Portal — 物料碳排提報分頁

- [x] 11.1 在 `SupplierCompliancePortalView.vue` 的 tab 列新增「物料碳排」tab（現有：合規文件 / 碳排回報 / 物料碳排）
- [x] 11.2 物料碳排 tab：載入 `portalMaterialEmissionApi.list()` 顯示需提報清單，標示已提報 / 待提報 / AI估算中（Option C：顯示估算值 + 灰框 + 🤖 + 提示文字）
- [x] 11.3 「填報碳排」/ 「更新數值」按鈕開啟 modal：emissions_value（必填）、reported_period（下拉 YYYY-Q1~Q4）、calculation_method（下拉）、calculation_note（選填）
- [x] 11.4 「主動填報其他物料」入口：搜尋 MaterialItem（by 料號/名稱），選擇後開啟相同表單
- [x] 11.5 提報成功後刷新清單，已提報物料顯示最新值與 reported_period

## 12. 驗證

- [x] 12.1 供應商 Portal 提報物料碳排，確認 material_item_emissions 記錄建立（source=portal-self）
- [x] 12.2 確認提報後 PCF 自動重算，pcf_snapshots 新增快照
- [x] 12.3 切換 BomLine 主供應商，確認 PCF 重算使用新 primary 的碳排值
- [x] 12.4 BomLine 加入後無碳排記錄，確認 AI 估算 Celery Task 觸發並寫入 ai-estimated 記錄
- [x] 12.5 前端確認 PCF 欄位顏色、待實測筆數、快照明細 Drawer 正確顯示
- [x] 12.6 買方代填 + 異常標記流程，確認 is_flagged=true 且快照明細顯示 ⚠
<!-- API endpoints verified: /material-items/{id}/emissions OK, /products/{id}/pcf-latest OK -->
