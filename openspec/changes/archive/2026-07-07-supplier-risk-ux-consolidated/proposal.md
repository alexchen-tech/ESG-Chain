## Why

ESG·Chain 的供應商風險管理流程存在四條斷鏈：

1. **SAQ 評分與風險評估分離**：問卷計分後，E/S/G 維度分數不自動反映到風險矩陣；採購與永續團隊需手動維護兩套資料，「SAQ 分數下滑 → 風險惡化 → CAP 開立 → 改善效果」的因果鏈完全不可見。
2. **風險矩陣重複計入 bug**：同一供應商若有多筆不同 probability/impact 的評估，會出現在多個格子，導致格子計數失真。
3. **Extreme 風險無強制閉環**：某維度達到 extreme 等級後，系統不主動開立 CAP，完全依賴人工發現與跟進。
4. **UI 分散無法橫向比較**：供應商詳情頁風險歷史以靜態表格呈現，跨供應商比較完全缺失，頁面視覺層次不足，資訊密度低。

## What Changes

### 後端（esgchain-api）

- **risk_assessments 加 `source_saq_id`**（nullable FK → saqs）：精確追蹤自動建立時的來源 SAQ
- **RiskAutoDerivationService**：SAQ `scoreCallback()` 完成後，以 `probability = ceil((100 - score_dim) / 20)`, `impact = 3` 自動建立 RiskAssessment（E/S/G 三維；GP 不自動）
- **RiskAssessmentObserver**：`created()` 偵測任一維度 cell_score ≥ 20，自動建立 CAP（`source_type='risk_assessment'`），附 CAPFinding per extreme 維度；同時填入 `source_saq_id`
- **buildMatrix() 修正**：改用 joinSub 先取每個 supplier_id 的最新 assessed_at，消除重複計入
- **SupplierTimelineService + GET /api/v1/suppliers/:id/risk-timeline**：UNION RiskAssessment + SAQ（有分數）+ 關聯 CAP，回傳時間排序統一事件流，附 `pending_saq` 欄位
- **DemoEnhancedSeeder**：20 個跨國供應商（TW/VN/CN/TH/IN/ID/MY/KR/BD），每家有 2-3 筆歷史 SAQ + RiskAssessment + source_saq_id 關聯，高風險供應商附 CAP

### 前端（esgchain-web）

- **SupplierDetailView — 新「風險歷史」Tab**：獨立為第二個 Tab，採雙欄佈局（左：SAQ 計分；右：風險評估），不再藏在概況 Tab 底部
- **SupplierDetailView — 三種事件卡片**：`saq_scored`（藍色左框）、`risk_assessment`（橙色左框）、`pending_saq`（黃色頂端卡），呈現因果鏈
- **SupplierDetailView — UI 全面優化**：頁頭 meta chips（code/country/tier）、Tab 字型 14px、Section 標題 15px 加左側 accent 線條、detail-item 行分隔線、label 11.5px / value 14px 600
- **RiskMatrixView — 廠商卡重構**：`sc-head-right` 區塊整合狀態 badge + 26×22px 比較按鈕；E/S/G/GP dim 箱改為 `#f7f5f1` 背景；legend 改為橫式色條（5 段等寬 flex band）；summary-bar 加背景容器
- **Pinia compareStore**：上限 4 家，`add/remove/clear/canAdd/isAdded()`；從風險矩陣 panel 觸發
- **SuppliersView 表格優化**：移除序號欄、加入風險分數 bar + 百分比、國家旗幟 emoji、tier chip；操作改為 28×28px icon 按鈕
- **QuestionnaireView 表格優化**：colgroup 重新分配（`#` 40px、所屬專案彈性填滿），「查看」改邊框鏈結、「開始審核」改緊湊 green 按鈕
- **AppSidebar 優化**：menu icon 重新設計、footer 加用戶頭像圓形 + user-details 排版
- **Google Fonts 載入**：index.html 加入 Noto Sans TC / Syne / Fira Code preconnect + link

## Capabilities

### New Capabilities

- `supplier-risk-timeline`：統一事件流 API + 前端雙欄時間軸 component（因果鏈可視化）
- `saq-to-risk-auto-derivation`：SAQ 評分完成自動建立 RiskAssessment（E/S/G）
- `risk-extreme-cap-trigger`：Extreme 維度自動建立 CAP + CAPFinding
- `supplier-compare`：Pinia 比較籃（上限 4 家）+ 從風險矩陣 panel 觸發

### Modified Capabilities

- `supplier-detail-overview-tab`：識別資訊 grid 加行分隔線，label/value 字型調整，標題加左側線條
- `supplier-detail-risk-tab`（新）：獨立 Tab，雙欄事件流
- `risk-matrix-view`：廠商卡重構、legend 橫式色條、summary-bar 優化
- `suppliers-list`：風險分數欄位、國家旗幟、tier chip、操作 icon 按鈕
- `questionnaire-review-list`：表格列寬優化、操作按鈕重設計
- `app-sidebar`：icon 更新、footer user info

### Fixed Bugs

- `buildMatrix()` 重複計入 bug（同供應商多筆評估）

## Impact

- **資料庫**：`risk_assessments` 加 `source_saq_id CHAR(36) NULL`，需 migration
- **esgchain-api**：新 `SupplierTimelineService`、`RiskAutoDerivationService`、`RiskAssessmentObserver`；修改 `scoreCallback()`、`buildMatrix()`；新路由 `/api/v1/suppliers/:id/risk-timeline`
- **esgchain-web**：`compareStore.ts`（新）、`SupplierDetailView.vue`、`RiskMatrixView.vue`、`SuppliersView.vue`、`QuestionnaireView.vue`、`AppSidebar.vue`、`components.css`、`index.html`
- **無 breaking change**：現有 `/risk/assessments`、`/risk/matrix` API 不受影響
