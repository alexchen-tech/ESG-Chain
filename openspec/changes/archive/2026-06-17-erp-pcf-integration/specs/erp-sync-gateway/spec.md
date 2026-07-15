## ADDED Requirements

### Requirement: ERP 同步欄位歸屬保護
系統 SHALL 將每個可 ERP 同步的 entity 欄位分為「ERP 擁有」與「ESG-Chain 擁有」兩類，upsert 時只更新 ERP 擁有欄位，ESG-Chain 擁有欄位永不被同步覆蓋。

ERP 擁有欄位（可覆蓋）：`code`、`name`、`country_code`、`hs_code`、`quantity`、`unit_price`、`supplier_code`
ESG-Chain 擁有欄位（永不覆蓋）：`onboarding_stage`、`saq_score`、`risk_level`、`emission_factor`、`applicable_regulations`、`notes`、`material_group_source = 'manual'`

#### Scenario: ERP 同步時 ESG 標注不被覆蓋
- **WHEN** ERP 同步傳入某供應商更新的 name，但 ESG-Chain 已手動設定 onboarding_stage = certified
- **THEN** 系統 SHALL 更新 name，onboarding_stage 保持 certified 不變

#### Scenario: ERP 同步時 manual 物料群組標注不被覆蓋
- **WHEN** ERP BOM 匯入同一 erp_line_id，但該行的 material_group_source = manual（ESG 團隊手動設定）
- **THEN** 系統 SHALL 更新 quantity / hs_code 等 ERP 欄位，material_group 保持 ESG 手動設定值

### Requirement: ERP 同步來源記錄
系統 SHALL 在 `product_bom_lines` 記錄 `erp_sync_source`（`csv` / `webhook` / `scheduled` / `manual`）與 `erp_synced_at`（最後同步時間），以便稽核追蹤。

#### Scenario: CSV 匯入記錄來源
- **WHEN** 透過 CSV 上傳匯入 BOM 行
- **THEN** 系統 SHALL 設定 `erp_sync_source = 'csv'`，`erp_synced_at = 匯入時間`

#### Scenario: Webhook 匯入記錄來源
- **WHEN** 透過 Webhook 推送匯入 BOM 行
- **THEN** 系統 SHALL 設定 `erp_sync_source = 'webhook'`，`erp_synced_at = 推送時間`

### Requirement: ERP Webhook 接收端點
系統 SHALL 提供 `POST /api/v1/erp/webhook/{entity}` 端點（entity: `suppliers` / `materials` / `bom` / `shipments`），接受 ERP 推送的 JSON payload，驗證 HMAC-SHA256 signature（header: `X-ERP-Signature`），執行正規化 upsert。

#### Scenario: Webhook HMAC 驗證成功
- **WHEN** 請求包含有效 `X-ERP-Signature` header（以系統設定的 shared secret 計算）
- **THEN** 系統 SHALL 接受 payload 並執行 upsert，回傳 `202 Accepted`

#### Scenario: Webhook HMAC 驗證失敗
- **WHEN** 請求的 `X-ERP-Signature` 不符
- **THEN** 系統 SHALL 拒絕請求，回傳 `401 Unauthorized`，不執行任何資料變更

#### Scenario: Webhook 冪等性
- **WHEN** 相同 payload 重複推送（同一 erp_line_id / supplier_code）
- **THEN** 系統 SHALL 執行 upsert（不重複建立），回傳相同的 `202 Accepted`

### Requirement: 排程拉取介面抽象
系統 SHALL 定義 `ErpAdapterInterface`，包含 `fetchSuppliers(since: Carbon)`、`fetchMaterials(since: Carbon)`、`fetchBomLines(productCode: string)`、`fetchShipments(since: Carbon)` 四個方法，由各 ERP 廠商實作具體 Adapter。

#### Scenario: 排程拉取執行
- **WHEN** 排程任務觸發（預設每小時）
- **THEN** 系統 SHALL 呼叫已設定的 Adapter，取得 `since = last_synced_at` 之後的增量資料，執行正規化 upsert
