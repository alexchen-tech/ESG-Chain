## Why

現行 SAQ 問卷以單一總分評估供應商，無法同時滿足「跨框架合規語言（ISO 20400/26000/28000）」與「跨供應商可比分析」兩個需求。不同框架的問卷分數互不相容，導致風險矩陣的推導品質低落，採購決策缺乏一致基準。本變更導入六維評估架構，以單一問卷作答同時輸出六個維度分數，並以外部資料混合計算地緣與產品合規維度，使評估結果既具框架深度又保留跨供應商可比性。

## What Changes

- **新增六維分數體系**：E1 ESG整體、E2 ISO 20400、E3 ISO 26000、E4 地緣風險、E5 ISO 28000、E6 產品合規，取代現有單一 `score` + 三軸設計
- **題庫多重標記（Multi-Tag）**：每道題可同時帶多個 framework tag 與對應 weight，一次作答貢獻多個維度分數
- **產業自動模組加掛**：依 `supplier.industry_group` 自動決定問卷包含哪些維度模組（E2/E3/E5/E6 為選配，E1/E4 全體必有）
- **E4/E6 混合計分**：地緣風險（E4）混合 `country_risk_ratings` + 問卷回答；產品合規（E6）混合 `SalesProduct.applicable_regulations` + 動態篩題
- **動態篩題機制**：發送問卷前依供應商外部資料決定 E6 適用題集，只問「真正相關」的合規題目
- **100% 必答強制**：系統層面阻擋未完整作答的提交；逾期未提交觸發 SLA 標記
- **六維 → 四軸推導**：六維分數自動投影為現有 E/S/G/GP 風險矩陣（probability × impact），保留決策視角
- **risk_assessments 擴充**：新增 `dim_e1`–`dim_e6` 欄位儲存六維分數；現有四軸欄位改為系統計算填入，不接受手動輸入（**BREAKING**：移除手動評估 API）

## Capabilities

### New Capabilities

- `six-dimension-scoring`: 六維評估計分引擎，多重標記題庫設計，單次作答輸出 E1–E6 六個維度分數
- `industry-module-assignment`: 依供應商產業分類自動加掛評估模組，決定問卷題集與維度覆蓋範圍
- `hybrid-dimension-scoring`: E4（地緣風險）與 E6（產品合規）的混合計分邏輯，結合問卷回答與外部資料
- `dynamic-question-filtering`: 問卷發送前依供應商外部資料動態篩選 E6 合規題集
- `six-dim-to-risk-matrix`: 六維分數投影為 E/S/G/GP 四軸風險矩陣的推導規則引擎

### Modified Capabilities

- `saq-scoring-engine`: 計分引擎從單一分數輸出改為六維並行輸出，需支援 per-framework weight
- `saq-to-risk-auto-derivation`: 風險推導邏輯從三軸改為從六維投影，E4 加入外部資料混合計算
- `supplier-risk-history`: 風險歷史顯示從三軸擴充為六維，依供應商適用維度動態顯示
- `questionnaire-template-management`: 模板管理需支援「核心題 + 加掛模組」的組合結構
- `question-tag-library`: 題目標記從單一 compliance_domain 擴充為多框架 multi-tag + per-framework weight

## Impact

- **esgchain-ai**：SAQ 計分 Celery task 改為多維度輸出；新增 E4/E6 混合計分 task（需讀取 MySQL 的 country_risk_ratings 與 SalesProduct）
- **esgchain-api**：`risk_assessments` 表新增 dim_e1–dim_e6 欄位；`POST /api/v1/risk/assessments` 已封閉（手動評估已移除）；問卷發送流程加入動態篩題邏輯
- **esgchain-web**：供應商詳情頁風險歷史改為六維顯示；問卷審核頁顯示各維度分數
- **資料庫**：`saq_questions` 表的 `tags` / `compliance_domains` 需重新設計為 per-framework weight 結構；`risk_assessments` 新增六維欄位
- **現有 SAQ 資料**：現有問卷分數（score/score_e/score_s/score_g）保留，六維分數為新增欄位，不破壞歷史記錄
