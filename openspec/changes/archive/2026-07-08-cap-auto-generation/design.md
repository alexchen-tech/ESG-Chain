## Context

ESG-Chain 已實作問卷（SAQ）→ AI 計分（multi-framework）→ RiskAssessment 三軸評估的自動推導鏈。本次設計在此鏈末端加入「閾值觸發 → CAP 自動建立」的閉環，並同時強化 CAPFinding 的 ISO 框架語意。

現有關鍵資料流：
- `scoreCallback` 收到 `iso26000_total`、`iso20400_total`、`category_scores`（key 為 `iso26k.*` slug）
- `RiskAutoDerivationService::deriveFromSaq()` 建立 RiskAssessment，axis1 = 100 − iso26000_total，axis2 = 100 − iso20400_total
- `question_tags` 三層結構（l1_domain / l2_pillar / l3_topic），slug 為 AI scoring router 識別鍵

## Goals / Non-Goals

**Goals:**
- axis extreme（≥80）時自動建立 CAP，finding 精確對應 ISO 26000 / ISO 20400 低分主題
- axis high（60–79）時發送系統通知，不建立 CAP
- CAP 可追溯至觸發來源（RiskAssessment、SAQ、具體 axis）
- CAPFinding 支援 ISO 框架詞彙（framework + topic_slug + 分數資訊）
- 向後相容：既有 manual CAP（category: E/S/G）不受影響

**Non-Goals:**
- axis3（地緣產業，手動填報）不納入自動觸發，僅支援手動開 CAP
- 不修改 AI scoring 服務（category_scores 格式已符合需求）
- 不實作 CAP 自動關閉或自動重評邏輯

## Decisions

### D1：一個 axis 觸發一個 CAP，不合併
axis1 與 axis2 分別觸發獨立的 CAP。理由：axis1（ISO 26000）的矯正行動由永續/ESG 團隊負責，axis2（ISO 20400）的矯正行動涉及採購部門，合併會造成指派模糊。`triggered_by_axis` 欄位確保可區分。

### D2：finding 閾值固定為 60
category_scores 中 < 60 分的 iso26k 主題、SAQResponse 聚合後 < 60 分的 iso20400 主題，各自產生一條 finding。閾值不可設定（避免配置複雜度），未來如需調整透過 migration 更新 `threshold` 欄位預設值。

### D3：iso20400 finding 從 SAQResponse 即時聚合
axis2 finding 不依賴 AI 回傳的 category_scores（AI 目前只回傳 iso26k.\*），而是在 CapAutoGenerationService 內直接 JOIN question_tag_assignments WHERE l1_domain='iso20400'，對 raw_score 取平均，篩出低分主題。

### D4：CAPFinding 新欄位與舊 category 並存
`category`（E/S/G）保留，手動建立的 finding 繼續使用。自動產生的 finding 填入 `framework` + `topic_slug` + `source_score` + `threshold`，`category` 可為 null。前端依 `framework` 是否存在決定顯示哪套標籤。

### D5：通知使用既有 notifications 機制
high level（60–79）不開 CAP，改以 `type='risk_high_axis'` 寫入 notifications 表，推播給 `sustain` 和 `comply` 角色的用戶。

### D6：自動產生的 CAP 可被人工覆蓋
`auto_generated: true` 只是標記，不鎖定欄位。責任人員可修改 title、due_date、assigned_to，也可手動追加 finding。

## Risks / Trade-offs

**R1：SAQResponse.raw_score 在 scoreCallback 時序**
`question_scores` 批次更新與 CAP 建立在同一 request 內，需確認 `raw_score` 已寫入 DB 再執行聚合查詢。解法：CapAutoGenerationService 在 `updateScore()` 與 snapshot 建立之後呼叫（即 RiskAutoDerivation 之後），確保 DB flush。

**R2：無 iso20400 tagged 題目時的行為**
若問卷範本無任何 `l1_domain='iso20400'` 標籤題目，axis2 extreme 時 CAP 仍建立，但 findings 為空，由人工補填。這是可接受的行為。

**R3：重複計分（weight_updated）觸發重複 CAP**
`snapshotTrigger` 為 `weight_updated` 時代表權重重算，不應重複開 CAP。解法：CapAutoGenerationService 在執行前查詢是否已存在 `auto_generated=true AND saq_id=? AND triggered_by_axis=?` 的 open CAP，若存在則跳過。
