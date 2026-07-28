## ADDED Requirements

### Requirement: 批次相關製程類型推導
系統 SHALL 根據生產批號所屬產品的 BOM 明細涉及的核可供應商 `facility_type` 聯集，推導出「該批次相關的製程類型」清單，不得要求物料本身額外定義所需製程類型。

#### Scenario: BOM 涉及多種製程類型的供應商
- **WHEN** 某批號的產品 BOM 有物料的核可供應商 `facility_type` 為染整、另一物料的核可供應商 `facility_type` 為成衣製造
- **THEN** 系統 SHALL 回傳「染整」與「成衣製造」兩個製程類型作為該批次相關製程

#### Scenario: 核可供應商未填製程類型
- **WHEN** 某物料的核可供應商在 `MaterialItemSupplier` 未關聯任何 `supplier_facility_id`，或關聯的 `SupplierFacility` 無法判定 `facility_type`
- **THEN** 該供應商 SHALL NOT 出現在任何製程類型的候選清單中

### Requirement: 製程實際供應商選定
系統 SHALL 提供 API 讓使用者針對批次相關的每個製程類型，從該製程類型的候選供應商清單中選定一個實際執行的供應商/廠區；候選清單 SHALL 僅包含 `facility_type` 與該製程類型相符的核可供應商。

#### Scenario: 查詢批次製程清單
- **WHEN** 使用者查詢某批號的製程清單
- **THEN** 系統 SHALL 回傳每個相關製程類型的候選供應商清單，以及目前是否已選定（`confirmed`）與已選定的供應商/廠區資訊

#### Scenario: 選定製程供應商
- **WHEN** 使用者對某製程類型選定一個候選供應商並送出
- **THEN** 系統 SHALL 儲存該筆選定（`production_batch_id`+`process_type` 唯一），若先前已有選定紀錄則覆蓋更新

#### Scenario: 選定非候選清單內的供應商
- **WHEN** 使用者嘗試選定一個 `facility_type` 不符合該製程類型的供應商
- **THEN** 系統 SHALL 拒絕該請求並回傳驗證錯誤

#### Scenario: 清除選定
- **WHEN** 使用者清除某製程類型的已選定供應商
- **THEN** 該製程類型 SHALL 回到「待選定」狀態，不影響其他製程類型的選定

### Requirement: 一個製程類型僅能有一筆選定
系統 SHALL 限制同一生產批號的同一製程類型最多只能有一筆選定紀錄，不支援同一製程類型在單一批次內拆分多個供應商/廠區。

#### Scenario: 重複選定同一製程類型
- **WHEN** 使用者對已有選定紀錄的製程類型再次送出選定
- **THEN** 系統 SHALL 以新選定覆蓋舊紀錄，而非新增第二筆
