## Why

標籤庫（`/settings/tag-library`）與計分模型（`/settings/scoring-models`，內含框架預設加權與 SASB 必調題目設定）目前是 Settings 下兩個互不相連的獨立頁面，但兩者透過 `SasbRequiredTopic.tag_slug` 已有隱性資料關聯。維護人員需要在兩個頁面間切換才能確認「某個標籤是否被某產業列為 SASB 必調」，容易漏改或對不上。將兩者整合進同一個功能選項，可把這條隱性關聯變成頁面內可視化對照，降低設定錯誤風險。

## What Changes

- 在 Settings 選單新增一個整合功能項「分類與計分管理」，取代原本「標籤庫」「計分模型」兩個獨立選單項
- 新增一個容器頁面，以 Tabs 呈現三塊既有功能，不重寫其內部邏輯：
  - Tab 1：標籤庫樹狀編輯（沿用 `TagLibraryView` 既有 CRUD/tree/deprecate-restore 邏輯）
  - Tab 2：框架預設 Pillar 加權（沿用既有 `FrameworkDefaultWeightController` 對應前端邏輯）
  - Tab 3：SASB 必調題目設定（沿用既有 `SasbRequiredTopicController` 對應前端邏輯），在此 Tab 內標籤顯示時附帶「是否存在於標籤庫」的可視化標記/連結
- 移除/合併原 `/settings/tag-library` 與 `/settings/scoring-models` 兩個獨立路由的選單入口（路由本身可保留以相容舊連結，但選單只露出新入口）
- 不更動 Laravel/FastAPI 任何既有 API 路由、Controller、Service 或資料表結構

## Capabilities

### New Capabilities
- `settings-classification-scoring-hub`: Settings 下「分類與計分管理」整合頁面，以 Tabs 組織標籤庫、框架預設加權、SASB 必調題目三個既有功能，並在 SASB 必調題目 Tab 內提供標籤庫對照可視化

### Modified Capabilities
（無，本變更僅重組前端頁面/選單入口，不變更 `question-tag-library`、`framework-default-weights`、`sasb-required-topics` 既有規格的行為需求）

## Impact

- 前端：`esgchain-web/src/router/`（選單與路由設定）、新增容器頁面（暫名 `ClassificationScoringHubView.vue`），`SettingsView.vue` 選單項調整
- 後端：無變更（API、Model、資料表皆不變）
- 受影響使用者：admin 角色（唯一可存取 settings 的角色）
