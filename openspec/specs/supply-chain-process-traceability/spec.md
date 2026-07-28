### Requirement: 供應商廠區製程分類

SupplierFacility 的 `facility_type` 值域 SHALL 擴充涵蓋紡織關鍵製程：`weaving`（織布）、`knitting`（針織）、`dyeing`（染整）、`printing`（印花）、`wet_processing`（濕製程）、`garment_assembly`（成衣製造），並保留既有 `manufacturing`/`warehouse`/`office`/`other` 以維持既有資料相容性。

#### Scenario: 新增染整廠區

- **WHEN** 使用者為某供應商新增廠區，`facility_type` 選擇 `dyeing`
- **THEN** 系統儲存該廠區為染整類型

#### Scenario: 既有廠區資料不受影響

- **WHEN** 系統查詢既有 `facility_type = 'manufacturing'` 的廠區紀錄
- **THEN** 該紀錄維持不變，可正常讀取

### Requirement: 產品-供應商關聯可指定製程廠區

TradeGoodSupplier（產品與供應商的關聯）SHALL 新增可選的 `supplier_facility_id` 欄位，指向該供應商底下的特定廠區，使同一產品可透過多筆 TradeGoodSupplier 紀錄表達不同製程分別發生在不同廠區/地點。

#### Scenario: 產品指定染整與織布分屬不同廠區

- **WHEN** 某產品建立兩筆 TradeGoodSupplier 紀錄，一筆 `supplier_facility_id` 指向染整廠區、另一筆指向織布廠區
- **THEN** 系統可分別查得該產品染整與織布各自的廠區地點

#### Scenario: 產品-供應商關聯未指定廠區

- **WHEN** TradeGoodSupplier 紀錄的 `supplier_facility_id` 為 null
- **THEN** 系統視為「僅知道供應商，不知道具體廠區/製程」，不視為錯誤
