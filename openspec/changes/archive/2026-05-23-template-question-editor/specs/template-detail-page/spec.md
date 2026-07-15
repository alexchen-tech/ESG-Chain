## ADDED Requirements

### Requirement: 範本詳情頁路由與麵包屑
系統 SHALL 提供 `/settings/templates/:id` 路由，渲染 `TemplateDetailView.vue`，頁頭顯示麵包屑「系統設定 → 問卷範本 → {範本名稱}」，「系統設定」可點擊返回 `/settings`。

#### Scenario: 進入有效範本詳情頁
- **WHEN** 使用者導覽至 `/settings/templates/{有效UUID}`
- **THEN** 頁面載入範本資訊（name / version / sasb_industry_id / is_active）及題目列表

#### Scenario: 進入不存在的範本
- **WHEN** API 回傳 404
- **THEN** 頁面顯示「找不到此問卷範本」並提供返回按鈕

### Requirement: 範本基本資訊區塊
詳情頁 SHALL 顯示範本名稱、版本（font-mono）、SASB 分類、狀態 badge，並提供「啟用/停用」toggle。

#### Scenario: 顯示範本資訊
- **WHEN** 頁面載入完成
- **THEN** 顯示 name / version / is_active badge；若有 sasb_industry_id 則顯示對應 sector

### Requirement: 題目列表顯示
詳情頁 SHALL 以編號列表顯示所有題目，每題顯示：序號、分類 badge（E/S/G）、題文（截斷超過 60 字）、題型 badge、權重（font-mono）、必填標記、操作按鈕（編輯/↑/↓/刪除）。

#### Scenario: 有題目時顯示列表
- **WHEN** 範本有 N 道題
- **THEN** 依 order 升序顯示，序號從 1 開始

#### Scenario: 無題目時顯示引導
- **WHEN** 範本無任何題目
- **THEN** 顯示「尚無題目，點擊新增第一道題」及新增按鈕

### Requirement: SettingsView 範本列表加入口
`SettingsView.vue` 的問卷範本列表 SHALL 在每行操作欄加入「編輯題目」按鈕，點擊後跳轉至 `/settings/templates/:id`。

#### Scenario: 點擊編輯題目
- **WHEN** admin 點擊某範本列的「編輯題目」按鈕
- **THEN** router.push(`/settings/templates/${template.id}`)
