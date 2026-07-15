## ADDED Requirements

### Requirement: 供應商風險評估歷史時間軸

供應商詳情頁 overview tab SHALL 在底部新增「風險評估歷史」區塊，以時間倒序列出該供應商所有 RiskAssessment 紀錄（最多 10 筆）。

每筆紀錄 SHALL 顯示：
- `assessed_at`（日期）
- E / S / G / GP 各維度 cell_score（probability × impact）與對應 risk_level badge
- 整體最高 risk_level（取四維度中最高者）
- delta 標記：與上一筆相比，各維度 cell_score 的 ↑↓ 變化（僅在有上一筆時顯示）
- 若為自動推導（notes 含「自動從 SAQ」），顯示「自動」badge

資料來源：`GET /api/v1/risk/assessments?supplier_id={id}&per_page=10`

#### Scenario: 顯示歷史評估列表

- **WHEN** 使用者開啟有 RiskAssessment 記錄的供應商詳情頁
- **THEN** 系統 SHALL 在 overview tab 底部顯示最多 10 筆評估紀錄，最新在上

#### Scenario: 顯示 delta 變化

- **WHEN** 某筆 RiskAssessment 的 E 維度 cell_score 與前一筆不同
- **THEN** 系統 SHALL 在 E 維度旁顯示 ↑（上升）或 ↓（下降）箭頭與差值

#### Scenario: 無歷史紀錄時顯示空狀態

- **WHEN** 供應商尚無任何 RiskAssessment
- **THEN** 系統 SHALL 顯示「尚無風險評估紀錄」空狀態，並提供「前往風險矩陣填報」連結
