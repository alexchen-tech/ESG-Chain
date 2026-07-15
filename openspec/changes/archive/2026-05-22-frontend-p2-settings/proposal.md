# Change Proposal: Frontend P2 — 系統設定頁面

## 前置條件

Frontend P0 + P1 完成後執行。

## 動機

Settings 模組讓 admin 可以管理問卷範本、供應商分組、瀏覽 SASB 分類，而無需直接操作資料庫。這是平台長期營運必要的後台管理功能。

## 範圍

### P2-1 Settings 主頁面 `/settings`

新建 `SettingsView.vue`，採用 Tabs 設計：
- Tab 1：問卷範本管理
- Tab 2：供應商分組管理
- Tab 3：SASB 產業分類

### P2-2 問卷範本管理（Tab 1）

串接 `/settings/questionnaire-templates`：
- 列表（顯示名稱、版本、適用產業、啟用狀態）
- 新增 Modal（name、version、sasb_industry_id）
- 啟用/停用切換（PUT is_active）
- 刪除確認

### P2-3 供應商分組管理（Tab 2）

串接 `/settings/supplier-groups`：
- 列表（名稱 + 供應商數量）
- 建立 / 編輯 inline
- 刪除確認

### P2-4 SASB 產業分類瀏覽（Tab 3）

串接 `/settings/sasb-industries`：
- 唯讀列表
- 按 sector 摺疊展開（11 sectors）
- sector 搜尋篩選
- 顯示 code（TC-ES 等）

### P2-5 API 模組 + Sidebar

- 新增 `api/modules/settings.ts`（templates/groups/sasb）
- Sidebar 新增「系統設定」選單項（admin 限定）

## 成功條件

- [ ] admin 可建立、啟用/停用問卷範本
- [ ] admin 可建立/刪除供應商分組
- [ ] SASB 分類可按 sector 摺疊瀏覽，顯示所有 74 個 industries
