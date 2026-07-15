## ADDED Requirements

### Requirement: ERP BOM 匯入（JSON API）
系統 SHALL 提供 `POST /api/v1/sales-products/{id}/bom-lines/import` endpoint，接受 JSON 格式的 BOM 行項目陣列，以 `erp_line_id` 為鍵進行冪等 upsert，並正確關聯至 `SalesProduct`（`sales_product_id`）。

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
- **THEN** 系統 SHALL 建立 5 筆 BomLine，`sales_product_id` 指向呼叫路徑上的 SalesProduct，`material_group_source` 設為 `'erp_imported'`（若有對應 HS Code 推斷），`supplier_source` 設為 `'erp_designated'`（若 supplier_code 能解析）

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
系統 SHALL 提供 `POST /api/v1/sales-products/{id}/bom-lines/import` endpoint 支援 multipart/form-data 上傳 CSV 或 Excel 檔案，並正確關聯至 `SalesProduct`（`sales_product_id`）。欄位對應包含新增的供應商配對欄位：`primary_supplier_code`（主要供應商 ERP 代碼）與 `alternate_supplier_code`（替代供應商，可空）。

CSV 必填欄位：`erp_line_id`、`material_name`
CSV 選填欄位：`hs_code`、`material_code`、`primary_supplier_code`、`alternate_supplier_code`、`quantity`、`unit`

匯入後系統 SHALL 依 `primary_supplier_code` 查找 `suppliers.code`，找到則建立 `bom_line_suppliers`（`role = 'primary'`，`source = 'erp_designated'`）；`alternate_supplier_code` 同理建立 `role = 'alternate'` 關聯。

#### Scenario: CSV 成功上傳並配對供應商

- **WHEN** 上傳 CSV，`primary_supplier_code` 能解析到現有 Supplier
- **THEN** 系統 SHALL 解析並執行冪等 upsert，建立 BomLine（關聯至呼叫路徑上的 SalesProduct）並自動建立 BomLineSupplier，回傳匯入摘要含 `linked_suppliers: N`

#### Scenario: CSV 格式錯誤

- **WHEN** CSV 缺少必填欄位 `erp_line_id` 或 `material_name`
- **THEN** 系統 SHALL 回傳 422 並說明缺少的欄位名稱

#### Scenario: CSV supplier_code 無法解析

- **WHEN** `primary_supplier_code` 在 suppliers 表中不存在
- **THEN** 系統 SHALL 繼續建立 BomLine，`bom_line_suppliers` 關聯跳過，並在 response `warnings` 陣列中列出無法解析的 supplier_code

#### Scenario: CSV 重複匯入冪等性

- **WHEN** 相同 erp_line_id 再次匯入
- **THEN** 系統 SHALL 更新 ERP 控制欄位，不覆蓋 ESG 標註（`notes`，`material_group_source = 'manual'` 的欄位），BomLineSupplier 若已存在則跳過，若新增則建立

---

## ADDED Requirements (erp-pcf-integration)

### Requirement: BOM 匯入時自動 upsert MaterialItem

系統 SHALL 在 BOM 行匯入時，依 `material_code` 欄位自動 upsert `MaterialItem`（`item_code = material_code`，`hs_code`、`name` 同步更新），並將 `ProductBomLine.material_item_id` 設為對應 MaterialItem 的 id。無需額外的物料映射步驟。

#### Scenario: 新物料代碼自動建立 MaterialItem

- **WHEN** BOM 匯入包含 `material_code = "CTN-32S"`，MaterialItem 中不存在此 item_code
- **THEN** 系統 SHALL 建立 `MaterialItem(item_code: "CTN-32S", hs_code: ..., name: ...)`，並設定 BomLine.material_item_id

#### Scenario: 已存在的物料代碼更新 MaterialItem

- **WHEN** BOM 匯入包含已存在的 `material_code`，但 hs_code 有變更
- **THEN** 系統 SHALL 更新 MaterialItem 的 hs_code，material_item_id 不變

#### Scenario: 無 material_code 時 material_item_id 保持 null

- **WHEN** BOM 行沒有提供 `material_code`
- **THEN** 系統 SHALL 建立 BomLine，material_item_id = null，不強制建立 MaterialItem

### Requirement: BOM 匯入後觸發碳排缺口掃描

系統 SHALL 在每次 BOM 匯入（JSON API 或 CSV）完成後，dispatch Celery job 執行碳排缺口掃描（參照 pcf-emission-gap-scan spec）。

#### Scenario: BOM 匯入後非同步觸發掃描

- **WHEN** BOM 匯入 API 成功回傳 `{ created: N, updated: M }`
- **THEN** 系統 SHALL 非同步 dispatch 缺口掃描 job，API 不等待 job 完成即回傳
