## MODIFIED Requirements

### Requirement: SAQ 評分完成後自動建立風險評估
系統 SHALL 在 SAQ 評分完成後，依據 dim_e1–e6 分數直接建立 RiskAssessment，移除 D6 投影公式（E/S/G/GP 推導），assessment_version 預設升為 `v3`。

#### Scenario: SAQ 評分完成自動建立 RA（新路徑）
- **WHEN** esgchain-ai 完成 SAQ 評分，回調 `/api/v1/saq/{id}/score-callback`
- **THEN** `RiskAutoDerivationService::deriveFromSaq()` 讀取 `saqs.dim_e1`–`dim_e6`
- **AND** 建立 RiskAssessment，含：
  - `source_type = 'saq'`
  - `source_id = saq.id`（同時回填 `source_saq_id`）
  - `assessment_version = 'v3'`
  - `dim_e1`–`dim_e6` 直接從 SAQ 帶入
  - 四軸欄位（e_probability 等）全部 NULL
- **AND** 不再執行任何四軸推導公式

#### Scenario: 移除 D6 投影公式
- **WHEN** `deriveFromSaqV2()` 方法被呼叫（舊 v2 路徑）
- **THEN** 系統 SHALL NOT 執行 `axis_e = 0.4*E1 + 0.3*E3 + 0.3*E6` 等映射公式
- **AND** 舊 `deriveFromSaq()`（legacy 路徑，依 score_e/s/g 建立四軸）也 SHALL NOT 再被呼叫

#### Scenario: 一個 SAQ 只對應一筆 v3 RA
- **WHEN** 同一 SAQ 評分回調觸發兩次（重試情境）
- **THEN** 系統 SHALL 以 `source_saq_id = saq.id AND source_type = 'saq'` 做 upsert，避免重複建立
- **AND** 若已存在舊 RA（source_type='saq'），更新 dim_e1–e6 欄位及 assessed_at

#### Scenario: AiRiskSuggestionService payload 更新
- **WHEN** 系統對 esgchain-ai 請求 AI 風險建議
- **THEN** 請求 payload SHALL 包含 dim_e1–dim_e6 及各維度 label
- **AND** SHALL NOT 包含 e_probability、e_impact、axis_score 等四軸欄位

#### Scenario: suppliers.risk_score 同步（新公式）
- **WHEN** RiskAssessment 建立或更新（Observer 觸發）
- **THEN** 計算 `risk_score = 1 - Σ(weight[N] × dim_eN) / 100`
- **AND** weights 從 `system_settings.dim_weight_defaults` 讀取
- **AND** 若 dim_e6 為 null，將 E6 的 weight 等比例分攤至其他五個有值的維度
- **AND** 若所有 dim_eN 皆為 null，risk_score 設為 null（不強制預設）
