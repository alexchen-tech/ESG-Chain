## MODIFIED Requirements

### Requirement: 供應商比較頁折線圖渲染

系統 SHALL 以有效 SVG 座標渲染總分趨勢折線圖。

#### Scenario: 多波次多供應商折線
WHEN 系列含 2 個以上 project，且供應商在各 project 均有分數
THEN 折線圖 SHALL 以連續 polyline 連接各波次分數點，各供應商使用不同顏色；僅連接有分數的波次，null 點不斷線

#### Scenario: SVG 座標系
WHEN 渲染折線圖
THEN SVG SHALL 使用 `viewBox="0 0 1000 200"` 絕對座標，X 軸範圍 50–950、Y 軸範圍 10–190，不使用百分比字串作為 points 屬性值

#### Scenario: 單一波次
WHEN 系列僅有 1 個 project
THEN 折線圖區塊 SHALL 不顯示（`v-if="comparisonData.projects.length > 1"`）

### Requirement: 逐題趨勢矩陣可用性

系統 SHALL 提供可讀性良好的逐題趨勢矩陣。

#### Scenario: 題目欄固定
WHEN 矩陣欄位超出可視區域需橫向捲動
THEN 「題目」欄 SHALL sticky 固定於左側，不隨橫向捲動移動

#### Scenario: 波次標籤縮短
WHEN 渲染矩陣欄標題的波次子標籤
THEN 系統 SHALL 優先提取波次名稱中的 `Q1/Q2/Q3/Q4`、`H1/H2`、或 4 位年份作為標籤，取代直接截斷字串
