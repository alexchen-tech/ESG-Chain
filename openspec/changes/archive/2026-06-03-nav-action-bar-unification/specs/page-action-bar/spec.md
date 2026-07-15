## ADDED Requirements

### Requirement: Global Page Action Bar Class

**What**: `components.css` 新增 `.page-action-bar` class，定義頁面主要行動按鈕列的版面規則。

**Behavior**:
- `display: flex`
- `justify-content: flex-end`
- `gap: 8px`
- `margin-bottom: 12px`

**Usage Rule**: 非 detail 頁面的主要 CTA 按鈕（新增、匯入、匯出）一律放在 `<div class="page-action-bar">` 中，位於 `page-header` 之後、`filter-bar` 之前。

### Requirement: Page Header Button-Free

**What**: `page-header` 只放標題區塊（title + subtitle），不放行動按鈕。

**Behavior**: 所有頂層列表頁的 `page-header` 右側不再有按鈕元素。`justify-content: space-between` 維持，但右側為空。

**Scope**:
- SuppliersView
- SaqProjectsView
- SeriesListView
- BuyerProductsView
- CAPView
- ReportsView
- MaterialItemsView（已有 tab-action-bar，改用全局 class）
- QuestionBankView（同上）
- ScoringModelView（同上）
