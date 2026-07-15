### Requirement: Portal 揭露資料填報 API

`GET /api/v1/portal/disclosures`

- 僅限 `supplier` / `sup_esg` 角色，自動以 JWT `supplierId` 限定供應商
- 回傳該供應商所有 `supplier_disclosures` 記錄，按 `field_slug` 分組，含歷年資料
- 結構與 `GET /api/v1/suppliers/{id}/disclosure-profile` 相同，但附加每個欄位的 `label`、`data_type`、`unit`（從 `supplier_disclosure_fields` join）

`POST /api/v1/portal/disclosures`

Request body：
```json
{
  "field_slug": "ghg.scope1_mt_co2e",
  "period_year": 2024,
  "value": 1560.5
}
```

- `value` 接受 number（numeric 欄位）或 boolean（boolean 欄位）
- 執行 upsert：`(supplier_id, field_slug, period_year)` 為 unique key
- `source` 設為 `manual`，`source_saq_id` 設為 null
- 若同年同欄位已有 `source: saq_sync` 記錄，仍允許覆蓋，回傳 HTTP 200 並帶 `overwritten_saq_sync: true` 警告旗標

#### Scenario: 供應商填報新數值

WHEN 供應商 POST `{ field_slug: "ghg.scope1_mt_co2e", period_year: 2024, value: 1560 }`
THEN `supplier_disclosures` upsert 一筆 `source: manual` 記錄
AND 回傳 `{ success: true, overwritten_saq_sync: false }`

#### Scenario: 覆蓋 saq_sync 記錄

WHEN 同年同欄位已有 `source: saq_sync` 記錄
AND 供應商 POST 新值
THEN 允許覆蓋，回傳 `{ success: true, overwritten_saq_sync: true }`

#### Scenario: 無效欄位

WHEN `field_slug` 不存在於 `supplier_disclosure_fields`
THEN 回傳 HTTP 422，`{ message: "欄位不存在" }`

### Requirement: Portal 永續資料填報頁面

路由：`/supplier/portal/disclosures`，僅限 `supplier` / `sup_esg` 角色。

頁面結構：
- 頁面標題「永續 KPI 填報」，副標題說明資料用途
- KPI 依 prefix 分組為多個 section（cert / ghg / energy / labor / water / waste / governance / diversity / supply_chain）
- 每個 section 顯示 section 標題 + 該 prefix 下所有欄位
- 每個欄位顯示：`label`（中文）、`unit`（若有）、年度選擇（下拉，2020–當前年）、值輸入（boolean 用 toggle/checkbox，numeric 用 number input）
- 若該欄位已有當年度歷史值，預填顯示並標記來源（「來自問卷」或「手動填報」）
- 若預填值來自 `saq_sync`，填報前顯示「此數值已由問卷同步，覆蓋後將標記為手動填報」提示
- 儲存按鈕：每欄位獨立儲存（inline 儲存，不需全頁 submit）

#### Scenario: 供應商查看現有填報

WHEN 供應商進入填報頁面
THEN 頁面顯示所有 KPI 欄位，已有當年度資料的預填顯示
AND 來源為 saq_sync 的欄位標示「來自問卷 v{saq_series}」

#### Scenario: 填報 numeric 欄位

WHEN 供應商在「總用電量」欄位輸入 50000 並選擇年度 2024，點擊儲存
THEN 呼叫 POST /portal/disclosures，成功後 inline 顯示「已儲存」
AND 若原為 saq_sync，顯示橘色「已覆蓋問卷數值」提示

#### Scenario: 填報 boolean 欄位

WHEN 供應商切換「持有 ISO 14001 認證」為「是」並儲存
THEN `boolean_value: true` 寫入 `supplier_disclosures`

### Requirement: Portal 側邊欄新增填報入口

Portal 側邊欄（`supplier` / `sup_esg` 角色）SHALL 包含「永續 KPI 填報」選單項目，導向 `/supplier/portal/disclosures`。
