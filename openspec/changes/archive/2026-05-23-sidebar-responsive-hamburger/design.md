## 架構設計

### Pinia UI Store（`src/stores/ui.ts`）

```typescript
export const useUiStore = defineStore('ui', {
  state: () => ({
    sidebarCollapsed: false,
    sidebarMobileOpen: false,
  }),
  actions: {
    toggleSidebar() { this.sidebarCollapsed = !this.sidebarCollapsed },
    openMobileSidebar() { this.sidebarMobileOpen = true },
    closeMobileSidebar() { this.sidebarMobileOpen = false },
  },
  persist: true,  // pinia-plugin-persistedstate → localStorage
})
```

### 寬度狀態機

```
桌機（≥ 768px）
  展開：width = 220px，顯示 icon + label
  收合：width =  56px，只顯示 icon，hover → tooltip

手機（< 768px）
  關閉：width = 0（hidden，不佔空間）
  開啟：overlay，width = 220px，backdrop 遮罩
```

### App.vue layout

```css
/* 主內容 margin 根據 sidebar 狀態切換 */
.main-with-sidebar         { margin-left: 220px; }
.main-with-sidebar.collapsed { margin-left: 56px; }

/* 手機版不推移 */
@media (max-width: 767px) {
  .main-with-sidebar,
  .main-with-sidebar.collapsed { margin-left: 0; }
}
```

### AppSidebar.vue 結構變化

```
sidebar（:class="{ collapsed, mobile-open }）
  ┌── sidebar-top
  │   漢堡按鈕（☰ / ×）
  │   logo（收合時隱藏文字）
  │
  ├── sidebar-menu
  │   menu-item（v-for）
  │     icon（始終顯示）
  │     label（收合時 v-show=false）
  │     tooltip（收合時 data-tooltip，CSS :hover → ::after）
  │
  └── sidebar-footer
      user-info（收合時隱藏）
      logout-btn（收合時只顯示圖示）

backdrop（手機版 overlay 時顯示，點擊 → closeMobileSidebar）
```

### Tooltip 實作（純 CSS，不需套件）

```css
.collapsed .menu-item {
  position: relative;
}
.collapsed .menu-item:hover::after {
  content: attr(data-tooltip);
  position: absolute;
  left: 64px;
  top: 50%;
  transform: translateY(-50%);
  background: #1a1714;
  color: #fff;
  padding: 4px 10px;
  border-radius: 4px;
  font-size: 13px;
  white-space: nowrap;
  z-index: 100;
  pointer-events: none;
}
```

### 手機版漢堡按鈕位置

手機版（< 768px）時，漢堡按鈕移至 **Topbar** 右上角（或左上角），固定在畫面頂部，點擊打開 sidebar overlay。

```
┌──────────────────────────────────┐
│ ☰  ESG·Chain              ...    │  ← Topbar（手機版）
├──────────────────────────────────┤
│         main content             │
```

### CSS Transition

```css
.sidebar {
  transition: width 0.25s cubic-bezier(0.4, 0, 0.2, 1);
  overflow: hidden;
}

.menu-label, .user-info, .logo-text {
  transition: opacity 0.15s ease, transform 0.15s ease;
}

.collapsed .menu-label,
.collapsed .user-info,
.collapsed .logo-text {
  opacity: 0;
  pointer-events: none;
}
```
