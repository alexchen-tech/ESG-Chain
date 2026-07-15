## 1. 資料庫 Migration

- [x] 1.1 新增 migration：`trade_goods.supplier_id` 改為 nullable（ALTER COLUMN）
- [x] 1.2 新增 migration：建立 `trade_good_suppliers` 表（id, trade_good_id, supplier_id, material_group_id nullable, notes nullable, timestamps）
- [x] 1.3 新增 migration：建立 `trade_good_supplier_emissions` 表（id, trade_good_id, supplier_id, emissions_value decimal, calculation_note nullable, reported_at, confirmed_at nullable, timestamps）
- [x] 1.4 新增 migration：資料遷移 — 現有 trade_goods.supplier_id 有值的記錄，插入對應 trade_good_suppliers 記錄
- [x] 1.5 新增 migration：DROP COLUMN `trade_goods.supplier_id`
- [x] 1.6 同步所有 migrations 至 Docker 並執行，驗證資料完整

## 2. 後端 Model 與關聯

- [x] 2.1 更新 `TradeGood` model：移除 `supplier_id` from `$fillable`、移除 `supplier()` 關聯、新增 `tradeGoodSuppliers()` hasMany、新增 `emissionReports()` hasMany
- [x] 2.2 新增 `TradeGoodSupplier` model（HasUuids, BelongsTo TradeGood / Supplier / MaterialGroup）
- [x] 2.3 新增 `TradeGoodSupplierEmission` model（HasUuids, BelongsTo TradeGood / Supplier）
- [x] 2.4 更新 `TradeGoodObserver`：`clearGroupCache()` 改為走 `tradeGoodSuppliers` 關聯查詢 SupplierGroup

## 3. 後端 Service

- [x] 3.1 新增 `TradeGoodService`：`getList()` — 含 CBAM 狀態、is_eudr_applicable（BOM → MaterialGroup.required_doc_types）、upstream_compliance_status（取最差狀態）
- [x] 3.2 新增 `TradeGoodService::getUpstreamCompliance(TradeGood)` — 遍歷 tradeGoodSuppliers，計算每個供應商的文件狀態
- [x] 3.3 新增 `TradeGoodService::confirmEmissions(TradeGoodSupplierEmission)` — 更新 confirmed_at，同步 trade_goods.embedded_emissions，寫 AuditLog

## 4. 後端 Controller 與路由

- [x] 4.1 改寫 `TradeGoodController::index()` — 使用 TradeGoodService::getList()，移除舊 supplier_id 篩選
- [x] 4.2 改寫 `TradeGoodController::store()` / `update()` — 移除 supplier_id 驗證規則
- [x] 4.3 新增 `TradeGoodController::suppliers()` — 列出某 TradeGood 的上游供應商
- [x] 4.4 新增 `TradeGoodController::addSupplier()` / `removeSupplier()` — 管理 trade_good_suppliers
- [x] 4.5 新增 `TradeGoodController::emissionReports()` — 列出某 TradeGood 的所有碳排回報
- [x] 4.6 新增 `TradeGoodController::confirmEmission()` — 中心廠確認採用某筆碳排
- [x] 4.7 新增 Portal endpoint：`GET /api/v1/supplier/portal/trade-goods` — 供應商查看被關聯的品項
- [x] 4.8 新增 Portal endpoint：`POST /api/v1/supplier/portal/trade-goods/{id}/emissions` — 供應商填報碳排
- [x] 4.9 更新 `routes/api.php`：新增所有上述路由
- [x] 4.10 同步所有後端檔案至 Docker，`docker restart esgchain-api`，驗證登入正常

## 5. 前端 API 模組

- [x] 5.1 新增 `esgchain-web/src/api/modules/tradeGoods.ts`：定義 TradeGood / TradeGoodSupplier / EmissionReport interfaces，定義 tradeGoodApi（list, create, update, destroy, suppliers, addSupplier, removeSupplier, emissionReports, confirmEmission）
- [x] 5.2 新增 Portal API：`portalTradeGoodsApi.list()` / `portalTradeGoodsApi.reportEmission(id, payload)`

## 6. 前端 TradeGoodsView.vue

- [x] 6.1 新增 `esgchain-web/src/views/trade-goods/TradeGoodsView.vue`（移至 `views/trade-goods/`，router 改為 lazy import）
- [x] 6.2 實作品項清單：表格含品項名稱、HS Code、CBAM 標籤、EUDR 標籤、嵌入碳排、上游合規狀態、操作按鈕
- [x] 6.3 實作新增 / 編輯 modal：name, product_code, hs_code, unit, unit_price, currency, description
- [x] 6.4 實作展開面板：上游供應商列表，含新增供應商（選 Supplier + MaterialGroup）、移除、各文件狀態
- [x] 6.5 實作碳排面板：列出所有供應商回報值（emissions_value, reported_at, confirmed_at），「確認採用」按鈕
- [x] 6.6 Router 新增 `/trade-goods` 路由，指向 TradeGoodsView

## 7. 前端 Portal 碳排回報

- [x] 7.1 在 `SupplierCompliancePortalView.vue` 或新增頁籤，加入「碳排回報」區塊
- [x] 7.2 顯示供應商被關聯的 TradeGood 清單，標示已填報 / 待填報
- [x] 7.3 新增填報表單：emissions_value（數字）、calculation_note（文字），送出後顯示成功
- [x] 7.4 已填報品項顯示最新數值與回報時間

## 8. 驗證

- [x] 8.1 建立 TradeGood，確認 CBAM 自動判定正確（HS Code 7208 → steel）
- [x] 8.2 新增上游供應商，確認 EUDR 適用性與合規狀態計算正確
- [x] 8.3 供應商 Portal 填報碳排，確認回報記錄建立
- [x] 8.4 中心廠「確認採用」，確認 embedded_emissions 更新且 AuditLog 寫入
- [x] 8.5 前端確認 CBAM / EUDR 標籤、合規狀態顯示正確
