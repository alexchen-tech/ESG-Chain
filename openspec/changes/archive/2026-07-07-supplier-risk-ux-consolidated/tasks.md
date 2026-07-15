## Tasks

> 本 change 整合自 `risk-matrix-intelligence`（已歸檔）與 `supplier-timeline`（已歸檔）及本 session UI 優化。
> 以下任務均已於歸檔的子 change 中完成，此處為合併後的完整紀錄。

### Phase 1：後端 — 資料庫與 Model

- [x] Migration：`risk_assessments` 加 `source_saq_id CHAR(36) NULL` FK → `saqs.id` ON DELETE SET NULL
- [x] Migration：`risk_assessments` 加 `is_auto BOOLEAN DEFAULT FALSE`

### Phase 2：後端 — Service 與 Observer

- [x] `RiskAutoDerivationService::deriveFromSaq($saq)`：以 `probability = max(1, ceil((100 - score_dim) / 20))`, `impact = 3` 自動建立 E/S/G RiskAssessment，填入 `source_saq_id`
- [x] `SAQController::scoreCallback()`：完成計分後呼叫 `RiskAutoDerivationService`
- [x] `RiskAssessmentObserver::created()`：偵測 cell_score ≥ 20 自動開立 CAP + CAPFinding，防重複
- [x] `AppServiceProvider` 註冊 `RiskAssessmentObserver`
- [x] `RiskMatrixService::buildMatrix()` 修正：joinSub 取每個 supplier_id 最新 assessed_at，消除重複格子

### Phase 3：後端 — Timeline API

- [x] `SupplierTimelineService::getTimeline($supplierId)`：UNION RiskAssessment + SAQ（有分數），eager-load linked SAQ 與 CAP，降冪排序
- [x] `GET /api/v1/suppliers/{id}/risk-timeline` 路由 + Controller action
- [x] 回傳結構含 `events[]`（`risk_assessment` / `saq_scored`）與 `pending_saq`

### Phase 4：前端 — SupplierDetailView 重構

- [x] Tab 結構更新：overview / risk（風險歷史）/ sustain / comply / facility
- [x] 頁頭 meta chips：code / country_code / tier
- [x] 「Onboarding 階段」標籤改為「活躍狀態」
- [x] 風險歷史 tab：雙欄佈局（左：SAQ 計分；右：風險評估）
- [x] 三種事件卡片樣式：`saq_scored`（藍框）/ `risk_assessment`（橙框自動 / 灰框手動）/ `pending_saq`（黃虛線頂框）
- [x] Section 標題：15px 700，左側 3px accent 線條，移除所有內聯 `style="border:none"` 覆蓋
- [x] Detail grid：行底線分隔，奇欄右邊線，label 11.5px / value 14px 600
- [x] Tab 字型 14px，active tab `background: #f4faf8`

### Phase 5：前端 — RiskMatrixView 優化

- [x] Legend 改為橫式色條（`.legend-band` + `.legend-band-item`，5 段等寬 flex）
- [x] 廠商卡 `sc-head-right`：狀態 badge + 26×22px 比較按鈕
- [x] Dim 箱背景改為 `#f7f5f1`

### Phase 6：前端 — 其他頁面優化

- [x] `QuestionnaireView`：colgroup 重新分配，「查看」改邊框連結，「開始審核」改緊湊 green 按鈕
- [x] `SuppliersView`：風險分數 bar + 百分比、國家旗幟、tier chip、28×28px icon 操作按鈕
- [x] `AppSidebar`：footer 加用戶頭像圓形 + user-details 排版
- [x] `index.html`：載入 Noto Sans TC / Syne / Fira Code Google Fonts

### Phase 7：前端 — Pinia compareStore + CompareModal

- [x] `src/stores/compareStore.ts`：上限 4 家，`add/remove/clear/canAdd/isAdded()`
- [x] `CompareModal.vue`：並排呈現比較供應商 SAQ + 風險四維度
- [x] `SuppliersView` 整合 CompareModal 觸發
- [x] `RiskMatrixView` 整合比較按鈕觸發 compareStore
