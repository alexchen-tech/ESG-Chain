## Why

生產批號詳情頁「供應鏈合規」分頁把同一份物料資料拆成兩個獨立區塊呈現：「供應鏈合規調查」卡片（唯讀，來自批次護照）與「原物料合規與溯源管理」清單（可編輯，來自 `raw_material_origins`）。使用者要確認某個物料的實際供應商時，必須先在合規調查卡片看到「建議（未確認）」，再捲到下面另一個區塊找同一個物料、再看一次幾乎一樣的原產國/GPS/認證編號資料，才能點「確認」——同一個物料的資訊被講兩次，操作路徑也被迫拆成兩段。

## What Changes

- 「供應鏈合規調查」卡片與「原物料合規與溯源管理」表單合併：每張物料卡片內建「編輯溯源」按鈕，點擊後直接在卡片內就地展開溯源欄位（原產國/GPS/收成年份/認證編號/運輸方式/實際供應商）與「儲存」/「刪除」操作，不再需要跳到另一個區塊尋找同一個物料
- 未對應任何 BOM 物料的自由填寫溯源紀錄（如商業機密考量不連結特定物料），保留為獨立的「其他原料溯源」區塊，維持原本的新增表單
- 「供應鏈製程級地點」維持獨立不動——它是以廠區為單位的視角，跟以物料為單位的合規調查／溯源是兩種不同的資訊，不合併
- 儲存/刪除溯源後，同步重新載入批次護照，讓合規調查卡片的「已選定/建議」狀態與合規文件查核結果即時反映最新的實際供應商

## Capabilities

### Modified Capabilities
- `batch-supply-chain-compliance`：「供應鏈合規調查」與「原物料合規與溯源管理」的操作入口合併，供應商選定/溯源填寫改為卡片內就地編輯，不再是兩個分開的區塊

## Impact

- 前端：`ProductionBatchDetailView.vue`（供應鏈合規分頁樣板重構、`submitOrigin()` 改為依 `originForm.id` 是否存在分流 create/update、新增 `originForBomLine()`/`toggleOriginEdit()`/`toggleUnmappedOriginForm()`/`unmappedOrigins` computed）
- 不影響：後端 API（`RawMaterialOriginController`、`BatchPassportService`）完全不變，純前端整合既有端點的呈現方式
