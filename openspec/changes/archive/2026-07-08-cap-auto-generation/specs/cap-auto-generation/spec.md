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
