## ADDED Requirements

### Requirement: SASB 必調題目資料表
`sasb_required_topics` 表儲存各 SASB 產業代碼對應的 ESG L3 tag_slug 必調清單。

#### Scenario: 新增必調 topic
- **WHEN** POST /api/v1/settings/sasb-required-topics，傳入 `{ sasb_industry_code, tag_slug, rationale? }`
- **THEN** 建立記錄；若 `(sasb_industry_code, tag_slug)` 已存在則回傳 422

#### Scenario: 刪除必調 topic
- **WHEN** DELETE /api/v1/settings/sasb-required-topics/{id}
- **THEN** 刪除記錄；不影響已發送 SAQ 的 `is_sasb_required` 欄位（快照語意）

#### Scenario: 取得所有 SASB 必調設定
- **WHEN** GET /api/v1/settings/sasb-required-topics
- **THEN** 回傳按 `sasb_industry_code` 分組的必調 topic 清單，含 `tag_slug`、`label_zh`（join question_tags）、`rationale`

### Requirement: SASB 必調標記注入 project_questions
當 SAQ 發送給供應商時，根據供應商的 `sasb_industry_code` 標記對應題目。

#### Scenario: 發送時注入必調標記
- **WHEN** POST /api/v1/saq-projects/{id}/send，供應商有 `sasb_industry_code`
- **THEN** 對該供應商的 `project_questions`，凡 tag_slug 在 `sasb_required_topics[sasb_industry_code]` 中者，設 `is_sasb_required = true`
- **AND** 其餘題目 `is_sasb_required = false`

#### Scenario: 供應商無 SASB 代碼
- **WHEN** 發送對象的供應商 `sasb_industry_code IS NULL`
- **THEN** 所有題目 `is_sasb_required = false`，不標記

### Requirement: Portal 填答頁顯示 SASB 必調標記
供應商填答時，必調題目顯示視覺標記。

#### Scenario: 顯示 SASB 必調標籤
- **WHEN** 供應商在 Portal 填答 SAQ
- **THEN** `is_sasb_required = true` 的題目旁顯示「SASB 必調」標籤（amber 色系）

### Requirement: 前端 SASB 必調設定管理 UI
`ScoringModelView.vue` 區塊 2 顯示並管理各 SASB 產業的必調 topic 清單。

#### Scenario: 列表顯示
- **WHEN** 進入計分模型管理頁，捲動至 SASB 必調設定區塊
- **THEN** 以表格顯示各 SASB 代碼、產業名稱、必調 topic 數，可展開查看 topic 詳情

#### Scenario: 新增必調 topic
- **WHEN** 使用者選擇 SASB 代碼與 ESG tag_slug 後點擊新增
- **THEN** POST API，成功後更新列表

#### Scenario: 刪除必調 topic
- **WHEN** 使用者點擊某 topic 的刪除按鈕
- **THEN** DELETE API，成功後移除該列
