## ADDED Requirements

### Requirement: 題目列表 API
`GET /api/v1/settings/questionnaire-templates/:id/questions` SHALL 回傳指定範本的所有題目，依 order 升序排列。

#### Scenario: 有題目的範本
- **WHEN** 呼叫 GET questions
- **THEN** 回傳 `{ success: true, data: SAQQuestion[] }` 依 order 排序

#### Scenario: 無題目的範本
- **WHEN** 範本無任何題目
- **THEN** 回傳 `{ success: true, data: [] }`

### Requirement: 新增題目 API
`POST /api/v1/settings/questionnaire-templates/:id/questions` SHALL 接受 `question_text(必填) / category(E|S|G) / question_type / options(nullable JSON) / weight(0–1) / is_required(bool) / order(int)`，建立後回傳 201。

#### Scenario: 新增有效題目
- **WHEN** 提交合法的 question_text 與 category
- **THEN** 回傳 201 含新建題目資料

#### Scenario: question_text 為空
- **WHEN** 提交空白 question_text
- **THEN** 回傳 422 驗證錯誤

### Requirement: 更新題目 API
`PUT /api/v1/settings/questionnaire-templates/:id/questions/:questionId` SHALL 接受 `question_text / category / options / weight / is_required / order`（皆為 sometimes）；`question_type` 不可更改（忽略）。

#### Scenario: 更新 order 值（排序用）
- **WHEN** 只提交 `{ order: 3 }`
- **THEN** 回傳 200，只更新 order

### Requirement: 刪除題目 API
`DELETE /api/v1/settings/questionnaire-templates/:id/questions/:questionId` SHALL 刪除指定題目並回傳 200。

#### Scenario: 刪除存在的題目
- **WHEN** DELETE 有效的 questionId
- **THEN** 題目刪除，回傳 `{ success: true, message: '題目已刪除' }`

#### Scenario: 題目不屬於此範本
- **WHEN** questionId 的 template_id 與路由 :id 不符
- **THEN** 回傳 404
