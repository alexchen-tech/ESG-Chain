## MODIFIED Requirements

### Requirement: SAQ 狀態擴充（申訴流程）

系統 SHALL 在現有 SAQ 狀態機中新增 `disputed`、`re_review`、`finalized` 三個狀態。

#### Scenario: 完整狀態列表（更新後）
WHEN 查詢或顯示 SAQ 狀態
THEN 系統 SHALL 支援以下所有狀態值：`sent`、`in_progress`、`submitted`、`under_review`、`review_returned`、`completed`、`reviewed`、`disputed`、`re_review`、`finalized`

#### Scenario: 申訴相關狀態轉換
WHEN 執行申訴相關操作
THEN 合法轉換 SHALL 為：
- `completed` --[dispute]--> `disputed`（供應商，7 天窗口期內）
- `disputed` --[re_review]--> `re_review`（審核員）
- `re_review` --[finalize]--> `finalized`（審核員，終態）

#### Scenario: finalized 為終態
WHEN SAQ 狀態為 `finalized`
THEN `QuestionnaireService::TRANSITIONS['finalized']` SHALL 為空陣列，任何狀態轉換操作 SHALL 回傳 422
