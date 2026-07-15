## ADDED Requirements

### Requirement: 產品卡片 BOM 明細 Panel
`BuyerProductsView` 中每張產品卡片 SHALL 提供可展開的 BOM 明細 panel，在供應商關聯 panel 下方，預設收合。Panel header 顯示「BOM 明細（N 筆）」，點擊展開。

#### Scenario: 展開 BOM Panel
- **WHEN** 使用者點擊卡片上的「BOM 明細」標籤
- **THEN** 系統 SHALL 載入並顯示該產品的 BomLines 列表，包含欄位：物料名稱、HS Code、物料群組（綠色 chip）、指定供應商、數量/單價、來源標記

#### Scenario: BOM Panel 空狀態
- **WHEN** 產品尚無任何 BomLine
- **THEN** SHALL 顯示空狀態提示「尚無 BOM 明細，請新增或匯入」

### Requirement: BomLine 新增與 Inline 編輯
使用者 SHALL 能在 BOM panel 中新增或修改 BomLine，無需跳頁或開 modal。

#### Scenario: 新增 BomLine
- **WHEN** 點擊「+ 新增物料」
- **THEN** 在列表頂部插入一列空白 input row，使用者填寫後點擊「儲存」呼叫 API，成功後 row 轉為顯示模式

#### Scenario: Inline 編輯
- **WHEN** 點擊某 BomLine 列的「編輯」圖示
- **THEN** 該列轉為 input 模式，欄位可編輯；物料群組欄位為下拉選單（materialGroupApi 載入選項）；指定供應商欄位為下拉選單（現有 suppliersApi）

#### Scenario: 刪除 BomLine
- **WHEN** 點擊「✕」圖示並確認
- **THEN** 呼叫 DELETE API，該列從列表移除，並更新 panel header 筆數

### Requirement: CSV/Excel BOM 匯入
BOM panel toolbar SHALL 提供「ERP 匯入」按鈕，觸發 CSV 上傳流程。

#### Scenario: 上傳並預覽
- **WHEN** 使用者選擇 CSV 檔案
- **THEN** 系統 SHALL 解析並顯示預覽：預計新增 N 筆、更新 M 筆，列出前 5 筆資料

#### Scenario: 確認匯入
- **WHEN** 使用者點擊「確認匯入」
- **THEN** 呼叫 POST /bom-lines/import，完成後顯示結果摘要（created/updated/warnings），並重新載入 BomLine 列表

#### Scenario: 匯入有 warnings
- **WHEN** 部分 supplier_code 無法解析
- **THEN** SHALL 以黃色警示列出無法解析的 supplier_code，其餘資料正常匯入
