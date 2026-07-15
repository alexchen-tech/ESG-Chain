## Why

目前 AppSidebar 固定佔用 220px 寬度，在小螢幕（筆電 1280px 以下）或分割視窗使用時，主內容區域被壓縮，導致表格、卡片顯示不完整。使用者截圖也顯示側邊欄常駐於畫面左側，但沒有收合機制。

需要加入漢堡盒（Hamburger）控制，讓使用者可以：
1. 點擊漢堡按鈕收合 / 展開側邊欄
2. 收合後主內容區域自動補滿寬度
3. 手機版（< 768px）預設收合，點擊選單項目後自動關閉

## What Changes

- `AppSidebar.vue`：加入收合狀態（`collapsed`），收合時只顯示 icon（寬度縮為 56px）
- `App.vue`：根據 sidebar 狀態調整 `main` 的 `margin-left`
- 漢堡按鈕：放在 Sidebar 頂部，點擊切換收合/展開
- Pinia store（`ui.ts`）：持久化 sidebar collapsed 狀態（localStorage）
- Sidebar 收合時 tooltip 顯示選單項目名稱（hover 提示）
- 響應式：視窗寬度 < 768px 時 sidebar 以 overlay 模式呈現（浮在主內容上，點擊外側關閉）

## Success Criteria

- [ ] 桌機版（≥ 768px）：點擊漢堡按鈕可切換 220px ↔ 56px，icon 保持可點擊
- [ ] 收合狀態持久化：重新整理後保持收合/展開狀態
- [ ] 手機版（< 768px）：側邊欄以 overlay 呈現，點擊外側遮罩關閉
- [ ] 收合時 hover 顯示 tooltip（選單項目名稱）
- [ ] 動畫流暢（CSS transition，不卡頓）
