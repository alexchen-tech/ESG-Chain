## Context

「供應鏈合規調查」卡片（`passport.supply_chain_compliance`，BOM 行驅動，唯讀）與「原物料合規與溯源管理」（`batch.raw_material_origins`，可編輯的 CRUD 清單）原本各自對應不同 API：前者來自 `BatchPassportService::buildSupplyChainCompliance()`，後者來自 `RawMaterialOriginController`。兩者的物料清單邏輯上是同一份（都源自產品 BOM），只是一個是彙總視圖、一個是原始資料，UI 卻拆成兩段。

## Goals / Non-Goals

**Goals:**
- 同一個物料的「合規調查狀態」與「溯源／實際供應商編輯」在同一張卡片內完成，不需要跨區塊尋找
- 未對應 BOM 物料的自由填寫溯源紀錄仍保留獨立入口（無法比照卡片模式，因為沒有 `bom_line_id` 可對應哪張卡片）
- 儲存/刪除後合規調查卡片即時反映最新狀態

**Non-Goals:**
- 不修改後端 API（`RawMaterialOriginController`、`BatchPassportService`）與資料模型
- 不合併「供應鏈製程級地點」——它是廠區視角，跟物料視角是不同維度的資訊
- 不支援「一個 BOM 行對應多筆溯源紀錄」的編輯（沿用既有假設：一個 BOM 行此批次只有一筆溯源），若未來有多筆需求需另外設計

## Decisions

**1. 卡片內就地編輯，不用 Modal**

比照系統既有慣例（如供應商清單、物料清單的行內編輯），編輯表單直接在卡片內展開/收合，不彈 Modal——這批卡片通常一次要處理好幾個物料，Modal 會打斷「看一個、編一個、看下一個」的連續操作流程。

**2. `submitOrigin()` 依 `originForm.id` 是否存在分流 create/update**

`toggleOriginEdit()` 開啟編輯時，若該 BOM 行已有溯源紀錄，把 `originForm.id` 設為該筆記錄的 id 並預填所有欄位；若尚無記錄，`id` 留空。`submitOrigin()` 依此決定呼叫 `rawMaterialOriginApi.update()` 或 `.create()`，同一個方法同時服務「新增」與「編輯」兩種情境，不需要為卡片內編輯另外寫一個方法。

**3. `onOriginBomLineSelected()` 新增 `resetSupplier` 參數**

既有邏輯在使用者手動切換「對應 BOM 物料」下拉選單時，會清空已選的供應商（因為換了物料，原本選的供應商不再適用）。但卡片內編輯是「載入既有記錄的供應商核可清單」情境，此時不應該清空已預填的 `supplier_id`。新增布林參數（預設 `true` 保留原本手動切換的行為，卡片編輯呼叫時傳 `false`）避免兩種情境誤觸同一段清空邏輯。

**4. 未對應 BOM 物料的紀錄維持獨立區塊**

自由填寫的溯源紀錄沒有 `bom_line_id`，無法對應到任何一張合規調查卡片，因此保留原本「原物料合規與溯源管理」的新增表單模式，改名為「其他原料溯源（未對應 BOM 物料）」，維持獨立於卡片列表之外。

## Risks / Trade-offs

- [風險] 卡片內嵌表單會讓單張卡片變得較長，物料數量多的產品（如成衣類常見 10+ 項 BOM）展開多張編輯表單時頁面會變長 → 緩解：一次僅允許一張卡片處於編輯狀態（`editingBomLineId` 為單一值，非陣列），切換編輯其他卡片會自動收合前一張

## Migration Plan

1. `ProductionBatchDetailView.vue`：「供應鏈合規調查」卡片內新增就地編輯表單（原產國/GPS/收成年份/認證編號/運輸方式/實際供應商 + 儲存/刪除）
2. 移除獨立的「原物料合規與溯源管理」區塊，改為「其他原料溯源（未對應 BOM 物料）」，僅列出 `bom_line_id` 為空的紀錄
3. `submitOrigin()` 改為依 `originForm.id` 分流 create/update；儲存/刪除後呼叫 `loadPassport()` 重新整理合規調查卡片狀態
4. `onOriginBomLineSelected()` 新增 `resetSupplier` 參數，卡片編輯情境呼叫時傳 `false` 避免清空預填的供應商
5. 部署後以真實資料驗證：卡片內編輯溯源儲存成功後，合規調查狀態（已選定/建議）與合規文件查核結果即時更新；未對應 BOM 物料的紀錄仍可透過獨立區塊新增/顯示

## Open Questions

（無）
