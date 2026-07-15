## Why

問卷範本設計功能從系統設定移出後，`/questionnaires/templates` 路由仍指向 SettingsView，造成入口斷裂。使用者點擊「問卷範本設計」後只看到組織架構頁，找不到任何範本。此外 TemplateDetailView 的麵包屑仍指回「系統設定 > 問卷範本」（已移除），且缺少複製範本與封存版本兩個關鍵功能，導致版本管理完全無法操作。

## What Changes

- 新增 `QuestionnaireTemplatesView.vue`（獨立範本列表頁）：列出所有範本（含封存），可新增、啟停用、複製、封存
- `saq_templates` 補 `archived_at` 欄位（nullable timestamp），封存後不可編輯
- 後端補 `clone` API：複製範本全部欄位 + 所有題目，version 自動加 `.copy`
- 後端補 `archive` / `unarchive` API（設定/清除 archived_at）
- `TemplateDetailView.vue`：補基本資訊編輯（名稱/版本/描述/is_active toggle），麵包屑改為 ESG 問卷 › 問卷範本設計
- 路由 `/questionnaires/templates` 改指向 `QuestionnaireTemplatesView.vue`

## Capabilities

### New Capabilities
- `template-list-page`: 獨立範本列表頁（/questionnaires/templates），含狀態篩選（啟用/停用/封存）、複製、封存操作
- `template-clone`: 後端 clone API + 前端「複製」按鈕，複製範本與全部題目
- `template-archive`: 後端 archive/unarchive API + 前端「封存/取消封存」操作，archived 範本不可編輯、不出現在問卷發送清單

### Modified Capabilities
- `template-detail-edit`: TemplateDetailView 補基本資訊編輯區塊（名稱/版本/描述）+ 麵包屑修正
- `template-api`: QuestionnaireTemplateController 補 clone/archive/unarchive endpoints

## Impact

- **DB**：1 個 migration（saq_templates 補 archived_at）
- **後端**：QuestionnaireTemplateController 補 3 個方法 + 路由
- **前端**：1 個新頁面、更新 TemplateDetailView、更新路由
- **無破壞性**：archived_at nullable，現有資料全為 null（視為未封存）
