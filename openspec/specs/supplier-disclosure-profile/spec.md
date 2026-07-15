### Requirement: Disclosure 填報儲存

`supplier_disclosures` 表以 `(supplier_id, field_slug, period_year)` 為 unique key 保存供應商 KPI 填報值。欄位：
- `supplier_id` FK → suppliers
- `field_slug` FK → supplier_disclosure_fields
- `period_year` INT（西元年，例如 2024）
- `numeric_value` DECIMAL(15,4) nullable
- `boolean_value` TINYINT nullable
- `text_value` TEXT nullable（單選題存選項字串）
- `evidence_url` VARCHAR nullable
- `source` ENUM：`saq_sync` / `manual` / `erp_sync`
- `source_saq_id` FK → saqs nullable
- `verified_at` TIMESTAMP nullable
- `updated_at` TIMESTAMP

#### Scenario: 同年同欄位 upsert

WHEN `DisclosureSyncService` 對同一 `(supplier_id, field_slug, period_year)` 寫入第二次
THEN 應 upsert（覆蓋 numeric_value / boolean_value / text_value 及 source_saq_id、updated_at），不新增重複列

#### Scenario: 跨年歷史保留

WHEN 2023 年與 2024 年各有一筆 Scope 1 記錄
THEN 兩筆同時存在，`GET /api/v1/suppliers/{id}/disclosure-profile` 應回傳兩筆，各自帶 `period_year`

### Requirement: Disclosure Profile API

`GET /api/v1/suppliers/{id}/disclosure-profile`

回傳結構：
```json
{
  "supplier_id": "...",
  "disclosures": {
    "ghg.scope1_mt_co2e": [
      { "period_year": 2023, "numeric_value": 1840, "source": "saq_sync", "source_saq_id": "..." },
      { "period_year": 2024, "numeric_value": 1560, "source": "saq_sync", "source_saq_id": "..." }
    ],
    "governance.has_anti_corruption_policy": [
      { "period_year": 2024, "boolean_value": true, "source": "saq_sync" }
    ]
  }
}
```

#### Scenario: 無 disclosure 資料的供應商

WHEN 供應商尚未完成任何問卷
THEN API 回傳 `{ "disclosures": {} }`，HTTP 200

#### Scenario: 權限控管

WHEN `supplier` / `sup_esg` 角色呼叫其他供應商的 disclosure-profile
THEN 回傳 HTTP 403

### Requirement: SAQ Scored 後自動同步

SAQ 評分完成（`SAQService::updateScore()` 執行後），系統 SHALL 自動呼叫 `DisclosureSyncService::syncFromSaq($saq)`。

同步規則：
1. 查詢該 SAQ 所有 `saq_responses`，join `saq_questions.disclosure_field_slug`
2. 對有 `disclosure_field_slug` 的回答，提取 `answer` / `answer_options` 值
3. 判斷 `period_year`：取 SAQ 的 `submitted_at` 年份；若為 null 取 `updated_at` 年份
4. Upsert 到 `supplier_disclosures`，`source = 'saq_sync'`，`source_saq_id = saq.id`
5. 若 sync 拋出例外，only log，不影響 SAQ 主流程

#### Scenario: Sync 成功後可查詢

WHEN 供應商送出問卷且評分完成
THEN `GET /api/v1/suppliers/{id}/disclosure-profile` 應能查到對應 KPI 值

#### Scenario: Sync 失敗不影響評分

WHEN `DisclosureSyncService::syncFromSaq()` 拋出 DB 例外
THEN SAQ 的 `score`、`grade`、`status` 不受影響，例外被 catch 並寫入 Laravel log

### Requirement: 歷年趨勢 UI

供應商詳情頁新增「Disclosure Profile」tab，顯示：
- 每個 disclosure field 的歷年折線圖或數值列表（至少支援 3 年）
- `source_saq_id` 可點擊跳轉至來源問卷
- `verified_at` 非 null 時顯示「已驗證」標記

#### Scenario: 顯示趨勢

WHEN 供應商有 2 年以上同一欄位的 disclosure 記錄
THEN UI 應以表格或圖表並排顯示各年數值及年度變化百分比

#### Scenario: 無資料狀態

WHEN 供應商該欄位無任何 disclosure 記錄
THEN 顯示「尚無填報資料」空白狀態，不顯示圖表
