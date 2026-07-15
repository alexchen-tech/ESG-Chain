## 1. 抽出既有子元件

- [x] 1.1 從 `ScoringModelView.vue` 抽出框架預設加權區塊為 `FrameworkDefaultWeightPanel.vue`，保留原有 props/狀態/API 呼叫邏輯
- [x] 1.2 從 `ScoringModelView.vue` 抽出 SASB 必調題目設定區塊為 `SasbRequiredTopicPanel.vue`，保留原有 props/狀態/API 呼叫邏輯
- [x] 1.3 確認 `ScoringModelView.vue` 抽出後仍可獨立運作（暫時保留作為舊路由 `/settings/scoring-models` 的內容），手動驗證原有編輯/儲存流程未受影響

## 2. 建立整合容器頁面

- [x] 2.1 新增 `ClassificationScoringHubView.vue`，以 Tabs 組裝 `TagLibraryView`（或其核心內容）、`FrameworkDefaultWeightPanel`、`SasbRequiredTopicPanel`
- [x] 2.2 在 `esgchain-web/src/router/index.ts` 新增路由 `/settings/classification-scoring`（name: `classification-scoring`），權限同既有設定為 `roles: ['admin']`
- [x] 2.3 調整 `SettingsView.vue` 選單，將原「標籤庫」「計分模型」兩個入口收斂為單一「分類與計分管理」入口，指向新路由

## 3. SASB 必調題目與標籤庫可視化對照

- [x] 3.1 在 `SasbRequiredTopicPanel.vue` 內，掛載時額外呼叫既有 `GET /api/v1/settings/tag-library`（`include_deprecated=true`，flat list）取得含已停用標籤的完整清單 — 改用 `list()` 而非 `tree()`，因 `tree()` 端點固定排除已停用標籤，無法支援停用狀態比對
- [x] 3.2 實作前端比對邏輯：依每筆必調項目的 `tag_slug` 對照標籤庫，分三種狀態呈現（有效對應 / 對應到已停用標籤 / 找不到對應標籤）
- [x] 3.3 在 UI 上為三種狀態加上對應的標記樣式（如成功圖示、警示圖示），確保警示狀態清晰可辨

## 4. 驗證

- [x] 4.1 以 admin 測試帳號（`admin@esgchain.com` / `demo1234`）登入，走過新整合頁面三個分頁的既有 CRUD 操作，確認行為與重構前一致（以 Playwright 實際驅動瀏覽器驗證三分頁渲染與切換）
- [x] 4.2 驗證 SASB 必調題目分頁的標籤對照功能：分別測試「對應有效標籤」「對應已停用標籤」「找不到對應標籤」三種情境（以實際 API 建立測試資料驗證，過程中發現並修正 3.1 的 `tree()` 端點限制，驗證後已清除測試資料）
- [x] 4.3 確認舊路由 `/settings/tag-library`、`/settings/scoring-models` 仍可直接訪問（相容性保留），但 Settings 選單已不再顯示這兩個入口
- [x] 4.4 確認非 admin 角色仍無法存取新路由 `/settings/classification-scoring`（以 buyer 帳號測試，被導回 dashboard）
