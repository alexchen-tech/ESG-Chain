# Tasks: Sidebar 漢堡盒響應式

- [x] T1 建立 `src/stores/ui.ts`（sidebarCollapsed / sidebarMobileOpen，localStorage 持久化）
- [x] T2 AppSidebar.vue：加入漢堡按鈕（☰），點擊 toggleSidebar()
- [x] T3 AppSidebar.vue：收合狀態（collapsed class），width 220px → 56px CSS transition
- [x] T4 AppSidebar.vue：收合時隱藏 label / logo 文字（opacity transition）
- [x] T5 AppSidebar.vue：收合時 menu-item 加 data-tooltip，hover 顯示純 CSS tooltip
- [x] T6 AppSidebar.vue：手機版 overlay 模式（backdrop + closeMobileSidebar on click）
- [x] T7 App.vue：main margin-left 根據 collapsed 狀態動態切換（transition 同步）
- [x] T8 App.vue：手機版（< 768px）主內容不推移，sidebar 以 overlay 呈現
- [x] T9 sidebar-footer：收合時只顯示登出 icon，隱藏使用者名稱
