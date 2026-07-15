## ADDED Requirements

### Requirement: 風險趨勢摘要列

供應商風險歷史 tab SHALL 在時間軸事件流上方顯示「風險趨勢」摘要列，以年度為單位橫向比較各維度風險分數。

摘要列規則：
- 取每個自然年（assessed_at 年份）中 assessed_at 最大的一筆 RA
- 顯示欄位：年份、E / S / G / GP score、axis1_score / axis2_score / axis3_score（若有）
- delta 標記：與前一年（表格下一行）相比，分數上升顯示 ↑（綠）、下降顯示 ↓（紅）、持平不顯示
- 年份依降冪排列（最新在上）
- 若只有一年資料，不顯示 delta 標記

#### Scenario: 多年資料顯示趨勢摘要

- **WHEN** 供應商有跨越 2 個以上自然年的 RiskAssessment 資料
- **THEN** 系統 SHALL 顯示趨勢摘要列，每列一年，E/S/G/GP 各欄含分數與 ↑↓ delta

#### Scenario: 單年資料不顯示 delta

- **WHEN** 所有 RiskAssessment 的 assessed_at 都在同一自然年
- **THEN** 系統 SHALL 顯示趨勢摘要列但省略 delta 標記欄

#### Scenario: 三軸分數欄位條件顯示

- **WHEN** 某年最新 RA 有 axis1_score / axis2_score / axis3_score
- **THEN** 趨勢列 SHALL 顯示三軸欄位；若無則顯示「—」

#### Scenario: 無 RA 資料時隱藏摘要列

- **WHEN** 供應商尚無任何 RiskAssessment 記錄
- **THEN** 趨勢摘要列 SHALL 不顯示
