## MODIFIED Requirements

### caps 表新增欄位

| 欄位 | 類型 | 說明 |
|------|------|------|
| `triggered_by_axis` | ENUM('axis1','axis2','axis3') NULLABLE | 觸發此 CAP 的三軸來源 |
| `auto_generated` | BOOLEAN DEFAULT false | 是否由系統自動建立 |

### source_type 確認

`source_type` ENUM 已包含 `risk_assessment`（migration `2026_07_02_000001` 已執行）。`source_id` 於 `source_type='risk_assessment'` 時指向 `risk_assessments.id`。

### API：POST /api/v1/cap

新增可選欄位：

```json
{
  "source_type": "risk_assessment",
  "source_id": "uuid",
  "triggered_by_axis": "axis1",
  "auto_generated": false
}
```

驗證規則：
- `triggered_by_axis`：nullable，in:axis1,axis2,axis3
- `auto_generated`：nullable boolean，API 呼叫時由客戶端傳入（CapAutoGenerationService 傳 true）；前端手動建立不傳（預設 false）

### API：GET /api/v1/cap 回傳

新增欄位於每筆 CAP 物件：

```json
{
  "triggered_by_axis": "axis2",
  "auto_generated": true
}
```

### 既有行為不變

- CAP status 狀態機（open → in_progress → completed / overdue / closed）不變
- 逾期自動更新邏輯（`booted` observer）不變
- `saq_id` 欄位保留，auto-generated CAP 同時填入 `saq_id` 與 `source_id`（指向不同 table，非冗餘）
