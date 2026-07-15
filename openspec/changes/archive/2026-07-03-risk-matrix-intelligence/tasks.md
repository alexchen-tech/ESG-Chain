## 1. D — 修正 buildMatrix() 重複計入 bug

- [x] 1.1 修改 `RiskMatrixController::buildMatrix()`：改用 `joinSub` 先取每個 supplier_id 的最新 `assessed_at`，再 JOIN 取得該筆的 probability/impact，確保每個供應商只計入一個格子
- [x] 1.2 驗證：在 DB 手動建立同一供應商兩筆不同 p/i 的 RiskAssessment，確認矩陣只計入最新一筆

## 2. A — RiskAssessment 新增/編輯 API

- [x] 2.1 `RiskAssessmentController` 新增 `update()` 方法（PATCH `/api/v1/risk/assessments/{id}`），允許更新所有維度的 probability/impact 與 notes
- [x] 2.2 在 `api.php` 註冊對應路由

## 3. A — SAQ → RiskAssessment 自動推導服務

- [x] 3.1 新增 `RiskAutoDerivationService`（`app/Services/Risk/`）：接收 SAQ model，計算 E/S/G probability，建立 RiskAssessment
- [x] 3.2 換算邏輯：`probability = max(1, (int) ceil((100 - score_dim) / 20))`，impact = 3；score_dim 為 null 時跳過該維度；全為 null 時不建立
- [x] 3.3 在 `SAQController::scoreCallback()` 尾端呼叫 `RiskAutoDerivationService::deriveFromSaq($saq)`

## 4. B — RiskAssessmentObserver extreme → CAP

- [x] 4.1 新增 `RiskAssessmentObserver`（`app/Observers/`）：`created()` 偵測 extreme 維度（cell_score ≥ 20，跳過 null 值維度），防重複後建立 CAP + CAPFindings
- [x] 4.2 在 `AppServiceProvider::boot()` 中註冊 Observer：`RiskAssessment::observe(RiskAssessmentObserver::class)`
- [x] 4.3 驗證：建立一筆有 extreme 維度的 RiskAssessment，確認 CAP 自動出現；重複建立相同 source_id 不重複開 CAP

## 5. C — 前端：供應商詳情頁風險歷史區塊

- [x] 5.1 在 `SupplierDetailView.vue`（或其 overview tab component）新增「風險評估歷史」卡片，呼叫 `GET /api/v1/risk/assessments?supplier_id={id}&per_page=10`
- [x] 5.2 列表顯示：assessed_at、E/S/G/GP cell_score badge（probability × impact）、整體最高 risk_level
- [x] 5.3 delta 標記：比較相鄰兩筆同維度 cell_score，顯示 ↑↓ 與差值
- [x] 5.4 自動推導 badge：notes 含「自動從 SAQ」時顯示「自動」chip
- [x] 5.5 空狀態：無 RiskAssessment 時顯示提示文字與「前往風險矩陣」連結
- [x] 5.6 docker cp 前端檔案並確認 Vite HMR 觸發

## 6. 端對端驗證

- [x] 6.1 完整流程驗證：送出 SAQ → 評分完成 → 確認自動建立 RiskAssessment（E/S/G probability 符合換算規則）
- [x] 6.2 extreme CAP 驗證：讓自動建立的 RiskAssessment 有 extreme 維度 → 確認 CAP 自動建立含正確 Findings
- [x] 6.3 前端歷史區塊驗證：開啟供應商詳情頁確認歷史列表顯示，delta 標記正確
- [x] 6.4 buildMatrix() 驗證：風險矩陣各格子供應商數量合計 = 有 RiskAssessment 的供應商總數（不重複）
