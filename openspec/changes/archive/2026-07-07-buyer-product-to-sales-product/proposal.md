## Why

目前系統存在兩個語意重疊的實體：

- **BuyerProduct**（採購品）：實際存的是品牌商自製的**成品**，有 BOM、PCF 快照、法規推算
- **TradeGood**（出口商品）：有 HS Code、CBAM、客戶、出口申報，是真正的「銷售品」

兩者透過 `buyer_product_trade_goods` join table 連接，PCF 算完還要透過 `ExportLinkSyncService` push 到 TradeGood，形成雙向資料冗餘。從 ERP 視角看，品牌商匯入的是**銷售產品主檔**，ESG-Chain 應只在此實體上附加永續情報，不應另立一個「採購品」容器。

## What Changes

廢棄 `BuyerProduct` 及相關表，將 TradeGood 升級為統一的 **SalesProduct（銷售產品）**，同時繼承 BOM、PCF 計算能力。

- **廢棄**：`buyer_products`、`buyer_product_trade_goods`、`buyer_product_suppliers` 三張表
- **升級** `trade_goods` → `sales_products`：加入 `applicable_regulations`、`inferred_regulations` 欄位
- **擴充** `product_bom_lines`：新增 `child_sales_product_id`，允許 BomLine 指向另一個 SalesProduct（作為子產品組件），與 `material_item_id` 互斥
- **PCF 計算**：直接在 SalesProduct 上讀寫，移除 ExportLinkSyncService 中介層
- **UI**：移除「採購品合規」頁面，出口商品合規頁擴充 BOM 面板與 PCF 顯示

## Capabilities

### New Capabilities

- **BomLine 子產品型態**：一條 BOM 行可指向另一個 SalesProduct，PCF 計算自動引用子產品的最新快照碳強度
- **循環參照保護**：Service 層建立子產品 BomLine 前遞迴檢查，防止 A→B→A 形成閉環

### Modified Capabilities

- **銷售產品（SalesProduct）**：原 TradeGood，新增 BOM 管理、PCF 計算、法規推算能力
- **PCF 計算路徑**：`BuyerProduct → BomLines → PcfSnapshot` 改為 `SalesProduct → BomLines → PcfSnapshot`，移除 ExportLinkSyncService push 步驟
- **出口申報**：ShipmentLine 移除 `buyer_product_id`，統一以 `sales_product_id` 關聯

### Removed Capabilities

- **採購品合規頁面**（BuyerProductsView）：整個廢棄，功能合併至銷售產品頁面
- **ExportLinkSyncService**：廢棄，PCF 直接寫在 SalesProduct
- **BuyerProductSupplier**：廢棄，TradeGoodSupplier（改名 SalesProductSupplier）已足夠

## Scope

### In Scope

- 資料庫 migration：表改名、FK 重指、廢棄表移除、`component` 關係轉換為 BomLine 型態 B
- 後端：Model 改名、Controller 重構、PCF/BomLine Service 更新、路由調整
- 前端：廢棄 BuyerProductsView、擴充 TradeGoodsView（改名 SalesProductsView）、Sidebar 調整
- CLAUDE.md：更新欄位歸屬表（`applicable_regulations` 屬 ESG-Chain）

### Out of Scope

- ERP sync 實際觸發機制（Webhook/CSV）——本次只定義欄位歸屬，不實作 sync pipeline
- 多層 BOM 的 UI 視覺化（樹狀展開）——BomLine 型態 B 資料層建立，UI 僅顯示一層深度

## Migration Notes

現有 8 筆 BuyerProduct 中：
- 有 `finished_good` 連結（4 筆）：`applicable_regulations`、`inferred_regulations`、BomLines、PcfSnapshots 遷移至對應 TradeGood
- 無連結（4 筆）：在 `sales_products` 建立新記錄（從 BuyerProduct 資料新建，`item_code` 留空待 ERP sync）

現有 8 筆 `component` 關係：轉換為 BomLine 型態 B（`child_sales_product_id` 填入原 component TradeGood id，`quantity` 為 NULL 待人工補填）
