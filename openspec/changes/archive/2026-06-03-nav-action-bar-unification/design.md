## Context

目前 `components.css` 沒有 `.page-action-bar`，各設定子頁在 `<style scoped>` 各自定義 `.tab-action-bar`。頂層頁面（SuppliersView 等）的按鈕直接放在 `page-header` 的 flex 容器內（`justify-content: space-between`）。

統一後的結構為：

```
page-container
  ├── [breadcrumb]          ← 子頁才有
  ├── page-header           ← 只放 title + subtitle，不含按鈕
  └── page-action-bar       ← 主要 CTA 按鈕統一放這裡
      └── [filter-bar]      ← 搜尋篩選列（維持現有位置）
```

## Goals / Non-Goals

**Goals:**
- 所有非 detail 頁面：主要行動按鈕移至 `page-action-bar`
- `page-action-bar` 定義在全局 `components.css`，不再 scoped
- `page-header` 的 `justify-content` 維持 `space-between`，但右側不放按鈕（右側空白）

**Non-Goals:**
- Detail 頁面（SupplierDetailView、SaqProjectDetailView 等）的操作按鈕不調整 — 這類頁面按鈕語義是「對當前記錄操作」，位置邏輯不同
- SettingsView 的 tab 內 `tab-action-bar` 不需改名，因為它在 tab 切換內部使用，層級不同

## Decisions

**全局 class 命名用 `page-action-bar` 而非 `tab-action-bar`**
`tab-action-bar` 命名來自 SettingsView 的 tab 情境，語義不夠通用。全局 class 改名為 `page-action-bar`，SettingsView 內部維持 `tab-action-bar`（scoped）。

**CSS 定義**
```css
/* components.css 新增 */
.page-action-bar {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  margin-bottom: 12px;
}
```

**page-header 右側空白**
移除按鈕後 `page-header` 右側為空，`justify-content: space-between` 維持不變（無副作用，空右側不影響排版）。

## Risks / Trade-offs

- **改動頁面多（9 個）**：每個頁面改動範圍小（移動幾行 HTML + 刪除 scoped style），但需逐一同步 Docker 容器
- **Scoped → Global**：原 scoped 的 `tab-action-bar` 若有頁面忘記刪除舊定義，不影響功能（CSS specificity 相同），只是冗餘
