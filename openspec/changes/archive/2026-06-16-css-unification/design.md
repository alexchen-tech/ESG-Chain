## Context

`esgchain-web` 的 CSS 架構分三層：
1. `main.css` — `:root` CSS 變數定義（Warm Paper Light tokens）
2. `components.css` — 全局共用 class（`.btn`、`.badge`、`.card` 等）
3. `<View>.vue <style scoped>` — 頁面局部樣式

問題在於第三層越界承擔了本應屬於第一、二層的工作：
- Risk level 色值（5組）各自在 RiskMatrixView、SupplierDetailView 硬編碼
- `.badge-purple`、`.badge-orange` 已在 components.css 定義，但 6+ 個 View 又局部重定義（有 ShipmentsView 甚至改了值）
- TradeGoods 的 `.tag-cbam`、`.tag-eudr`、`.status-dot--*` 只存在於 TradeGoodsView 的 scoped style

## Goals / Non-Goals

**Goals:**
- `:root` 新增 risk level 色彩 token（`--risk-extreme-bg`、`--risk-high-bg` 等 10 個變數）
- `components.css` 新增 `.risk-extreme`、`.risk-high`、`.risk-medium`、`.risk-low`、`.risk-very-low` 共用 class
- `components.css` 新增 `.tag-cbam`、`.tag-eudr`、`.tag-cmrt`、`.tag-uflpa`、`.tag-espr`、`.tag-reach` 及 `.status-dot--valid`、`.status-dot--expiring-soon`、`.status-dot--expired`、`.status-dot--missing`
- 移除所有 View 中重複或衝突的局部 badge/risk 定義
- 修正 ShipmentsView badge 色值偏差（與 components.css 對齊）

**Non-Goals:**
- 不引入 CSS-in-JS 或 utility-first（Tailwind 等）框架
- 不處理 inline style（留待後續 pass）
- 不重構 Portal 頁面（另有獨立設計系統考量）
- 不動 spacing magic numbers（不在本次範圍）

## Decisions

### D1：Risk level 色值放 `:root` 變數，不只是 class

**決定：** 同時提供 CSS 變數（`--risk-extreme-bg`）和 class（`.risk-extreme`）。

**理由：** Class 適合直接套用在 DOM 元素，但 JS 動態計算顏色（如 SupplierDetailView 的 risk bar width）需要讀取變數值。兩者並存不衝突。

**捨棄方案：** 只用 class → JS 無法讀取色值；只用變數 → 模板中要寫 `:style` 而非 `:class`，更冗長。

### D2：Tag class 命名前綴 `.tag-*`，不併入 `.badge-*`

**決定：** 新增 `.tag-cbam` 等，與現有 `.badge-*` 分開。

**理由：** Badge 是圓角 pill 形狀用於狀態，Tag 是方角用於法規/品類標注，視覺語義不同。TradeGoods 現有 `.tag-*` 已有 CSS 定義，沿用命名保持 View 層零改動（只需把定義移到 components.css）。

### D3：修正順序 — 先加全局再移除局部

**決定：** 每個 class 先確認 components.css 有正確定義，再刪 View 的局部版本。

**理由：** 避免刪除後出現瞬間 class 不存在的空窗期（雖然是靜態資源，但 Vite HMR 有順序問題）。

## Risks / Trade-offs

- **[Risk] ShipmentsView badge 色值修正可能改變現有 UI 外觀** → 偏差很小（`#fff7ed` vs `#ffedd5`）；修正後與其他頁面一致是正確結果
- **[Risk] scoped style 中同名 class 優先級高於全局** → 只要刪除局部定義，全局版本自然生效；需確認每個 View 不遺留任何同名定義
- **[Risk] TradeGoodsView tag 色值移至全局後其他頁面可能誤用** → tag class 本身語義明確（`.tag-cbam` 不會被無意使用）

## Migration Plan

1. 更新 `main.css` — 新增 risk level CSS 變數
2. 更新 `components.css` — 新增 `.risk-*`、`.tag-*`、`.status-dot--*` class
3. 逐一處理各 View（可並行）：
   - TradeGoodsView：移除局部 tag/status-dot/page 定義
   - ShipmentsView：移除局部 badge 定義（色值以全局為準）
   - SupplierDetailView：移除 `.badge-purple`，risk bar 色值改用變數
   - QuestionnaireView、PortalView、SupplierSurveyView：移除 `.badge-purple`
   - RiskMatrixView：cell 顏色改用 `.risk-*` class
4. 瀏覽器驗證各頁面視覺無差異

**Rollback：** CSS 純前端靜態資源，git revert 即可。
