## ADDED Requirements

### Requirement: Multi-tag 混合範本類型

`saq_templates.scoring_framework` SHALL 支援新枚舉值 `"multi-framework"`，代表此範本的題目同時包含多個框架的 slug 標記。

系統 SHALL 驗證：當 `scoring_framework = "multi-framework"` 時，範本至少包含一題帶有 `iso26k.*` slug 的題目，且至少包含一題帶有 `iso20400.*` slug 的題目。

#### Scenario: 建立 Multi-tag 範本

- **WHEN** admin 建立 SAQTemplate，`scoring_framework = "multi-framework"`，且題目中包含 iso26k.hr.child_labor 和 iso20400.policy.written_policy 的 slug
- **THEN** 系統 SHALL 成功建立範本，`status = "draft"`

#### Scenario: Multi-tag 範本驗證失敗

- **WHEN** admin 嘗試發布 `scoring_framework = "multi-framework"` 的範本，但所有題目 slug 均屬同一框架
- **THEN** 系統 SHALL 回傳 422，說明「multi-framework 範本必須包含至少兩個框架的題目標籤」

### Requirement: 計分引擎多框架輸出

當 SAQ 計分引擎收到 `scoring_framework = "multi-framework"` 時，SHALL 對同一份答題資料執行三次 filter 並各自計算：
- `iso26000` filter（iso26k.* slug）→ 輸出 `iso26000_total`、`iso26000_category_scores`
- `iso20400` filter（iso20400.* slug）→ 輸出 `iso20400_total`、`iso20400_category_scores`
- `geo_risk` filter（geo_risk.* slug）→ 輸出 `geo_risk_total`（若有 geo_risk.* 題目）

同時保留原有 `score_e / score_s / score_g` 輸出（從 iso26k.* esg 對映填入，向後相容）。

三軸換算：
- `axis1_score = 100 - iso26000_total`（ESG 暴露，分數越高暴露越大）
- `axis2_score = 100 - iso20400_total`（治理成熟度風險，分數越高風險越高）

#### Scenario: Multi-framework 計分輸出

- **WHEN** `calculate_saq_score()` 收到 `scoring_framework = "multi-framework"` 的請求
- **THEN** 系統 SHALL 回傳含 `iso26000_total`、`iso20400_total`、`axis1_score`、`axis2_score` 的結果，各框架 category_scores 分開列出

#### Scenario: 題目無某框架 slug 時該框架分數為 null

- **WHEN** Multi-tag 範本無任何 geo_risk.* slug 題目
- **THEN** `geo_risk_total` SHALL 為 null，不影響 axis1 / axis2 計算

#### Scenario: 向後相容 score_e/s/g 填入

- **WHEN** Multi-framework 計分完成
- **THEN** `score_e`、`score_s`、`score_g` SHALL 從 iso26k.* 的對映 pillar 填入，與現有 ESG 框架行為一致

### Requirement: 舊範本停用流程

現有獨立框架範本（scoring_framework 為 esg / iso20400 / iso26000）SHALL 透過 `is_active = false` 停用，不刪除範本資料。已關聯的 SaqProject 資料保留，但停用範本不可再建立新 SaqProject。

#### Scenario: 停用舊範本

- **WHEN** admin 將 scoring_framework = "iso20400" 的舊範本 is_active 設為 false
- **THEN** 此範本不再出現於建立 SaqProject 的範本選單；既有 SaqProject 不受影響，歷史分數保留

#### Scenario: 舊 SaqProject 分數仍可查閱

- **WHEN** 使用者開啟關聯舊範本的 SaqProject 評分結果
- **THEN** 系統 SHALL 正常顯示歷史分數，並標記「此問卷使用舊版範本，三軸雷達分不適用」
