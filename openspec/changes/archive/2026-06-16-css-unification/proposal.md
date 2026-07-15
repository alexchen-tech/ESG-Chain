## Why

CSS 審查發現 44 個 Vue 元件中存在大量重複定義與不一致：6+ 個檔案各自重定義 `.badge-*`（色值有差異）、risk level 顏色分散在 3 處硬編碼、TradeGoods tag 系統局部定義無法複用。統一後可確保主題修改單點生效，消除視覺差異。

## What Changes

- 在 `:root` 加入 risk level CSS 變數（`--risk-*-bg`、`--risk-*-color`）
- 在 `components.css` 加入全局 `.risk-*`、`.tag-cbam`、`.tag-eudr` 等共用 class
- 移除各 View 中重複定義的 `.badge-purple`、`.badge-orange`、`.badge-blue`、`.badge-gray`（以全局版本為準，修正 ShipmentsView 的色值偏差）
- 修正 ShipmentsView 中局部重定義的 `.page-container`、`.breadcrumb`、`.data-table` 等全局 class
- 將 TradeGoodsView 的 `.tag-*`、`.status-dot--*` 提升至 `components.css`
- 消除各 View 中對全局 class 的局部微調（modal、breadcrumb、button 等）

## Capabilities

### New Capabilities
- `css-design-tokens`: `:root` 新增 risk level 色彩變數與全局 tag/status-dot class 定義

### Modified Capabilities
- （無 spec 層行為變化，純實作統一）

## Impact

- `esgchain-web/src/assets/main.css`：新增 CSS 變數
- `esgchain-web/src/assets/components.css`：新增共用 class
- `esgchain-web/src/views/trade-goods/TradeGoodsView.vue`：移除局部重複定義
- `esgchain-web/src/views/compliance/ShipmentsView.vue`：修正 badge 色值、移除局部重定義
- `esgchain-web/src/views/suppliers/SupplierDetailView.vue`：移除局部 `.badge-purple`、`.risk-bar` 色值改用變數
- `esgchain-web/src/views/questionnaires/QuestionnaireView.vue`：移除局部 `.badge-purple`
- `esgchain-web/src/views/portal/PortalView.vue`：移除局部 `.badge-purple`
- `esgchain-web/src/views/risk/RiskMatrixView.vue`：risk cell 顏色改用 CSS 變數
