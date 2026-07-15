## MODIFIED Requirements

### Requirement: 風險歷史依評估版本分流顯示
供應商風險歷史 UI SHALL 依 `assessment_version` 欄位決定顯示哪套 UI 元件：v2 記錄顯示六維分數（dim_e1–dim_e6）長條圖 + 四軸矩陣；legacy/null 記錄顯示現有三軸（E/S/G/GP）展示方式。兩種卡片在同一時間軸上共存，並以 badge 標示版本差異。

#### Scenario: v2 評估卡顯示六維
- **WHEN** 時間軸上存在 assessment_version='v2' 的 risk_assessment
- **THEN** 卡片顯示六個維度分數長條圖（E1/E2/E3/E4/E5/E6），以及由此推導的 E/S/G/GP 四軸矩陣位置

#### Scenario: legacy 評估卡維持原三軸格式
- **WHEN** 時間軸上存在 assessment_version='legacy' 或 null 的舊記錄
- **THEN** 卡片以原三軸（E/S/G/GP）格式顯示，不顯示六維欄位，並加上「舊版評估」badge

#### Scenario: 版本混合的時間軸
- **WHEN** 供應商同時有舊版（legacy）與新版（v2）評估記錄
- **THEN** 兩種卡片按時間排序共存於時間軸，版本 badge 幫助使用者識別差異

### Requirement: E4/E6 外部資料來源標示
v2 評估卡 SHALL 在 E4/E6 維度分數旁顯示「外部資料混合」標示，並提供 tooltip 說明混合比例（E4: 40%外部+60%問卷；E6: 問卷÷法規壓力指數）。

#### Scenario: 查看 E4 分數來源
- **WHEN** 使用者滑鼠懸停 E4 維度分數
- **THEN** tooltip 顯示：「地緣風險暴露分（geo_risk={X}）佔 40%，問卷管理成熟度佔 60%」
