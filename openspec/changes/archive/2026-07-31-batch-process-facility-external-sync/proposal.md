## Why

`batch-process-facility-selection` 讓使用者可以手動選定「這個批次某製程類型的實際供應商/廠區」，但這本質上是「生產現場已發生的事實」，理論上應該由外部系統（如碳足跡計算系統、MES 生產執行系統）提供，而不是靠使用者事後回憶手動選。這次先把「外部系統可以推送/同步這筆資料」的架構設計定案，為未來真的有外部系統要對接時預留擴充點，避免屆時要推翻現有手動選定 UI 重做。這次僅做架構預留與設計，不含實作（目前沒有實際可對接的外部系統，先以既有 ERP 整合架構為範本設計，待有真實對接需求時再依此設計實作）。

## What Changes

- `batch_process_facilities` 表新增 `source`（`manual`/`external`，比照 `ProductBomLine.material_group_source` 的既有先例）與 `synced_at`/`external_ref` 欄位，區分「使用者手動選定」與「外部系統推送」的資料來源
- 資料歸屬定調：製程供應商事實資料比照 Supplier/Material 主檔（ERP/外部系統可覆蓋），不比照 `onboarding_stage`/`risk_level`（ESG-Chain 永不被覆蓋）；但已有使用者手動選定（`source=manual`）的紀錄，外部系統推送時 SHALL NOT 靜默覆蓋，須標記待確認而非直接覆蓋（跟現有 BOM 物料群組「靜默跳過」的保護方式不同，因為這筆資料會被碳足跡計算依賴，靜默跳過會導致長期資料飄移不被使用者發現）
- `ErpAdapterInterface` 新增 `fetchBatchProcessFacilities()` 方法（pull 模式擴充點）
- `ErpWebhookController` 新增專屬 webhook 端點（push 模式擴充點，比照既有 `productionBatch()` 專屬端點的驗簽/驗證模式，不走通用 `SUPPORTED_ENTITIES` 路徑）
- `ErpSyncService` 新增 `syncBatchProcessFacilities()`，處理欄位覆蓋與衝突標記邏輯
- 前端：`source=external` 的製程卡片呈現為「系統同步」樣式（比照 ERP 管理欄位「唯讀，不可從 UI 修改」的既有慣例），衝突待確認時顯示明確提示

## Capabilities

### New Capabilities
- `batch-process-facility-external-sync`：批次製程供應商資料的外部系統同步能力（pull + push 兩種擴充點）

## Impact

- 資料庫：`batch_process_facilities` 新增 `source`/`synced_at`/`external_ref` 欄位，新增 `conflict_pending`（bool，標記外部推送與既有手動選定不一致待確認）欄位
- 後端：`ErpAdapterInterface`、`MockErpAdapter`、`ErpSyncService`、`ErpWebhookController`、`ErpScheduledSyncJob`（如採 pull 模式）
- 前端：`ProductionBatchDetailView.vue` 製程卡片樣式分流（manual 可編輯 vs external 唯讀+同步時間）、`productionBatch.ts` 型別新增 `source`/`conflict_pending` 欄位
- 不影響：`BatchProcessDueDiligenceService`（已確認完全不關心資料來源，只讀 `confirmed`/`selected`，不需改動）、既有出口市場審查與批次護照邏輯
- 明確排除範圍：本次不實作任何真實外部系統對接（沒有實際可對接的系統），不實作 `MockErpAdapter` 的假資料模擬，僅完成架構設計與資料模型定案；待有實際外部系統（如使用者提及的「14067系統」）確定對接規格後，再依本次設計產出 tasks.md 並實作
