## 1. main.css — 新增 Risk Level CSS 變數

- [x] 1.1 在 `:root` 加入 `--risk-extreme-bg: #fecaca`、`--risk-extreme-color: #991b1b`
- [x] 1.2 在 `:root` 加入 `--risk-high-bg: #fed7aa`、`--risk-high-color: #9a3412`
- [x] 1.3 在 `:root` 加入 `--risk-medium-bg: #fef9c3`、`--risk-medium-color: #854d0e`
- [x] 1.4 在 `:root` 加入 `--risk-low-bg: #bbf7d0`、`--risk-low-color: #15803d`
- [x] 1.5 在 `:root` 加入 `--risk-very-low-bg: #dcfce7`、`--risk-very-low-color: #166534`

## 2. components.css — 新增全局 class

- [x] 2.1 新增 `.risk-extreme`、`.risk-high`、`.risk-medium`、`.risk-low`、`.risk-very-low` class（使用 1.x 定義的 CSS 變數）
- [x] 2.2 新增 `.tag-cbam`、`.tag-eudr`、`.tag-reach`、`.tag-uflpa`、`.tag-cmrt`、`.tag-espr` class（從 TradeGoodsView 複製並對齊色值）
- [x] 2.3 新增 `.status-dot` 基底 class 與 `.status-dot--valid`、`.status-dot--expiring-soon`、`.status-dot--expired`、`.status-dot--missing` modifier（從 TradeGoodsView 複製）

## 3. RiskMatrixView.vue — 改用全局 risk class

- [x] 3.1 移除 scoped style 中 `.very_low`、`.low`、`.medium`、`.high`、`.extreme` 的 background/color 定義（改用全局 `.risk-*`）
- [x] 3.2 移除 legend color 的局部定義（`.legend-color.very_low` 等），改用全局 class
- [x] 3.3 確認矩陣格子與圖例顯示顏色與修改前一致

## 4. SupplierDetailView.vue — 移除重複定義

- [x] 4.1 移除局部 `.badge-purple` 定義
- [x] 4.2 將 `.risk-bar.very_low`、`.risk-bar.low`、`.risk-bar.medium`、`.risk-bar.high`、`.risk-bar.extreme` 的 background 色值改用對應 CSS 變數
- [x] 4.3 確認供應商風險評分顯示顏色正確

## 5. TradeGoodsView.vue — 移除局部定義

- [x] 5.1 移除局部 `.page-container`、`.page-header`、`.page-title`、`.page-subtitle` 定義（已有全局版本）
- [x] 5.2 移除局部 `.tag-cbam`、`.tag-eudr`、`.tag-reach`、`.tag-uflpa`、`.tag-cmrt`、`.tag-espr` 定義（已提升至 components.css）
- [x] 5.3 移除局部 `.status-dot`、`.status-dot--*` 定義（已提升至 components.css）
- [x] 5.4 確認 TradeGoods 頁面顯示與修改前完全一致

## 6. ShipmentsView.vue — 修正 badge 色值偏差

- [x] 6.1 移除局部 `.badge-gray`、`.badge-orange`、`.badge-blue` 定義
- [x] 6.2 移除局部 `.page-container`、`.breadcrumb`、`.data-table`、`.modal`、`.btn-primary`、`.btn-secondary` 定義
- [x] 6.3 確認 Shipments 頁面 badge 顏色與其他頁面對齊（orange 從 `#fff7ed` 修正為 `#ffedd5`）

## 7. Badge 重複定義清理（多個 View）

- [x] 7.1 移除 `QuestionnaireView.vue` 局部 `.badge-purple`
- [x] 7.2 移除 `PortalView.vue` 局部 `.badge-purple`
- [x] 7.3 移除 `SupplierSurveyView.vue` 局部 `.badge-purple`（若存在）
- [x] 7.4 確認上述頁面 badge 顯示正常

## 8. 全局驗證

- [x] 8.1 搜尋所有 `.vue` 檔案，確認 scoped style 中不再出現 `.badge-purple`、`.badge-orange`、`.badge-blue`、`.badge-gray` 的重複定義
- [x] 8.2 搜尋所有 `.vue` 檔案，確認 risk level 色值（`#fecaca`、`#fed7aa`、`#fef9c3`、`#bbf7d0`、`#dcfce7`）不再以硬編碼出現
- [ ] 8.3 瀏覽器依序確認：儀表板、供應商列表、TradeGoods、Shipments、RiskMatrix、問卷頁面視覺無明顯差異
