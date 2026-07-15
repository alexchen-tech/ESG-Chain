## Context

`esgchain-web/src/router/index.ts:134-145` 目前各自掛了 `/settings/scoring-models`（`ScoringModelView.vue`）與 `/settings/tag-library`（`TagLibraryView.vue`）兩條獨立路由，兩者在 `SettingsView.vue` 的選單 Tabs 中分別呈現，互不知道彼此存在。`ScoringModelView.vue` 內部依 openspec `framework-default-weights` 與 `sasb-required-topics` 規格，已拆成「框架預設加權」與「SASB 必調題目設定」兩塊。`sasb-required-topics` 的後端資料模型 `SasbRequiredTopic` 用 `tag_slug`（字串，無 FK 約束）參照 `QuestionTag.slug`，因此設定 SASB 必調題目時無法即時看到該 slug 是否存在於標籤庫、是否已停用（deprecated）。

## Goals / Non-Goals

**Goals:**
- 提供單一入口（一個選單項）管理「標籤庫」「框架預設加權」「SASB 必調題目」三件事
- 在 SASB 必調題目設定畫面中，將 `tag_slug` 解析為標籤庫中的實際標籤名稱/路徑，並標示找不到或已停用的 slug
- 完全重用既有三個畫面的元件與既有 API，不新增/修改後端端點

**Non-Goals:**
- 不重新設計標籤庫或計分模型的資料結構、API 或業務邏輯
- 不強制把 `tag_slug` 改為資料庫層 FK 約束（風險與既有遷移成本超出本變更範圍）
- 不處理非 admin 角色的存取（三者本來就只開放 admin）

## Decisions

1. **新增容器頁面 + 既有元件重用**：新增 `ClassificationScoringHubView.vue`，內部以 Tabs 渲染三個既有畫面元件（`TagLibraryView`、其下拆出的 `FrameworkDefaultWeightPanel`、`SasbRequiredTopicPanel`，後兩者目前是 `ScoringModelView.vue` 內的局部區塊，需先抽成可獨立掛載的子元件）。
   - 替代方案：直接在 `ScoringModelView.vue` 裡 `<iframe>` 或路由跳轉嵌入 `TagLibraryView`——放棄，因為會引入額外的狀態同步與樣式隔離問題，遠比抽元件複雜。

2. **路由保留、選單收斂**：`/settings/tag-library`、`/settings/scoring-models` 路由保留（避免破壞既有書籤/外部連結），但 `SettingsView.vue` 選單只露出新的 `/settings/classification-scoring`（對應 `ClassificationScoringHubView`），並可從新頁面內的分頁切換抵達等效功能。
   - 替代方案：直接刪除舊路由——放棄，因為屬於不必要的破壞性變更，且不在使用者要求範圍內。

3. **SASB ↔ 標籤對照採前端 join，不做後端 API**：在 SASB 必調題目 Tab 內，前端同時取得 `GET /api/v1/settings/tag-library`（樹狀標籤清單）與 `GET /api/v1/settings/sasb-required-topics`，於前端用 `tag_slug` 做查表比對，顯示「✓ 對應標籤：xxx」或「⚠ 標籤庫中找不到此 slug」。
   - 替代方案：後端新增一個聯合查詢 API——放棄，因為違反「不更動既有 API」的前提，且資料量小（標籤與必調項目皆為設定型資料，非分頁列表），前端 join 成本可忽略。

## Risks / Trade-offs

- [風險] 抽出 `FrameworkDefaultWeightPanel`/`SasbRequiredTopicPanel` 子元件時可能誤動既有狀態管理（如表單 dirty-check、loading flag），導致行為偏移 → 緩解：抽元件時逐一比對重構前後的 props/emits，並在 `/verify` 流程中針對三個 Tab 各跑一次既有的編輯/儲存流程
- [風險] 前端 join 在標籤庫資料量未來變大時可能效能下降 → 緩解：目前標籤庫為三層樹狀設定資料，量級可控；若未來變大可在此基礎上再決定是否升級為後端 API（非本次範圍）
- [Trade-off] 保留舊路由但選單不可見，會讓「正確入口」與「可達路徑」不一致 → 可接受，因為主要是過渡期相容考量，且僅 admin 角色可達

## Migration Plan

1. 抽出 `FrameworkDefaultWeightPanel.vue`、`SasbRequiredTopicPanel.vue`，從 `ScoringModelView.vue` 取出對應區塊邏輯，確認原頁面行為不變
2. 新增 `ClassificationScoringHubView.vue`，組裝三個 Tab：標籤庫、框架預設加權、SASB 必調題目
3. 新增路由 `/settings/classification-scoring`，調整 `SettingsView.vue` 選單項，指向新頁面
4. 在 SASB 必調題目 Tab 加入標籤對照可視化邏輯
5. 手動驗證三個 Tab 的既有 CRUD 流程與新對照功能（無自動化前端測試套件可用時，依專案慣例以 `/verify` 跑過 admin 帳號操作）
6. 無需資料庫遷移、無需 rollback 腳本（純前端路由/頁面重組）

## Open Questions

- 新選單項的中文標籤定為「分類與計分管理」是否符合產品/UX 期望，或有更貼切的命名？
- 舊路由 `/settings/tag-library`、`/settings/scoring-models` 要保留多久（是否設一個未來移除的時間點）？
