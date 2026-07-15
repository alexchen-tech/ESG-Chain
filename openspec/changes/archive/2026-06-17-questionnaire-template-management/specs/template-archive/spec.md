## ADDED Requirements

### Requirement: archived_at 欄位
`saq_templates` 表 SHALL 新增 `archived_at` nullable timestamp 欄位，值非 null 代表已封存。

#### Scenario: 現有範本遷移
- **WHEN** 執行 migration
- **THEN** 所有現有範本的 archived_at = NULL（未封存），行為不變

### Requirement: Archive / Unarchive API
`POST /api/v1/settings/questionnaire-templates/:id/archive` SHALL 設定 archived_at=now()（封存），`POST /api/v1/settings/questionnaire-templates/:id/unarchive` SHALL 設定 archived_at=null（取消封存）。

#### Scenario: 封存已啟用的範本
- **WHEN** POST archive 一個 is_active=true 的範本
- **THEN** archived_at 設為現在時間，is_active 自動設為 false，回傳 200

#### Scenario: 取消封存
- **WHEN** POST unarchive
- **THEN** archived_at=null，is_active 保持 false（需手動重新啟用），回傳 200

### Requirement: TemplateDetailView 封存 Banner
當 `template.archived_at IS NOT NULL` 時，TemplateDetailView SHALL 在頁頭下方顯示黃色警告 banner「此範本已於 {date} 封存，無法編輯。」，所有題目操作按鈕（新增/編輯/刪除/排序/從題庫選題）disabled，基本資訊編輯亦 disabled。

#### Scenario: 已封存範本顯示
- **WHEN** 進入已封存範本的 TemplateDetailView
- **THEN** 顯示黃色 banner，所有操作按鈕 disabled

#### Scenario: 未封存範本正常顯示
- **WHEN** 進入未封存範本
- **THEN** 無 banner，所有操作正常可用

### Requirement: 列表頁「API index 過濾封存」
`GET /api/v1/settings/questionnaire-templates` SHALL 支援 `is_archived` 查詢參數（true/false），預設（不帶參數）回傳所有未封存範本（archived_at IS NULL）。

#### Scenario: 取得封存範本
- **WHEN** ?is_archived=true
- **THEN** 只回傳 archived_at IS NOT NULL 的範本

#### Scenario: 預設行為不變
- **WHEN** 不帶 is_archived 參數
- **THEN** 只回傳未封存範本（向後兼容現有前端呼叫）
