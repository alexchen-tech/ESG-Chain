## ADDED Requirements

### Requirement: PCF 請求資料模型
系統 SHALL 維護 `pcf_requests` 與 `pcf_request_lines` 兩張表。

`pcf_requests` 欄位：`id`（UUID）、`supplier_id`、`period_start`（date）、`period_end`（date）、`due_date`（date）、`status`（`pending` / `submitted` / `verified` / `overdue`）、`saq_round_id`（nullable）、`created_by`、`created_at`、`updated_at`

`pcf_request_lines` 欄位：`id`（UUID）、`pcf_request_id`、`bom_line_id`、`material_name`（快照）、`hs_code`（快照）、`submitted_at`（nullable）、`status`（`pending` / `submitted` / `verified`）

#### Scenario: 資料完整性
- **WHEN** 建立 pcf_request_line
- **THEN** 系統 SHALL 從 bom_line 快照 `material_name` 與 `hs_code`（以 effective 欄位為準），確保歷史記錄不受 BomLine 後續修改影響

### Requirement: 批次發送 PCF 請求
系統 SHALL 提供 `POST /api/v1/pcf-requests/batch` endpoint，採購商可指定多個 `supplier_id` + 對應的 `bom_line_ids` 陣列 + `period_start`/`period_end`/`due_date`，批次建立 PCF 請求。

#### Scenario: 成功批次建立
- **WHEN** 提交包含 3 個供應商各自的 bom_line_ids
- **THEN** 系統 SHALL 建立 3 筆 pcf_requests，各自包含對應的 pcf_request_lines，狀態為 `pending`

#### Scenario: 防止重複發送
- **WHEN** 同一 `supplier_id` + `bom_line_id` + `period_start` 已存在 `pending` 或 `submitted` 狀態的請求
- **THEN** 系統 SHALL 跳過該組合，在 response `skipped` 陣列中說明原因

#### Scenario: 部分失敗不回滾
- **WHEN** 批次中部分供應商的請求建立失敗（如 supplier 不存在）
- **THEN** 系統 SHALL 繼續處理其他供應商，回傳 `{ created: N, skipped: [], errors: [] }`

### Requirement: PCF 請求列表查詢
系統 SHALL 提供 `GET /api/v1/pcf-requests` endpoint，支援以下過濾參數：`supplier_id`、`status`、`period_start`（年份）、`due_before`（日期）。回傳含 supplier 名稱、進度（`submitted_lines / total_lines`）的摘要列表。

#### Scenario: 採購商查詢全部請求
- **WHEN** 採購商呼叫 `GET /api/v1/pcf-requests` 不帶過濾
- **THEN** 系統 SHALL 回傳所有請求，含各請求的 `progress`（已提交行數 / 總行數）

#### Scenario: 依狀態過濾
- **WHEN** 傳入 `?status=pending`
- **THEN** 系統 SHALL 只回傳 status 為 `pending` 的請求

### Requirement: PCF 請求逾期自動更新
系統 SHALL 提供 Artisan 指令 `pcf:update-overdue`，將 `due_date < today` 且 `status = pending` 的 `pcf_requests` 更新為 `overdue`。

#### Scenario: 排程執行
- **WHEN** 指令執行
- **THEN** 系統 SHALL 批次更新所有符合條件的請求，並回傳更新筆數

### Requirement: PCF 請求管理頁面（前端）
`esgchain-web` SHALL 提供 `/compliance/pcf-requests` 頁面（`PcfRequestsView.vue`），角色限 `admin`、`buyer`、`sustain`，功能包含：篩選列表、批次發送表單、各請求進度檢視。

#### Scenario: 列表顯示進度
- **WHEN** 頁面載入
- **THEN** SHALL 顯示每筆請求的：供應商名稱、申報週期、截止日、進度條（submitted/total）、狀態 badge

#### Scenario: 批次發送流程
- **WHEN** 點擊「發送碳排請求」
- **THEN** SHALL 開啟選擇表單：選供應商 → 選 BomLine → 設定週期與截止日 → 確認送出
