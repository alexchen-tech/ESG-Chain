## ADDED Requirements

### Requirement: scoring_framework 建立後不可修改

系統 SHALL 確保 `saq_templates.scoring_framework` 在範本建立後為唯讀，任何 UPDATE 請求若包含 `scoring_framework` 欄位且與現有值不同，SHALL 回傳 422。

#### Scenario: 嘗試修改 scoring_framework 被拒絕
- **WHEN** Admin PUT `/api/v1/questionnaire-templates/{id}`，帶有不同的 `scoring_framework` 值
- **THEN** 系統 SHALL 回傳 422，message: '範本框架（scoring_framework）建立後不可修改'

#### Scenario: 編輯 Modal 顯示框架為唯讀
- **WHEN** Admin 開啟已建立範本的編輯 Modal
- **THEN** scoring_framework 欄位 SHALL 顯示為 badge（不可點擊），並附帶提示文字「建立後不可修改」

### Requirement: 範本與系列的關聯顯示

系統 SHALL 在範本詳情頁顯示此範本被哪些評核系列使用（series 列表）。

#### Scenario: 範本詳情顯示關聯系列
- **WHEN** Admin 查看 GET `/api/v1/questionnaire-templates/{id}`
- **THEN** response SHALL 含 `series_count` 及 `series` 陣列（id, name, status）

#### Scenario: 被系列使用的範本無法刪除
- **WHEN** Admin 嘗試刪除有 `assessment_series` 關聯的範本
- **THEN** 系統 SHALL 回傳 422，message: '此範本已被 N 個評核系列使用，無法刪除'
