## Why

風險矩陣目前是完全手動的孤島：SAQ 評分結果無法自動反映至矩陣，矩陣格子計數有重複計入 bug，extreme 供應商不會自動觸發矯正行動，且歷史評估紀錄從未被前端消費。四項問題讓風險管理流程缺乏閉環，採購與永續團隊需要手動維護兩套資料。

## What Changes

- **D — 修正 buildMatrix() 重複計入 bug**：同一供應商若有多筆評估且 probability/impact 不同，目前會出現在多個格子；改用 subquery 先取每個 supplier_id 的最新 assessed_at，再取該筆的 probability/impact
- **A — SAQ → RiskAssessment 自動推導**：`scoreCallback()` 完成後自動建立 RiskAssessment；E/S/G probability = `ceil((100 - score_dim) / 20)`（最小值 1），impact 預設 3；GP 維持手動；建立後使用者可在風險矩陣頁或供應商詳情頁自行編輯
- **B — extreme 自動開 CAP**：`RiskAssessment::created` observer 偵測任一維度 cell_score ≥ 20，自動建立 CAP（`source_type='risk_assessment'`），`findings` 填入所有 extreme 維度名稱與 cell_score
- **C — 風險趨勢歷史區塊**：供應商詳情頁（overview tab）新增風險歷史時間軸，列出每次 RiskAssessment 的各維度 cell_score 與 risk_level，支援 delta 比較（本次 vs 上次）

## Capabilities

### New Capabilities

- `saq-to-risk-auto-derivation`：SAQ 評分完成後自動建立 RiskAssessment 的規則與換算邏輯
- `risk-extreme-cap-trigger`：RiskAssessment extreme 維度自動觸發 CAP 的規則
- `supplier-risk-history`：供應商詳情頁風險歷史趨勢顯示

### Modified Capabilities

- `supplier-detail-overview-tab`：新增風險歷史區塊

## Impact

- **後端**：`SAQController::scoreCallback()`、新增 `RiskAssessmentObserver`、`RiskMatrixController::buildMatrix()`
- **前端**：`SupplierDetailView.vue`（overview tab 新增風險歷史）、`RiskMatrixView.vue`（支援手動編輯已自動建立的 RiskAssessment）
- **無 breaking change**：自動建立的 RiskAssessment 與手動建立相同格式，UI 僅新增顯示區塊
