## ADDED Requirements

### Requirement: iso_subject 欄位

`saq_questions` 表 SHALL 有 `iso_subject` nullable 欄位，值為 ISO 26000 七大核心主題之一：`組織治理`、`人權`、`勞工`、`環境`、`公平營運`、`消費者`、`社區`，或 NULL。

#### Scenario: 新增題庫題目時指定 ISO 主題

- **WHEN** POST /question-bank 帶 `iso_subject: "勞工"`
- **THEN** 題目儲存後 `iso_subject = '勞工'`，response 包含此欄位

#### Scenario: iso_subject 為選填

- **WHEN** POST /question-bank 不帶 `iso_subject`
- **THEN** 儲存成功，`iso_subject = NULL`

#### Scenario: iso_subject 不合法值被拒絕

- **WHEN** POST /question-bank 帶 `iso_subject: "氣候變遷"`（非七大主題）
- **THEN** 回傳 422，錯誤訊息說明合法值範圍

### Requirement: tags 欄位清理

現有 tags 中的 `ISO-xxx` 前綴值與 E/S/G 值 SHALL 在 migration 執行後不再出現。`tags` 欄位保留供未來 Phase 2 L3 細項使用，Phase 1 後應為空或僅含 `地域風險`。

#### Scenario: 現有資料遷移

- **WHEN** 執行 migration
- **THEN** tags 中 `"ISO-勞工"` → `iso_subject = '勞工'`，原 tag 移除；`"E"/"S"/"G"` tag 直接移除

### Requirement: API 篩選支援 iso_subject

`GET /api/v1/settings/question-bank` SHALL 支援 `?iso_subject=勞工` 查詢參數，回傳 `iso_subject` 符合指定值的題目。

#### Scenario: 依 iso_subject 篩選

- **WHEN** GET ?iso_subject=人權
- **THEN** 只回傳 iso_subject = '人權' 的題目

### Requirement: QuestionBankFilter ISO 維度

`QuestionBankFilter` 的 ISO 20400 群組 SHALL 改為查詢 `?iso_subject=`（不再查 tag），ESG 群組 SHALL 移除（由 `?category=` 覆蓋）。

### Requirement: 題庫 Modal 改 radio

題庫新增/編輯 Modal 中，ISO 主題選擇 SHALL 改為 radio button（單選），並提供「清除」選項以回到 NULL 狀態。舊的 tag checkbox 群組 SHALL 移除。

#### Scenario: 選擇 ISO 主題

- **WHEN** 使用者點選 radio「勞工」
- **THEN** `iso_subject = '勞工'`，其他 radio 取消選取

#### Scenario: 清除 ISO 主題

- **WHEN** 使用者點選「清除」
- **THEN** `iso_subject = NULL`，所有 radio 取消選取
