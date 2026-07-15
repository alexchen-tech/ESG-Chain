## Why

EUDR、CBAM 等法規的申報粒度是「批次」而非「型號」。現有 ESG·Chain 只有型號層資料（BuyerProduct → TradeGood 連結），缺少生產批號這一層：是哪個工廠、什麼時候生產、原料來自哪個農場。沒有這層資料，EUDR DDS 無法自動產出草稿，碳排申報只能用型號平均值（CBAM 不接受）。

Phase 2a 引入生產批號（ProductionBatch）與原料溯源（RawMaterialOrigin）兩個實體，並建立 ERP Webhook 接入層（Webhook 優先、CSV 備援），讓工廠生產資料能自動流入 ESG·Chain 成為合規申報的事實基礎。

## What Changes

- 新增 `production_batches` 資料表：記錄每個工廠批次的生產事實（批號、工廠、日期、數量、批次 PCF）
- 新增 `raw_material_origins` 資料表：記錄每個批次的原料溯源（農場 GPS、認證號、採收年份）
- 新增 ERP Webhook endpoint：`POST /api/v1/erp/webhook/production-batches`（HMAC-SHA256 驗證）
- 新增 CSV import endpoint：`POST /api/v1/erp/import/production-batches`（備援路徑）
- 新增前端「生產批號」管理頁面：列表、詳情 Drawer、原料溯源編輯

## Capabilities

### New Capabilities

- `production-batch-management`：生產批號管理 — ERP Webhook / CSV 接入，記錄工廠、批號、數量、批次 PCF；含原料溯源（農場 GPS、認證號）子表管理

### Modified Capabilities

- `buyer-product-registry`：BuyerProductTradeGood 透過 `erp_product_code` 橋接到 ProductionBatch（依賴 export-link-erp-bridge 完成）

## Impact

- **資料庫**：新增 2 張表（production_batches、raw_material_origins）
- **後端**：新增 ProductionBatch Model、RawMaterialOrigin Model；新增 ErpWebhookController、ProductionBatchController；新增 ProductionBatchService
- **前端**：新增 `ProductionBatchesView.vue`；新增 sidebar 入口（商品合規管理群組）；新增 `api/modules/productionBatch.ts`
- **安全**：Webhook HMAC-SHA256 驗證，Secret 存 `.env`（`ERP_WEBHOOK_SECRET`）；API Key 備援選項
- **依賴**：需先完成 `export-link-erp-bridge`（`erp_product_code` 橋接欄位）
