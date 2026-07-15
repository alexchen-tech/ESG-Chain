## MODIFIED Requirements

### Requirement: 六維 → 四軸自動投影
SAQ 完成並計算出六維分數後，系統 SHALL 依 D6 定義的映射規則自動推導 E/S/G/GP 四軸的 probability 與 impact，寫入 `risk_assessments` 的四軸欄位。此過程為系統自動計算，不允許人工覆蓋。

#### Scenario: 六維完整時完整投影四軸
- **WHEN** 供應商的問卷六維分數全部有值（E1–E6 均不為 null）
- **THEN** 系統計算 E/S/G/GP 四軸各自的 probability（1–5）與 impact（1–5），並更新 risk_assessments 記錄

#### Scenario: 部分維度為 null 時的 fallback
- **WHEN** dim_e3 為 null（供應商未加掛 E3 模組），計算 S 軸
- **THEN** S 軸 probability 僅用 E1.社會支柱分，不納入 E3 加權（等效於 E3 係數歸零、E1 係數升為 1.0）

#### Scenario: probability/impact 夾限
- **WHEN** 計算結果的原始浮點數超出 1–5 範圍（如 0.3 或 5.8）
- **THEN** 系統強制 clamp 至最小值 1 或最大值 5，不允許越界

### Requirement: 六維評估版本標記
系統 SHALL 在 `risk_assessments` 紀錄上標記評估計算版本，以區分舊版三軸（legacy）與新版六維（v2）評估結果，讓前端能依版本顯示對應 UI。

#### Scenario: 新問卷完成使用六維評估
- **WHEN** 六維計分 feature flag 啟用後完成的 SAQ 推導出 risk_assessment
- **THEN** 該筆記錄 `assessment_version = 'v2'`，前端顯示六維分數區塊

#### Scenario: 舊問卷資料保持 legacy 版本
- **WHEN** 六維計分 feature flag 啟用前的舊有 risk_assessment 記錄
- **THEN** `assessment_version = 'legacy'`（或 null），前端顯示舊版三軸 UI，dim_e1–dim_e6 均為 null
