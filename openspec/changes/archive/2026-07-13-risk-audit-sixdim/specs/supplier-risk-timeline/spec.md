## MODIFIED Requirements

### Requirement: 供應商時間線顯示 E1–E6 六維分數
供應商詳情時間線的風險評估事件卡片 SHALL 移除四軸顯示邏輯，改為 E1–E6 六個維度分數，並依 source_type 顯示不同事件標題與 badge。

#### Scenario: 時間線 risk_assessment 事件卡片（SAQ 驅動）
- **WHEN** SupplierTimelineService 產生 source_type='saq' 的 RA 事件
- **THEN** 事件標題為「SAQ 評核風險更新」
- **AND** 卡片內容顯示：dim_e1–e6 六個維度分數（格式：E1: 72.5）
- **AND** 提供連結至對應 SAQ 詳情頁
- **AND** SHALL NOT 顯示 e_probability、e_impact 等四軸欄位

#### Scenario: 時間線 risk_assessment 事件卡片（地緣事件驅動）
- **WHEN** SupplierTimelineService 產生 source_type='geo_event' 的 RA 事件
- **THEN** 事件標題為「地緣事件風險複查：{geo_event_name}」
- **AND** 卡片顯示 dim_e4 前後差異（pre_e4_score → post_e4_score）
- **AND** 提供連結至對應地緣事件詳情頁

#### Scenario: 移除 v2 專屬分支
- **WHEN** `SupplierTimelineService::buildRiskEvent()` 處理 RA 事件
- **THEN** 不再有 `if ($ra->assessment_version === 'v2')` 等版本分支
- **AND** 統一使用 dim_e1–e6 路徑（v3 格式）

#### Scenario: 前端時間線卡片 Vue 元件更新
- **WHEN** `SupplierDetailView.vue` 渲染時間線中的 `risk_assessment` 類型事件
- **THEN** 顯示六個維度標籤（E1–E6）及對應數字（font-mono）
- **AND** source_type='geo_event' 的事件顯示地球儀 badge 以示區別
- **AND** 舊 `buildDimension('e')` 等輸出欄位的顯示邏輯 SHALL 從 Vue 元件移除
