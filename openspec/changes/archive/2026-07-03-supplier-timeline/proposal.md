## Why

供應商詳情頁的「風險評估歷史」目前以靜態表格呈現，SAQ 評分與風險矩陣評估是兩條各自獨立的資料流，用戶無法在同一個視圖看到「SAQ 分數下滑 → 風險惡化 → CAP 開立 → 改善效果」的完整因果鏈。同時，跨供應商的橫向比較目前完全缺失，採購與永續團隊只能在腦海中手動對比，嚴重影響決策效率。

## What Changes

- **risk_assessments 加 `source_saq_id` 欄位**（nullable FK → saqs），記錄自動建立時的來源 SAQ
- **RiskAssessmentObserver** auto-create 時填入 `source_saq_id`
- **新 `SupplierTimelineService`**：UNION RiskAssessment + SAQ（有分數）+ 關聯 CAP，回傳時間排序的統一事件流
- **新 API endpoint** `GET /api/v1/suppliers/:id/risk-timeline`
- **SupplierDetailView 風險歷史區塊升級**：table → 事件流 component，三種事件卡片（saq_scored / risk_assessment / cap_linked），最新 SAQ 若仍在 submitted/under_review 則頂端顯示 pending 卡
- **新 Pinia 比較籃 store**（上限 4 家）
- **風險矩陣 panel** 每張廠商卡加「加入比較」按鈕
- **供應商清單** 加 checkbox multi-select 模式與浮動比較列
- **新 CompareModal component**：Modal 形式，並排呈現最多 4 家廠商的 SAQ + 風險四維度

## Capabilities

### New Capabilities

- `supplier-risk-timeline`：統一事件流 API 與前端時間軸 component，整合 SAQ 評分、風險評估、CAP 關聯，呈現因果鏈
- `supplier-compare`：跨供應商橫向比較，Pinia 比較籃 + CompareModal，最多 4 家並排

### Modified Capabilities

- `supplier-detail-overview-tab`：風險評估歷史區塊從靜態 table 改為事件流 component

## Impact

- **資料庫**：`risk_assessments` 加 `source_saq_id` 欄位，需 migration
- **esgchain-api**：新增 `SupplierTimelineService`、`SupplierController::timeline()`、更新 `RiskAssessmentObserver`、新增路由
- **esgchain-web**：新增 `compareStore.ts`、`CompareModal.vue`，修改 `SupplierDetailView.vue`、`RiskMatrixView.vue`、`SuppliersView.vue`
- **無 breaking change**：現有 `/risk/assessments` 和 `/risk/matrix` API 不受影響
