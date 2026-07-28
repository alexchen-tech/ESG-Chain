## MODIFIED Requirements

### Requirement: 供應鏈合規調查清單
批次護照 SHALL 提供以 BOM 表為主軸的供應鏈合規調查清單，每一筆物料需同時包含選定/建議的供應商、該批次的溯源資料、以及該供應商對此物料類別必備文件的符合狀態。批次護照的「供應鏈製程級地點」區塊 SHALL 以批次層級已選定的製程供應商（見 `batch-process-facility-selection`）為準，未選定的製程類型 SHALL 標示為「待選定」並列出候選供應商，不得直接呈現產品 BOM 全部核可供應商清單當作該批次的製程地點。

#### Scenario: 完整鏈路呈現
- **WHEN** 使用者查看某生產批號的供應鏈合規調查
- **THEN** 清單 SHALL 逐一物料列出：selected_supplier（含是否為批次已確認）、traceability（原產國/設施/GPS/認證編號等）、doc_statuses（依物料類別必備文件逐項列出狀態）

#### Scenario: 供應商選定與確認的唯一入口
- **WHEN** 使用者需要為某物料確認實際供應商
- **THEN** 系統 SHALL 導引至「原物料合規與溯源管理」表單完成，不 SHALL 提供其他平行的快速確認入口

#### Scenario: 製程級地點以批次已選定資料為準
- **WHEN** 使用者查看某批號的「供應鏈製程級地點」
- **THEN** 系統 SHALL 針對該批次相關的每個製程類型，顯示是否已選定實際供應商（`confirmed: true` 並顯示廠區資訊）或尚待選定（`confirmed: false` 並列出候選供應商）

#### Scenario: 未選定製程不得誤呈現為已確認地點
- **WHEN** 某製程類型尚未有 `BatchProcessFacility` 選定紀錄
- **THEN** 系統 SHALL NOT 從產品 BOM 全部核可供應商中挑一筆當作該批次的製程地點顯示，必須明確標示待選定狀態
