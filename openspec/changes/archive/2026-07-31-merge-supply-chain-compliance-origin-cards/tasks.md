## 1. 卡片內就地編輯

- [x] 1.1 「供應鏈合規調查」卡片新增「編輯溯源／+ 新增溯源」按鈕，點擊在卡片內就地展開表單（原產國/GPS/收成年份/認證編號/運輸方式/實際供應商）
- [x] 1.2 `originForBomLine()` 新增：取得該 BOM 行此批次現有的溯源紀錄
- [x] 1.3 `toggleOriginEdit()` 新增：開合卡片內編輯表單，預填既有紀錄（若有）
- [x] 1.4 `onOriginBomLineSelected()` 新增 `resetSupplier` 參數，卡片編輯情境呼叫時不清空預填的供應商

## 2. 表單提交邏輯調整

- [x] 2.1 `submitOrigin()` 改為依 `originForm.id` 是否存在分流 create/update
- [x] 2.2 儲存/刪除溯源後呼叫 `loadPassport()`，讓合規調查卡片即時反映最新狀態

## 3. 未對應 BOM 物料區塊

- [x] 3.1 移除獨立的「原物料合規與溯源管理」區塊，改為「其他原料溯源（未對應 BOM 物料）」
- [x] 3.2 `unmappedOrigins` computed 新增：僅列出 `bom_line_id` 為空的紀錄
- [x] 3.3 `toggleUnmappedOriginForm()` 新增：獨立於卡片列表之外的新增表單開合

## 4. 清理與驗證

- [x] 4.1 移除已不再使用的 `showOriginForm`／`goToOriginForm`／`bomLineLabel`／`supplierName` 死程式碼
- [x] 4.2 `vue-tsc` 全專案型別檢查通過
- [x] 4.3 部署至 esgchain-web，觸發 HMR
- [x] 4.4 以真實資料驗證：curl 確認 create/update/delete 三個既有 API 端點行為不變（後端未修改，純前端整合）
