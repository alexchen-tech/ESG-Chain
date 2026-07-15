# Tasks: Frontend P2 — 系統設定頁面

## API 模組

- [x] P2-0.1 建立 `src/api/modules/settings.ts`（templates/groups/sasb 三個子 namespace）

## P2-1 Settings 主框架

- [x] P2-1.1 建立 `src/views/settings/SettingsView.vue`（三 Tab 骨架：問卷範本/供應商分組/SASB）
- [x] P2-1.2 Router 新增 /settings 路由，Sidebar 新增「系統設定」選單項（admin 限定）

## P2-2 問卷範本管理（Tab 1）

- [x] P2-2.1 串接 GET /settings/questionnaire-templates 列表（含 is_active/sasb_industry_id 顯示）
- [x] P2-2.2 新增 Modal（name/version/sasb_industry_id 下拉）+ POST 建立
- [x] P2-2.3 啟用/停用 toggle（PUT is_active）
- [x] P2-2.4 刪除按鈕 + 確認 Dialog（DELETE）

## P2-3 供應商分組管理（Tab 2）

- [x] P2-3.1 串接 GET /settings/supplier-groups 列表
- [x] P2-3.2 建立 / inline 編輯（POST/PUT）
- [x] P2-3.3 刪除確認（DELETE）

## P2-4 SASB 產業分類瀏覽（Tab 3）

- [x] P2-4.1 串接 GET /settings/sasb-industries，按 sector 分組
- [x] P2-4.2 摺疊/展開 UI（點擊 sector 展開 industries 列表）
- [x] P2-4.3 文字搜尋篩選（sector 名稱 + industry 名稱）
