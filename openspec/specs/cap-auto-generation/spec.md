## ADDED Requirements

### 觸發條件

- 當 `SAQController::scoreCallback()` 處理 `scoring_framework='multi-framework'` 的計分結果，且 `RiskAutoDerivationService::deriveFromSaq()` 成功建立 `RiskAssessment` 後，系統 **必須** 呼叫 `CapAutoGenerationService::generateFromRisk()`。
- 觸發判斷依據 axis level（`RiskAssessment::axisToLevel()`）：
  - `extreme`（score ≥ 80）→ 自動建立 CAP
  - `high`（60 ≤ score < 80）→ 寫入 Notification，不建立 CAP
  - `medium` / `low` / `very_low`（score < 60）→ 不動作

### 重複保護

- 建立 CAP 前，系統 **必須** 檢查是否已存在 `auto_generated=true AND saq_id={saq.id} AND triggered_by_axis={axis}` 且 status 不為 `closed` 的 CAP。若存在，跳過建立（冪等保護，防止 `weight_updated` 重算重複觸發）。

### axis1 CAP（ISO 26000 ESG 暴露）

- 建立 CAP，欄位：
  - `source_type`: `risk_assessment`
  - `source_id`: 本次 RiskAssessment UUID
  - `saq_id`: 觸發計分的 SAQ UUID
  - `triggered_by_axis`: `axis1`
  - `auto_generated`: `true`
  - `priority`: `critical`
  - `title`: `[ISO 26000] 供應商 ESG 暴露風險嚴重超標（分數：{axis1_score}）`
  - `due_date`: 建立日 + 30 天
- Finding 來源：`category_scores`（AI callback 回傳）中 value < 60 的每個 `iso26k.*` key，各建立一條 CAPFinding：
  - `framework`: `iso26k`
  - `topic_slug`: category_scores 的 key（如 `iso26k.env.climate`）
  - `source_score`: category_scores 的 value
  - `threshold`: 60.0
  - `finding`: 系統自動組語：`{topic 中文標籤} 評分 {score}/100，低於及格線 60`（中文標籤從 `question_tags.label_zh` 查詢）

### axis2 CAP（ISO 20400 治理成熟度）

- 建立 CAP，欄位同上，差異：
  - `triggered_by_axis`: `axis2`
  - `title`: `[ISO 20400] 供應商次階供應鏈管理能力不足（分數：{axis2_score}）`
- Finding 來源：對 `saq_responses` JOIN `project_questions` JOIN `question_tag_assignments` JOIN `question_tags`（`l1_domain='iso20400'`），GROUP BY `question_tags.slug`，計算 `AVG(raw_score)`；AVG < 60 的每個 slug 建立一條 CAPFinding：
  - `framework`: `iso20400`
  - `topic_slug`: question_tags.slug
  - `source_score`: AVG(raw_score) 四捨五入至小數第二位
  - `threshold`: 60.0
  - `finding`: `{topic 中文標籤} 平均評分 {score}/100，低於及格線 60`

### 通知（high level）

- axis1 或 axis2 為 high（60–79）時，寫入 `notifications` 表：
  - `type`: `risk_high_axis`
  - `notifiable_type`: `Supplier`
  - `notifiable_id`: supplier.id
  - `data.axis`: `axis1` 或 `axis2`
  - `data.score`: axis score
  - `data.saq_id`: saq.id
- 推播對象：系統內 `role IN ('sustain', 'comply')` 的所有 User

### Requirement: CAP 觸發維度欄位擴充為六維
`triggered_by_axis` 欄位 SHALL 從三值（axis1/axis2/axis3）擴充為接受六維識別碼（dim_e1–dim_e6），並保留舊值相容。觸發判斷 SHALL 改用 dim_eN 合規分低於六維閾值的條件。

#### Scenario: dim_e2 低於閾值自動產生 CAP
- **WHEN** SAQ 計分完成後 dim_e2 = 35（低於 E2 閾值 45）
- **THEN** 系統 SHALL 自動建立 CAP，`triggered_by_axis = 'dim_e2'`
- **THEN** CAP 標題 SHALL 帶入「氣候與碳排」維度對應的矯正模板

#### Scenario: 多維度低於閾值產生多筆 CAP
- **WHEN** dim_e2 = 35 且 dim_e5 = 38（兩維均低於閾值）
- **THEN** 系統 SHALL 分別建立兩筆 CAP，各自記錄 `triggered_by_axis`

#### Scenario: 舊有 axis1/axis2/axis3 值向下相容
- **WHEN** 查詢歷史 CAP 記錄中 `triggered_by_axis = 'axis1'`
- **THEN** 系統 SHALL 正常返回該記錄，不報錯（舊值不需回填）

### Requirement: 六維矯正模板對應
系統 SHALL 維護六個維度對應的 CAP 矯正方向模板，在自動建立 CAP 時帶入：

| triggered_by_axis | 矯正方向提示 |
|---|---|
| dim_e1 | 環境管理系統建立或認證（ISO 14001） |
| dim_e2 | 碳排放量化與揭露計畫 |
| dim_e3 | 勞工人權與社會責任改善 |
| dim_e4 | 地緣政治風險應對與備援規劃 |
| dim_e5 | 公司治理透明度與反腐措施 |
| dim_e6 | 適用法規合規準備（CBAM/EUDR 等） |

#### Scenario: 帶入對應矯正模板
- **WHEN** 系統以 `triggered_by_axis = 'dim_e2'` 建立 CAP
- **THEN** CAP `suggested_actions` 欄位 SHALL 預填 E2 對應的矯正方向提示文字
