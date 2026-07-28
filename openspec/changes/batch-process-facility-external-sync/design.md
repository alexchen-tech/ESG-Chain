## Context

`batch-process-facility-selection` 完成後，使用者提出：如果未來有外部系統（如碳足跡計算系統、MES）能提供「每個批次實際使用的製程供應商/工廠」，架構上該如何演進？研究既有 ERP 整合架構（`ErpAdapterInterface`/`ErpSyncService`/`ErpWebhookController`）後確認：這類「生產現場已發生的事實」資料，在既有欄位歸屬哲學下應歸屬「ERP/外部系統可覆蓋」類，而非「ESG-Chain 永不被覆蓋」類。這次先把資料模型與同步架構的擴充點設計出來，不含實作。

## Goals / Non-Goals

**Goals：**
- 定調 `batch_process_facilities` 資料歸屬：預設可被外部系統覆蓋，但保護使用者已手動選定的紀錄不被靜默覆蓋
- 設計跟既有 ERP 同步架構一致的擴充點（`ErpAdapterInterface`/`ErpSyncService`/`ErpWebhookController`），未來真的要接外部系統時，不需要推翻既有 pattern 重新設計
- 保留「使用者手動選定」作為外部系統尚未涵蓋的批次/製程類型的 fallback 機制，不因為要支援外部同步就廢除手動選定 UI

**Non-Goals：**
- 不實作任何真實外部系統對接（沒有實際可對接的系統，规格未知）
- 不預先猜測外部系統的 payload 格式（不同碳足跡系統/MES 系統格式差異可能很大，等實際對接時再依規格設計 payload schema/mapping，這次只定義 ESG-Chain 這一側要接住什麼概念）
- 不做「外部系統資料 vs SAQ 風險評分」的交叉驗證（那是 `process-due-diligence-saq-linkage` 的範圍，這次不動它，且已確認它的邏輯不受資料來源影響，不需改動）

## Decisions

**1. 資料歸屬：預設「外部系統可覆蓋」，但已手動選定的紀錄需標記待確認，不靜默覆蓋**

比照現有 ERP 同步的「整欄位白名單」哲學，`batch_process_facilities` 整體歸屬外部系統管理（跟 Supplier code/name 同一類）。但這裡選擇**不完全照搬** `ProductBomLine.material_group_source==='manual'` 時「靜默跳過覆蓋」的既有作法，理由：BOM 物料群組是相對靜態、變動頻率低的分類資訊，靜默保護的風險可控；但製程供應商資料會被碳足跡計算依賴，如果使用者手動選了一個供應商、外部系統之後推送了不同的供應商，靜默跳過會讓 ESG-Chain 顯示的資料長期偏離外部系統認定的事實，而使用者渾然不知。因此設計為：
- 尚無手動選定（`source` 為 null 或 `external`）：外部推送直接覆蓋，`source='external'`、`synced_at`/`external_ref` 更新
- 已有手動選定（`source='manual'`）：外部推送到同一筆（`production_batch_id`+`process_type`）時，**不覆蓋**現有紀錄，但寫入 `conflict_pending=true` 並記錄外部系統提供的建議值（需要新增一個獨立表或 JSON 欄位暫存外部建議值，供使用者比對後決定是否採用——這次設計先留白，實作時再定案暫存方式），前端顯示明確的「系統偵測到不同資料，請確認」提示

**2. 遵循既有 push+pull 雙軌架構，不用二選一**

現有 ERP 整合兩者都支援且共用同一套底層邏輯（`ErpSyncService`），只是觸發方式（排程 vs webhook）與 `source` 標籤不同。這次比照同一模式：
- Pull：`ErpAdapterInterface` 新增 `fetchBatchProcessFacilities(?\DateTimeInterface $since): array`，若外部系統是排程批次匯出型（如碳足跡系統每日匯出報表），走 `ErpScheduledSyncJob` 增量拉取
- Push：`ErpWebhookController` 新增專屬端點（比照 `productionBatch()` 而非通用 `SUPPORTED_ENTITIES` 路徑，因為這筆資料強綁在特定批次上，語意是「批次子資料」而非「主檔實體」），若外部系統是即時推送型（如 MES 完工回報）

兩種擴充點都設計出來，實際對接時依外部系統的技術能力選用其一或兩者並用（例如排程做初次/定期全量校正，webhook 做即時更新）。

**3. `MockErpAdapter` 這次不擴充**

`MockErpAdapter` 目前用途是本地開發/測試時模擬 ERP 回應，這次沒有實際外部系統規格，硬寫一組假的批次製程供應商模擬資料意義不大（容易跟未來真實規格對不上，變成要重寫的技術債），留到有真實對接需求、確定 payload 格式後再一併補上 Mock。

**4. UI 分流：`source=external` 唯讀，`source=manual` 維持現有可編輯**

比照 CLAUDE.md「ERP 欄位唯讀，不可從 UI 修改」的既有慣例，`source='external'` 的製程卡片改為唯讀呈現（顯示廠區/供應商 + 同步時間），不提供「更換」按鈕；使用者若認為外部資料有誤，需要透過對接系統端修正，不在 ESG-Chain 這端手動改（跟供應商主檔欄位的處理原則一致）。`source='manual'` 的卡片維持現有可編輯 UI（`batch-process-facility-selection` 已完成的部分不變）。`conflict_pending=true` 時兩種來源都要顯示待確認提示，UI 細節（用什麼互動讓使用者「採用外部建議值」或「保留手動值」）留待實作階段依實際外部系統資料形狀設計。

## Risks / Trade-offs

- [取捨] `conflict_pending` 機制比 BOM 物料群組的「靜默跳過」複雜，需要額外暫存外部建議值與提示 UI，但考量到這筆資料的下游用途（碳足跡計算依賴），這次判斷這個複雜度是必要的，不是過度設計
- [風險] 目前完全沒有實際外部系統規格，這次設計的 payload/欄位命名（`external_ref` 等）是通用猜測，真的對接時可能需要調整，但擴充點本身（介面新增方法、webhook 新端點、sync service 新方法）是遵循既有 pattern，調整幅度可控
- [取捨] 不擴充 `MockErpAdapter` 代表這次設計無法在本地端跑一次端到端測試驗證架構可行性，只能靠程式碼審查確認介面設計合理；若之後想在真實對接前先驗證架構，可以考慮先做一個簡化版 Mock，但這次判斷非必要，先留白

## Migration Plan

（本次僅為架構設計，不產出可執行的 migration 步驟；待有實際外部系統對接需求時，依本設計文件展開 tasks.md 並實作，屆時再依外部系統真實規格微調欄位設計）

## Open Questions

- `conflict_pending` 時外部建議值該暫存在哪裡（`batch_process_facilities` 本身加 JSON 欄位存「待確認的外部建議值」，還是另建一張 `batch_process_facility_conflicts` 表）？留待實作階段依實際情境決定，這次不預先定案
- 使用者確認衝突後的處置方式（覆蓋採用外部值／保留手動值／兩者都留但標記其中一個為「已停用」）目前未定案，需要在有實際外部系統、能看到真實衝突情境長什麼樣子後，再跟使用者確認業務規則
