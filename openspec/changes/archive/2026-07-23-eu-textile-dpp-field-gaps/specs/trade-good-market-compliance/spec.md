## ADDED Requirements

### Requirement: 出口審查 DPP 強制欄位完整度檢查
`BatchExportReviewService::checkDppFields()`（僅 EU market 觸發）SHALL 逐項檢查以下 EU 紡織品 DPP 最小強制類別的資料完整度，並各自產出一筆結構化 finding（比照既有逐份文件 finding 格式，非單一字串）：

1. 有害物質揭露：呼叫有害物質即時判定服務，若判定結果存在且非未解除風險則視為已揭露
2. 微纖維釋放風險：批次產品所涉 BOM 物料的 `microfiber_release_risk` 是否至少有一筆非 `not_rated`
3. 包材資訊：批次產品是否已建立 `product_packagings` 紀錄
4. 供應鏈製程級地點：批次產品的 TradeGoodSupplier 是否至少有一筆 `supplier_facility_id` 非 null
5. 再生料比例/可回收性：批次產品最新 ProductCircularitySnapshot 是否 `data_ready = true`
6. 運輸資訊：批次的 RawMaterialOrigin 是否至少有一筆已填寫 `transport_mode`

任一項缺資料時，該項 finding 標記為 `warning`（非阻擋性，因這些為選填揭露欄位，尚非法定強制門檻）；全部具備時該項標記為 `pass`。

#### Scenario: EU 市場批次六項皆缺失
- **WHEN** 對某批次執行 EU market 出口審查，其產品六項 DPP 欄位皆未填寫
- **THEN** findings 包含六筆各自標記 `warning` 的 finding，整體審查狀態依既有 fail/warning 判定邏輯至少為 `warning`

#### Scenario: EU 市場批次六項皆具備
- **WHEN** 對某批次執行 EU market 出口審查，其產品六項 DPP 欄位皆已填寫且有害物質判定為無風險
- **THEN** findings 包含六筆各自標記 `pass` 的 finding

#### Scenario: 非 EU 市場不觸發此檢查
- **WHEN** 對某批次執行 US 或其他非 EU market 的出口審查
- **THEN** 不產出前述六項 DPP 欄位相關 finding
