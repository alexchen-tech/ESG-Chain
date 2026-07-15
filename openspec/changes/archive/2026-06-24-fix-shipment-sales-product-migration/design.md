## Context

這是 `fix-erp-material-bom-sync-integrity` change 之後發現的第二個同根因問題。2026-06-17 那批 SalesProduct 重構（`BuyerProduct` + `TradeGood` 合併為 `SalesProduct`）的執行方式是：先改資料表結構與 Eloquent Model 關聯，再逐個模組地修正呼叫端程式碼。前一個 change 修的是 BOM 模組（`BomLineImportService`、`ErpSyncService`），這次發現 Shipment 模組同樣沒有跟進修正，而且比 BOM 模組更糟：

- `ShipmentLineController::store()`（第 21-22 行）的驗證規則同時保留 `trade_good_id`（`exists:trade_goods,id`）與 `buyer_product_id`（`exists:buyer_products,id`）兩個欄位，但 `trade_goods` 與 `buyer_products` 兩張表都已被刪除——這代表新增出貨明細這個動作，光是驗證階段就會失敗（`exists` 規則查詢一張不存在的表，Laravel 會拋 QueryException，而非乾淨的 422）
- `ShipmentLineController::store()` 第 29 行、`ShipmentController::index/show`（第 17、50 行）、`ShipmentService`（第 77、79、81、158、166 行）皆呼叫 `tradeGood` 關聯，但 `ShipmentLine` model（`app/Models/ShipmentLine.php`）只定義了 `salesProduct()`，沒有 `tradeGood()` 也沒有 `buyerProduct()`
- `ShipmentSeeder`（第 9、18-19、35 行）`use App\Models\TradeGood` 並查詢 `TradeGood::where(...)`——`TradeGood` model 本身是否還存在、對應的表是否還在，需要在實作時先確認（若 `TradeGood` model 已被刪除，這裡會是 class-not-found 錯誤；若 model 還在但表已刪，會是 SQL exception）

## Goals / Non-Goals

**Goals:**
- 讓 Shipment 模組（列表、詳情、新增/編輯出貨明細、批號分配與 PCF 加權計算、EUDR DDS 草稿生成、種子資料）恢復可用
- 同時清掉 `ShipmentLineController::store()` 裡殘留的 `buyer_product_id` 驗證規則與 `buyerProduct` 關聯呼叫（與 `trade_good_id` 同一行程式碼修正，不分開處理）
- 在 tasks 最後一步做一次全專案掃描（`grep -rn 'tradeGood\|trade_good_id\|TradeGood\|buyerProduct\|buyer_product_id\|BuyerProduct'`），確認沒有第三個模組有同類殘留——這次把 BuyerProduct 也一併掃，因為已經連續在兩個模組發現它的殘留引用

**Non-Goals:**
- 不處理舊版 `buyer-products` 路由群組本身（`fix-erp-material-bom-sync-integrity` 的 design.md 已將其列為獨立 Open Question，本次不重複處理）
- 若掃描發現第三個模組也有殘留引用，不在本次一併修——另開新 change，避免單次變更範圍持續滾大
- 不變更 `TradeGood` model 本身的去留（若它仍被其他既有功能使用，本次不動它；若調查後發現它已是純死代碼，留給掃描後的下一個 change 決定是否清除）

## Decisions

1. **欄位命名比照 BOM 模組的做法：直接替換，不做相容層**。`trade_good_id` → `sales_product_id`，`tradeGood` 關聯呼叫 → `salesProduct`。前端 request payload 同步改名。
   - 替代方案：在 `ShipmentLine` model 補一個 `tradeGood()` 當作 `salesProduct()` 的別名關聯，讓舊程式碼不用全部改——放棄，這只是把技術債往後推，且與前一個 change 處理 `BomLineImportService` 的方式不一致，會讓兩個模組的修復風格不統一。

2. **`buyer_product_id`/`buyerProduct` 直接整個移除，不轉成 `sales_product_id` 的第二個欄位**。檢查 `ShipmentLine` 的 fillable 與資料表欄位，本來就只有單一 `sales_product_id`，沒有「主商品+子商品」雙欄位的設計（不像 `ProductBomLine` 有 `sales_product_id`/`child_sales_product_id` 兩個）。因此 `buyer_product_id` 在這裡單純是重構時沒清掉的廢欄位，直接刪除驗證規則與關聯呼叫即可。
   - 替代方案：保留 `buyer_product_id` 並改成 `child_sales_product_id`——放棄，因為 `shipment_lines` 表本身沒有這個欄位（已用 `DESCRIBE shipment_lines` 確認只有 `sales_product_id`），保留只會繼續累積無效程式碼。

