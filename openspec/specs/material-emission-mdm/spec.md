## ADDED Requirements

### Requirement: 物料排放強度主數據管理

系統 SHALL 維護 `material_item_emissions` 表，記錄每個供應商對每個物料的碳排強度，支援多版本 append-only 歷史追蹤。

#### Scenario: 供應商提報物料碳排

- **WHEN** 供應商透過 Portal 提交某物料的 emissions_value
- **THEN** 系統 SHALL 新增一筆 material_item_emissions 記錄（source=portal-self, is_estimated=false, reported_at=now()），不覆蓋舊記錄

#### Scenario: 買方代填物料碳排

- **WHEN** 採購商在物料主檔碳排資料庫分頁代填某供應商的碳排值
- **THEN** 系統 SHALL 新增一筆 material_item_emissions 記錄（source=buyer-input, is_estimated=false）

#### Scenario: 多版本並存

- **WHEN** 同一供應商對同一物料在不同 reported_period 各提報一次
- **THEN** 系統 SHALL 保留兩筆記錄，PCF 計算取 is_estimated=false 中 reported_at 最新的一筆

### Requirement: 異常標記機制

採購商 SHALL 能對任一筆 material_item_emissions 標記異常，標記不阻斷 PCF 計算但於 PCF 明細中以警示呈現。

#### Scenario: 買方標記異常

- **WHEN** 採購商點擊某筆碳排記錄的「標記異常」並輸入原因
- **THEN** 系統 SHALL 將該筆記錄 is_flagged 設為 true，flag_reason 儲存輸入文字，PCF 快照繼續使用該值但標記 ⚠

#### Scenario: 異常值仍計入 PCF

- **WHEN** 某筆 material_item_emissions 的 is_flagged = true
- **THEN** PCF 計算 SHALL 繼續使用該值，但 pcf_snapshots.lines 中對應項目 SHALL 帶有 data_quality=flagged 標記

### Requirement: 碳排資料庫分頁（物料主檔頁買方視角）

物料主檔詳情（或 MaterialItemsView 展開列）SHALL 提供「碳排資料庫」分頁，顯示所有供應商的提報記錄，含主動提報但未被任何 BomLine 指定的供應商。

#### Scenario: 顯示所有供應商碳排記錄

- **WHEN** 採購商開啟某物料的碳排資料庫分頁
- **THEN** 系統 SHALL 列出所有 material_item_emissions 記錄，依 supplier_id 分組，每組顯示最新一筆值與歷史記錄數

#### Scenario: 顯示 AI 估算值

- **WHEN** 某供應商的碳排記錄 source=ai-estimated
- **THEN** UI SHALL 以灰底虛線框顯示，並標記「🤖 AI估算，建議提報實際數據」

#### Scenario: 主動提報供應商顯示

- **WHEN** 某供應商已提報物料碳排但未被任何 BomLine 指定為供應商
- **THEN** 碳排資料庫 SHALL 仍顯示該供應商的提報值，標記「未指定於 BOM」，提供「查看此供應商」連結

### Requirement: 碳排強度取值優先級

PCF 計算取值時 SHALL 遵循優先級：(1) portal-self 最新 (2) buyer-input 最新 (3) ai-estimated 最新 (4) 物料群組預設因子 (5) 行業平均。

#### Scenario: 有實測值時不用估算值

- **WHEN** 同一 (material_item, supplier) 同時存在 is_estimated=true 與 is_estimated=false 的記錄
- **THEN** PCF 計算 SHALL 取 is_estimated=false 中 reported_at 最新的記錄，忽略估算值
