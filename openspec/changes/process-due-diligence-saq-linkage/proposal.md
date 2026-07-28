## Why

`batch-process-facility-selection` 讓批次可以選定「某製程類型的實際供應商」，但選定之後系統沒有對這個選定做任何盡職調查判斷。染整製程牽涉環境足跡（廢水/化學品）、成衣縫製製程牽涉勞動條件（童工/工時），這些主題其實已經被既有六維風險評分（`RiskAssessment.dim_e1` 環境管理、`dim_e3` 社會責任）與細顆粒問卷標籤（`question_tags` 如 `esg.s.forced_labor`/`esg.s.child_labor`/`esg.e.water`）涵蓋，不需要另建一套平行的文件類型與檢查邏輯——只需要把「批次選定的製程供應商」跟「該供應商既有的 SAQ 風險評分」串起來即可。

## What Changes

- 新增「製程類型 → 六維風險構面」對應（染整/濕製程/印花 → 環境管理 `dim_e1`；成衣縫製 → 社會責任 `dim_e3`；其餘製程類型不觸發此檢查）
- 新增 `BatchProcessDueDiligenceService`：針對批次已選定的製程供應商，查詢該供應商最新一筆 `RiskAssessment` 對應構面的分數與風險等級，回傳「已評估（風險等級）」/「尚未完成評分」兩種狀態
- 新增唯讀 API `GET production-batches/{batchId}/process-due-diligence`
- 批次護照（`passport`）新增 `process_due_diligence` 區塊
- 生產批號詳情頁「供應鏈製程級地點」卡片，已選定供應商旁邊顯示對應構面風險等級徽章（環境/社會責任），並提供連結導向該供應商詳情頁查看完整 SAQ/風險資料

## Capabilities

### New Capabilities
- `process-due-diligence-saq-linkage`：批次製程供應商與既有 SAQ 六維風險評分的串接查詢

## Impact

- 後端：新增 `BatchProcessDueDiligenceService`、`BatchProcessFacilityController` 或新 controller 新增 `dueDiligence()` action、`routes/api.php` 新增路由、`BatchPassportService::build()` 新增區塊
- 前端：`ProductionBatchDetailView.vue` 製程卡片新增風險徽章、`productionBatch.ts` 新增型別與 API
- 不影響：既有 SAQ/風險評分邏輯本身（純讀取既有 `RiskAssessment`，不新增評分機制）、既有出口市場審查（這次不併入 `BatchExportReview`，是獨立的批次層級盡職調查資訊，不綁市場）
- 明確排除範圍：不新增文件類型（ZDHC/SA8000等）、不新增問卷或評分邏輯、不做「風險等級過高就阻擋出口審查」的關卡串接（那需要另外討論是否要把這個資訊納入 `gateCheck()` 的 blocked 判斷，這次先做唯讀呈現）
