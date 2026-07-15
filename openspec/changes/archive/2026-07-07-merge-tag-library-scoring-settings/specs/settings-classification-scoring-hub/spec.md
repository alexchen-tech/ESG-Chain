## ADDED Requirements

### Requirement: 整合功能選項入口
系統 SHALL 在 Settings 選單提供單一功能項「分類與計分管理」，導向一個容器頁面，該頁面以 Tabs 組織「標籤庫」「框架預設加權」「SASB 必調題目設定」三個既有功能。

#### Scenario: admin 從 Settings 選單進入整合頁面
- **WHEN** admin 角色使用者在 Settings 選單點擊「分類與計分管理」
- **THEN** 系統導向 `/settings/classification-scoring`，並預設顯示「標籤庫」分頁

#### Scenario: 非 admin 角色無法存取
- **WHEN** 非 admin 角色使用者嘗試直接訪問 `/settings/classification-scoring`
- **THEN** 系統依既有路由權限規則阻擋存取（與目前 `/settings/tag-library`、`/settings/scoring-models` 的權限規則一致）

### Requirement: 分頁切換不遺失既有功能行為
容器頁面內的三個分頁 SHALL 各自保留其原有畫面（標籤庫樹狀編輯、框架預設 Pillar 加權、SASB 必調題目設定）的既有 CRUD 與互動行為，不因抽出為子元件而改變使用者可觀察到的行為。

#### Scenario: 在標籤庫分頁新增/停用/還原標籤
- **WHEN** 使用者在「標籤庫」分頁執行新增標籤、停用（deprecate）或還原（restore）操作
- **THEN** 系統呼叫既有 `/api/v1/settings/tag-library` 相關端點，行為與原獨立頁面一致

#### Scenario: 在框架預設加權分頁調整權重
- **WHEN** 使用者在「框架預設加權」分頁調整 E/S/G 權重並儲存
- **THEN** 系統呼叫既有 `/api/v1/settings/framework-default-weights` 端點，行為與原獨立頁面一致

#### Scenario: 在 SASB 必調題目分頁新增必調項目
- **WHEN** 使用者在「SASB 必調題目設定」分頁新增一筆 SASB 產業代碼對應 tag_slug 的必調項目並儲存
- **THEN** 系統呼叫既有 `/api/v1/settings/sasb-required-topics` 端點，行為與原獨立頁面一致

### Requirement: SASB 必調題目與標籤庫的可視化對照
在「SASB 必調題目設定」分頁中，系統 SHALL 將每筆必調項目的 `tag_slug` 與標籤庫資料比對，並標示對照結果，使設定人員無需切換頁面即可確認 slug 有效性。

#### Scenario: tag_slug 存在於標籤庫且未停用
- **WHEN** 必調項目的 `tag_slug` 對應到標籤庫中一個未停用（非 deprecated）的標籤
- **THEN** 系統在該筆必調項目旁顯示對應標籤的名稱/路徑，標示為有效

#### Scenario: tag_slug 對應到已停用的標籤
- **WHEN** 必調項目的 `tag_slug` 對應到標籤庫中一個已停用（deprecated）的標籤
- **THEN** 系統顯示警示標記，提示該必調項目參照了已停用的標籤

#### Scenario: tag_slug 在標籤庫中找不到對應項
- **WHEN** 必調項目的 `tag_slug` 在目前標籤庫資料中找不到任何匹配的標籤
- **THEN** 系統顯示警示標記，提示該 slug 在標籤庫中不存在
