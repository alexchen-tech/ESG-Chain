## 1. 資料庫

- [x] 1.1 Migration：`batch_export_reviews`（batch FK、market、status、findings JSON、reviewed_at，unique(batch,market)）
- [x] 1.2 Seed：`system_settings.export_api_key`

## 2. 審查引擎與 API（esgchain-api）

- [x] 2.1 `BatchExportReview` model + `BatchExportReviewService::review(batch, market)`（文件規則/EUDR 溯源/UFLPA/PCF/DPP 完備度 → status + findings）
- [x] 2.2 內部端點：GET/POST/DELETE `production-batches/{id}/export-reviews`
- [x] 2.3 `ApiKeyMiddleware` + 對外端點 `GET /api/v1/export/batch-package/{erp_batch_no}`（批次護照 JSON）

## 3. 前端（esgchain-web）

- [x] 3.1 批號 Drawer「出口市場審查」區塊：市場選擇＋執行審查＋審查紀錄（狀態徽章＋findings）

## 4. Demo 與驗證

- [x] 4.1 Seeder：EU/US 審查 demo（含 pass/fail 案例）
- [x] 4.2 驗證：內部審查端點、外部 API Key 端點（401/404/200）、UI