3. **前端 response 欄位名（`trade_good_name`/`trade_good_code`）是否改名，留到實作時依「最小破壞」原則決定**。優先只改 request payload 的 `trade_good_id`→`sales_product_id`（這是造成功能壞掉的必要修正）；response 顯示欄位若改名需要同步改前端 View 的綁定欄位，風險與範圍較大，實作時先確認 `ShipmentsView.vue`/`ShipmentDetailView.vue` 有多少處綁定後再決定是否一次改完或留待下次。

## Risks / Trade-offs

- [風險] `ShipmentSeeder` 若 `TradeGood` model/表已不存在，種子資料會直接報錯，可能阻塞其他開發者的本地環境初始化 → 緩解：實作時第一步就確認 `TradeGood` 現況，若已不可用則同步修正 Seeder 改用 `SalesProduct::where('is_eudr_applicable', true)`
- [風險] 前端 response 欄位名暫不改可能造成命名不一致（後端內部用 sales_product，API 回應卻仍是 trade_good_name）→ 可接受，因為這是 API 契約變更，需要前後端協調，不應該在修 bug 的同一個 change 裡順手做掉
- [Trade-off] 比照前一個 change 的「先修，重建驗證，再列任務」流程，會再次需要 docker rebuild/restart 來驗證——已知這個專案的 docker 同步流程（CLAUDE.md 已有明確記載），照既有流程操作即可，不是新風險

## Migration Plan

1. 確認 `TradeGood` model 與 `trade_goods` 表現況（是否還存在、是否還被其他模組正常使用），再決定 Seeder 的修正方式
2. 依序修正 `ShipmentLineController`（驗證規則+關聯呼叫）、`ShipmentController`（eager load+組裝回應）、`ShipmentService`（建立/更新邏輯）
3. 修正 `ShipmentSeeder`
4. 修正前端 `shipment.ts` 的 request payload 欄位名
5. 跑過先前 change 已驗證有效的流程：`docker cp` 同步、`docker restart`、以 admin 帳號實測 `GET /api/v1/shipments`、新增一筆 shipment line、確認 PCF 加權計算與 EUDR DDS 草稿生成不報錯
6. 全專案掃描 `tradeGood|trade_good_id|TradeGood|buyerProduct|buyer_product_id|BuyerProduct`，確認沒有第三個模組漏改；若有，記錄下來但不在本次處理
7. 無需資料庫遷移（欄位早已是 `sales_product_id`，純粹是程式碼沒跟上），無需 rollback 腳本

## Open Questions

- ~~`TradeGood` model 目前是否還有任何存活用途~~ —— **已確認且修正先前的誤判**：`app/Models/TradeGood.php` 是 `class TradeGood extends SalesProduct`，標記 `@deprecated 使用 SalesProduct 取代`，是刻意保留、目前正常運作的向後相容別名（繼承 `SalesProduct` 的 `$table = 'sales_products'`，`TradeGood::count()` 實測正常回傳）。先前以為這代表 8 個檔案（`AppServiceProvider`、`PortalTradeGoodController`、`TradeGoodMarketComplianceController`、`TradeGoodController`、`TradeGoodObserver`、`TradeGoodService`、`MarketComplianceChecker`、`DashboardService`）都壞掉是錯誤推論——這些檔案透過 `TradeGood` class 操作的其實是 `sales_products` 表，不會出錯。**不需要為此另開 change**。
  - 但這不影響本次 Shipment 修復的必要性：問題從來不是「`TradeGood` model 能不能用」，而是 `ShipmentLine` model 沒有名為 `tradeGood()` 的關聯方法（只有 `salesProduct()`），`load('tradeGood')` 是依關聯方法名稱查找，找不到就直接報 `RelationNotFoundException`，與 `TradeGood` class 本身是否可用無關
  - `'trade_good_id' => ['exists:trade_goods,id']` 這條驗證規則也與 `TradeGood` model 無關——Laravel 的 `exists` 規則是直接對字串 `trade_goods` 這個資料表名稱查詢，不經過 Eloquent model，而 `trade_goods` 表確實已被刪除，所以驗證階段一樣會炸
  - `BuyerProduct` model 與 `TradeGood` 不同，它**不是** `SalesProduct` 的別名，而是獨立的 Model class，指向已刪除的 `buyer_products` 表——這是在 `fix-erp-material-bom-sync-integrity` change 已確認並修復過的真實 bug，本次 `ShipmentLineController` 裡的 `buyer_product_id`/`buyerProduct` 殘留引用同樣是真的壞掉，移除是正確的
- 前端 response 欄位是否改名（`trade_good_name`→`sales_product_name`），待實作時看影響範圍決定是否本次一併做
