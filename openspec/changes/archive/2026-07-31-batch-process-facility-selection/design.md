## Context

出口審查稽核（`export-review-flow-audit-fixes`）發現「供應鏈製程級地點」目前是 product 層級（列出全部核可供應商 facility，不分批次），使用者接著提出「製程導向盡職調查」的構想（染整→環境足跡、成衣縫製→勞動條件），並指出這需要先有「批次×製程→實際供應商」的選定機制才能繼續，因為目前製程資料完全沒有批次粒度，無從得知某批貨的染整實際在哪個工廠做。

物料本身沒有定義「需要哪些製程」，只有供應商自行標注 `SupplierFacility.facility_type`。使用者確認：「該批次相關製程」由該批次 BOM 涉及的核可供應商 `facility_type` 聯集決定（而非新增製程需求欄位，也非讓使用者自由新增不受限的製程項目）。

## Goals / Non-Goals

**Goals：**
- 讓使用者可以針對「這個批次 BOM 涉及的製程類型」（如染整、成衣製造），逐一選定實際執行的供應商/廠區
- 製程候選供應商清單，來自該批次 BOM 物料的核可供應商中，`facility_type` 符合該製程的那些供應商（不是任意供應商都能選）
- 一個批次的一個製程類型只能有一筆選定（不做「同一製程多廠區分批執行」的複雜情境，那需要再細分到 BOM 行或數量，超出這次範圍）
- `BatchPassportService::buildProcessLocations()` 改為呈現批次已選定資料，未選定的製程類型清楚標示「待選定」

**Non-Goals：**
- 不做「染整→環境文件」「成衣縫製→勞動文件」等製程導向合規檢查邏輯與新 doc_type（下一階段的事，這次只做選定機制）
- 不新增「物料所需製程類型」欄位——製程清單完全從核可供應商的 `facility_type` 聯集推導，不做物料侧的製程需求定義
- 不支援同一製程類型在同一批次內選多筆（如染整分兩個廠各做一部分），維持跟 `RawMaterialOrigin` 一樣「一個維度一筆」的簡單模型

## Decisions

**1. 新增獨立表 `batch_process_facilities`，不擴充 `RawMaterialOrigin`**

`RawMaterialOrigin` 是「批次×BOM行→供應商」（物料溯源，縱向、跟隨物料），這次要做的是「批次×製程類型→供應商」（製程執行，橫向、跨物料，一個製程類型可能對應多筆 BOM 行/多個物料）。兩者維度不同，硬塞進同一張表會讓 `bom_line_id` 變成可為 null 且語意混淆，故新增獨立表，唯一鍵 `(production_batch_id, process_type)`。

**2. 製程類型清單來源：批次涉及物料的核可供應商 `facility_type` 聯集**

沿用 `ProductUpstreamResolver::effectiveSuppliersByLine()` 已有的「BOM 行→核可供應商」推導邏輯，新增方法取得這批 BOM 涉及的全部核可供應商，把有填 `facility_type` 的都聯集起來（去重），作為「這個批次相關的製程類型」清單。每個製程類型底下的候選供應商，就是 `facility_type` 符合的那些核可供應商（同一供應商可能同時出現在多個製程類型底下，如果他有多筆 `MaterialItemSupplier` 對應不同 facility）。

**3. `buildProcessLocations()` 改寫邏輯**

- 先算出批次相關製程類型清單（決策 2）
- 每個製程類型，查 `BatchProcessFacility` 是否已選定：有 → 顯示選定的供應商/廠區，標記 `confirmed: true`；沒有 → 顯示 `confirmed: false`、`facility_name: null`，但仍列出候選供應商清單（`candidate_suppliers`）供前端下拉選擇
- 這個改寫會讓「供應鏈製程級地點」從目前的「列出所有核可供應商 facility（可能同一製程重複列多筆）」變成「每個製程類型一筆，狀態為已選定或待選定」，畫面資訊量會減少但語意正確

**4. API 設計比照 `RawMaterialOrigin` 慣例**

- `GET production-batches/{batchId}/process-facilities`：回傳批次相關製程類型清單（含候選供應商、目前選定狀態）
- `POST production-batches/{batchId}/process-facilities`：新增/更新（`updateOrCreate` by `production_batch_id`+`process_type`）某製程類型的選定供應商
- `DELETE production-batches/{batchId}/process-facilities/{id}`：清除選定（回到「待選定」狀態）

## Risks / Trade-offs

- [取捨] 「一個製程類型一筆選定」無法表達「染整分兩個廠各做一部分」的情境，若未來有此需求，需要把維度細分到 BOM 行或物料群組層級（屬於下一階段擴充，先用簡單模型驗證流程）
- [風險] `buildProcessLocations()` 改寫後，既有依賴「列出全部核可供應商 facility」畫面資訊量的使用情境（如果有的話）會看到資訊變少，需要在前端明確標示「待選定」讓使用者知道可以去選，不是資料消失
- [取捨] 候選供應商清單限定「`facility_type` 已經等於該製程類型」的核可供應商，若某供應商實際上能做這個製程但尚未在系統裡登記對應的 `facility_type`，使用者會選不到——這是刻意的限制（避免亂選），使用者需要先去供應商/物料管理頁補登記 `MaterialItemSupplier` 的 `supplier_facility_id`

## Migration Plan

1. Migration：新增 `batch_process_facilities` 表
2. `BatchProcessFacility` model
3. `ProductUpstreamResolver` 新增「批次相關製程類型清單（含候選供應商）」推導方法
4. `BatchProcessFacilityController`（index/store/destroy）+ `routes/api.php` 新增三條路由
5. `BatchPassportService::buildProcessLocations()` 改寫為批次層級呈現
6. 前端：`ProductionBatchDetailView.vue` 供應鏈合規分頁新增「製程實際供應商」inline-edit 區塊；`productionBatch.ts` 新增型別與 API 方法
7. 部署後以真實資料驗證：批次 BOM 涉及染整+成衣製造兩種製程時，畫面正確列出兩個製程各自的候選供應商；選定後 `passport` 的 `process_locations` 正確反映

## Open Questions

（無，待實作階段如發現新問題再補充）
