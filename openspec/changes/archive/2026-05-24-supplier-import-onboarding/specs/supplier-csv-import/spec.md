## ADDED Requirements

### Requirement: CSV 上傳 API
`POST /api/v1/suppliers/import` SHALL 接受 multipart/form-data 的 CSV 檔案，解析 7 個 MVD 欄位（vendor_code / vat_number / vendor_name / spend_amount / country_code / material_group / primary_email），寫入 `supplier_imports` 表，回傳 batch_id 與初步解析統計（total / valid_rows / error_rows）。

#### Scenario: 合法 CSV 上傳
- **WHEN** 上傳含 10 筆有效資料的 CSV
- **THEN** 回傳 batch_id，supplier_imports 有 10 筆 cleanse_status=staged

#### Scenario: CSV 欄位不符
- **WHEN** 上傳缺少 vat_number 欄位的 CSV
- **THEN** 回傳 422，訊息「缺少必要欄位：vat_number」

#### Scenario: 中文 header 對應
- **WHEN** CSV header 使用「統編VAT」
- **THEN** 系統自動對應至 vat_number 欄位，正常解析

### Requirement: 匯入頁面
`/suppliers/import` 頁面 SHALL 提供：CSV 格式說明（7欄位名稱）、拖曳/選擇上傳 CSV、上傳後顯示解析預覽（前 5 筆）與統計（總筆數/有效/錯誤）、「確認匯入」按鈕觸發清洗，「下載範本」按鈕提供空白 CSV 範本。

#### Scenario: 上傳預覽
- **WHEN** 上傳 CSV 後
- **THEN** 頁面顯示前 5 筆資料的 table 預覽及總計統計

#### Scenario: 下載 CSV 範本
- **WHEN** 點擊「下載範本」
- **THEN** 下載含 7 個 header 欄位的空白 CSV
