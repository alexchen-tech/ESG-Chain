## ADDED Requirements

### Requirement: ERP BOM 匯入（JSON API）
系統 SHALL 提供 `POST /api/v1/buyer-products/{id}/bom-lines/import` endpoint，接受 JSON 格式的 BOM 行項目陣列，以 `erp_line_id` 為鍵進行冪等 upsert。

JSON 格式：
```json
[
  {
    "erp_line_id": "string（必填）",
    "material_name": "string（必填）",
    "hs_code": "string（optional）",
    "quantity": "number（optional）",
    "unit": "string（optional）",
    "unit_price": "number（optional）",
    "currency": "string 3碼（optional）",
    "supplier_code": "string（optional，對應 suppliers.code）"
  }
]
```

#### Scenario: 首次匯入
- **WHEN** POST 包含 5 筆新 erp_line_id
- **THEN** 系統 SHALL 建立 5 筆 BomLine，`material_group_source` 設為 `'erp_imported'`（若有對應 HS Code 推斷），`supplier_source` 設為 `'erp_designated'`（若 supplier_code 能解析）

#### Scenario: 重複匯入冪等性
- **WHEN** 相同 erp_line_id 再次匯入，僅數量有變動
- **THEN** 系統 SHALL 更新 ERP 控制欄位（`quantity`, `unit_price`, `hs_code`），不覆蓋 ESG 標註（`notes`，以及 `material_group_source = 'manual'` 的欄位）

#### Scenario: supplier_code 無法解析
- **WHEN** 匯入資料中的 supplier_code 在 suppliers 表中不存在
- **THEN** 系統 SHALL 繼續匯入其餘欄位，`designated_supplier_id` 保持 null，並在 response 的 `warnings` 陣列中記錄無法解析的 supplier_code

#### Scenario: 匯入結果摘要
- **WHEN** 匯入完成
- **THEN** 系統 SHALL 回傳 `{ created: N, updated: N, warnings: [] }`

### Requirement: ERP BOM 匯入（CSV/Excel）
系統 SHALL 提供 `POST /api/v1/buyer-products/{id}/bom-lines/import` endpoint 支援 multipart/form-data 上傳 CSV 或 Excel 檔案，欄位對應與 JSON 格式相同（欄位名稱為 header 列）。

#### Scenario: CSV 成功上傳
- **WHEN** 上傳格式正確的 CSV 檔案（含 header 列）
- **THEN** 系統 SHALL 解析並執行冪等 upsert，回傳匯入摘要

#### Scenario: 格式錯誤
- **WHEN** CSV 缺少必填欄位 `erp_line_id` 或 `material_name`
- **THEN** 系統 SHALL 回傳 422 並說明缺少的欄位名稱
