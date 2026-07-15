# Change Proposal: Wave A — API Contract Alignment

## 動機

`esgchain-openapi.yaml` v2.1.0 定義了正式的 API 合約。現有實作在路徑命名、狀態機語義、欄位結構上與 spec 有多處不符，導致前端整合和外部系統對接困難。

Wave A 的目標：**不新增新功能，讓現有功能完全符合 spec 的命名、結構與行為**。

## 範圍

### A1 — Supplier 對齊

**狀態簡化**
- `status` 從 FRS 六狀態（potential/invited/reviewing/certified/suspended/terminated）改為 spec 三狀態：`active | inactive | suspended`
- 新增 `onboarding_stage` 欄位（僅供內部流程，不暴露於 API）：`potential | invited | reviewing | certified`

**篩選參數對齊**
- `search` 參數重命名為 `q`（同時支援 name / code 模糊搜尋）
- 新增 `supplier_group_id` 篩選
- 新增 `sasb_industry_id` 篩選

**新增端點**
- `GET /api/v1/suppliers/{id}/risk-summary`（Wave B 完整實作前回傳空資料結構）

### A2 — Questionnaire 路徑 + 狀態機重構

**路徑重命名**（全部 /saqs* → /questionnaires*）

| 舊路徑 | 新路徑 |
|--------|--------|
| GET /saq-projects/{id}/saqs | GET /questionnaires |
| POST /saq-projects/{id}/saqs/send | POST /questionnaires/send |
| GET /saqs/my | GET /questionnaires（supplier 角色自動過濾） |
| GET /saqs/{id} | GET /questionnaires/{id} |
| POST /saqs/{id}/submit | POST /questionnaires/{id}/submit |
| POST /saqs/{id}/approve | POST /questionnaires/{id}/complete-review |
| POST /saqs/{id}/reject | POST /questionnaires/{id}/return-review |

**新增端點**
- `POST /questionnaires/{id}/start-review`（submitted → under_review）
- `POST /questionnaires/{id}/mark-reviewed`（completed → reviewed）
- `GET /questionnaires/counts`（KPI 雙計數）

**狀態機對齊**

| 舊狀態 | 新狀態 |
|--------|--------|
| pending | not_started |
| sent | not_started |
| in_progress | in_progress |
| submitted | submitted |
| reviewing | under_review |
| approved | completed |
| rejected | review_returned |
| （新增） | reviewed |

**行為對齊**
- `is_editable` 欄位：`false` 當 status ∈ {under_review, completed, reviewed}
- `under_review` 狀態時，supplier / sup_esg 角色存取任何寫入端點 → 403 FORBIDDEN
- `review_started_at` 自動記錄（start-review 觸發時）
- `reviewed_by_id` 自動記錄（start-review 觸發時）

### A3 — Auth refresh_token

- `POST /auth/login` 回應補充 `refresh_token` 欄位（TTL 7 天）
- `POST /auth/refresh` 支援 request body `{ refresh_token }` 參數

## 不在範圍

- 風險模型重設計（Wave B）
- Settings 模組（Wave B）
- PCF 非同步化（Wave C）
- Reports（Wave C）
- CAP 模組（維持現有，不變動）
- Trade Goods 模組（維持現有，不變動）

## 成功條件

- [ ] `GET /api/v1/suppliers` 支援 `q`, `supplier_group_id`, `sasb_industry_id` 篩選
- [ ] Supplier status 為 active/inactive/suspended 三態
- [ ] `GET /api/v1/questionnaires` 路徑正確回應
- [ ] 問卷七狀態流轉正確，`is_editable` 欄位存在
- [ ] `under_review` 狀態時，supplier 角色 PUT/POST 回傳 403
- [ ] `GET /questionnaires/counts` 回傳 `just_submitted_count` 和 `submitted_count`
- [ ] `POST /auth/login` 回應含 `refresh_token`
