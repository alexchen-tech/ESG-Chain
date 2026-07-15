## ADDED Requirements

### Requirement: Risk level CSS variables in :root
`main.css` 的 `:root` SHALL 定義 10 個 risk level 色彩變數，供全平台統一使用。

變數命名規則：`--risk-<level>-bg`（背景色）、`--risk-<level>-color`（文字色），共 5 個等級：`extreme`、`high`、`medium`、`low`、`very-low`。

#### Scenario: Risk variables accessible globally
- **WHEN** 任何 Vue 元件需要使用 risk level 顏色
- **THEN** 可透過 `var(--risk-extreme-bg)` 等 CSS 變數取得，無需在局部重定義

#### Scenario: Consistent values across components
- **WHEN** RiskMatrixView、SupplierDetailView、DashboardComplianceRisk 同時顯示 risk 顏色
- **THEN** 三者呈現完全相同的色值（來自同一 CSS 變數）

---

### Requirement: Risk level utility classes in components.css
`components.css` SHALL 定義 `.risk-extreme`、`.risk-high`、`.risk-medium`、`.risk-low`、`.risk-very-low` 五個 class，各自設定 `background` 與 `color`，使用對應的 CSS 變數。

#### Scenario: Class applied to matrix cell
- **WHEN** RiskMatrixView 的格子元素加上 `.risk-extreme`
- **THEN** 顯示對應背景色與文字色，不需 scoped style

#### Scenario: Class applied to risk bar
- **WHEN** SupplierDetailView 的 risk bar 使用 `.risk-high`
- **THEN** 顯示正確顏色，局部 `.risk-bar.high { background: #f97316 }` 可被移除

---

### Requirement: Compliance regulation tag classes in components.css
`components.css` SHALL 定義以下全局 tag class，用於法規標注：
`.tag-cbam`、`.tag-eudr`、`.tag-reach`、`.tag-uflpa`、`.tag-cmrt`、`.tag-espr`

每個 class 設定對應的 `background`、`color`、`font-size`、`padding`、`border-radius`。

#### Scenario: Tag used outside TradeGoodsView
- **WHEN** BuyerProductsView 或其他頁面需要顯示法規 tag
- **THEN** 可直接使用 `.tag-cbam` 等全局 class，無需複製樣式

#### Scenario: TradeGoodsView removes local tag definitions
- **WHEN** TradeGoodsView 的 scoped style 移除局部 `.tag-*` 定義
- **THEN** 畫面顯示與移除前完全一致（由全局 class 提供樣式）

---

### Requirement: Status dot classes in components.css
`components.css` SHALL 定義供應商合規狀態圓點 class：
`.status-dot`（基底）、`.status-dot--valid`、`.status-dot--expiring-soon`、`.status-dot--expired`、`.status-dot--missing`

#### Scenario: Status dot displays correct color
- **WHEN** 元件套用 `.status-dot.status-dot--expired`
- **THEN** 顯示紅色圓點，與現有 TradeGoodsView 畫面一致

---

### Requirement: Duplicate badge definitions removed from View scoped styles
所有 Vue View 的 `<style scoped>` SHALL 不包含 `.badge-purple`、`.badge-orange`、`.badge-blue`、`.badge-gray`、`.badge-green` 等已在 `components.css` 定義的 class 之重複定義。

#### Scenario: ShipmentsView badge color corrected
- **WHEN** ShipmentsView 移除局部 `.badge-orange` 定義
- **THEN** badge 使用 components.css 的統一色值 `#ffedd5 / #9a3412`（修正原本偏差的 `#fff7ed / #ea580c`）

#### Scenario: No local override survives audit
- **WHEN** 執行 CSS 統一後對所有 View 掃描 `.badge-purple` 等 class
- **THEN** scoped style 中不再出現任何重複定義

---

### Requirement: Global layout classes not overridden locally
View 的 scoped style SHALL 不重新定義 `.page-container`、`.page-header`、`.page-title`、`.page-subtitle`、`.breadcrumb`、`.data-table`、`.modal`、`.btn-primary`、`.btn-secondary` 等已在 components.css 存在的 class。

#### Scenario: ShipmentsView layout class removed
- **WHEN** ShipmentsView 移除局部 `.page-container`、`.breadcrumb` 定義
- **THEN** 頁面 layout 由全局 components.css 控制，視覺一致性提升
