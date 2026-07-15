# Change Proposal: Wave C — Reports & PCF Async

## 前置條件

Wave A + Wave B 完成後執行。

## 動機

Spec v2.1.0 定義了兩個資料密集的功能：
- M5 PCF 非同步批量計算（取代目前的同步單筆計算）
- M7 Scope 3 排放報告（GHG Protocol 15 類別彙總 + 匯出）

這兩個功能的共同特點：**計算量大、需要非同步 + 檔案產出**，是平台功能成熟度的關鍵里程碑。

## 範圍

### C1 — M5 PCF 非同步化

**API 設計**
```
POST /pcf/calculate      觸發批量計算 → 202 Accepted + task_id
GET  /pcf/status/{task_id}  輪詢 Celery 任務狀態
GET  /pcf/results/{id}   取得計算結果（含 scope3_breakdown）
```

**批量計算支援**
- 支援多個 supplier_ids
- 支援 product_ids 篩選（空陣列 = 所有商品）
- Celery worker 非同步執行，Laravel 透過 Redis 追蹤 task_id

**結果結構**
```json
{
  "id": "uuid",
  "supplier_id": "uuid",
  "result_co2e": 247019.2,
  "scope3_breakdown": {
    "1": { "name": "採購商品和服務", "co2e": 150000.0 },
    "4": { "name": "上游運輸和配送", "co2e": 19.2 },
    ...
  },
  "calculated_at": "2024-03-15T10:00:00Z"
}
```

### C2 — M7 Scope 3 Report

**GHG Protocol 15 類別**
- 依年度彙總所有供應商的 PCF 記錄
- 對應到 GHG Protocol Scope 3 分類（Category 1–15）
- 計算總排放量（kg CO2e）

**API**
```
GET  /reports/scope3?year=2024          JSON 報告
GET  /reports/scope3/export?year=2024&format=xlsx|pdf  檔案下載
```

**匯出格式**
- `xlsx`：Laravel Excel（maatwebsite/excel）
- `pdf`：Laravel DomPDF 或 Snappy

## 不在範圍

- 前端報告頁面（後續 Phase 4）
- 驗證報告（CSRD/CBAM/CDP）— FRS 有定義但 spec 未納入

## 成功條件

- [ ] `POST /pcf/calculate` 回傳 202 + task_id（非 200）
- [ ] `GET /pcf/status/{task_id}` 回傳 pending/running/completed/failed 狀態
- [ ] `GET /pcf/results/{id}` 回傳 scope3_breakdown 明細
- [ ] `GET /reports/scope3?year=2024` 回傳正確 JSON（含 15 類別）
- [ ] `GET /reports/scope3/export?format=xlsx` 成功下載 Excel 檔
