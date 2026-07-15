## MODIFIED Requirements

### Requirement: 供應商風險時間軸前端佈局

供應商風險歷史 tab 的時間軸 SHALL 採單欄事件流佈局，廢棄兩欄配對設計。

**佈局規則：**
- 事件依 `date` 降冪排列，以自然年分組（分隔線標示年份）
- 主要事件卡：`risk_assessment` 類型，顯示 E/S/G/GP 維度 bar + level badge + 三軸分數（若有）+ embed 來源 SAQ 資訊
- 次要事件：`saq_scored` 類型且無對應 RA 者，以輕量標記列顯示（grade + score，不渲染為完整卡片）
- pending_saq 卡維持現有設計，顯示在最頂端（所有年份分組之前）

**RA 卡 embed 來源 SAQ：**
RA 卡底部 SHALL 顯示 `linked_saq` 資訊（grade、score、E/S/G 子分數），以橘色邊框區塊呈現，與現有「來源 SAQ」設計一致。

**後端補 year 欄位：**
`SupplierTimelineService` 每個事件物件 SHALL 包含 `year`（integer，從 date 提取），供前端分組使用，避免前端字串解析。

#### Scenario: 同一 SAQ 對應多筆 RA 時不錯位

- **WHEN** 同一 `source_saq_id` 有 2 筆 RiskAssessment
- **THEN** 兩筆 RA 各自渲染為獨立卡片，依 assessed_at 降冪排列，左側不出現空白欄

#### Scenario: 年度分組標示

- **WHEN** 事件流跨越 2024 / 2025 / 2026 三個自然年
- **THEN** 系統 SHALL 在每個年份第一張卡片上方顯示年份分隔線（如「2025」）

#### Scenario: 無對應 RA 的已計分 SAQ 顯示為輕量標記

- **WHEN** 一筆 `saq_scored` 事件的 `linked_ra` 為 null
- **THEN** 系統 SHALL 以單行輕量標記（grade chip + 分數 + 日期 + 查看連結）顯示，不渲染完整卡片

#### Scenario: RA 卡正確顯示三軸分數

- **WHEN** RA 事件的 `risk.axis1_score` / `axis2_score` / `axis3_score` 不為 null
- **THEN** RA 卡 SHALL 在維度 bar 下方顯示三軸分數 chip 列
