## ADDED Requirements

### 新欄位

`cap_findings` 表新增以下欄位（nullable，向後相容既有 E/S/G finding）：

| 欄位 | 類型 | 說明 |
|------|------|------|
| `framework` | VARCHAR(20) | ISO 框架識別：`iso26k` \| `iso20400` \| `esg` |
| `topic_slug` | VARCHAR(80) | 對應 `question_tags.slug`（如 `iso26k.env.climate`） |
| `source_score` | DECIMAL(5,2) | 觸發此 finding 的原始分數（來自 category_scores 或 SAQResponse 聚合） |
| `threshold` | DECIMAL(5,2) | 判斷低分的閾值，預設 60.0 |

### 欄位語意規則

- `framework` + `topic_slug` 一起使用時，`topic_slug` **必須**存在於 `question_tags.slug`，且 `question_tags.l1_domain` 與 `framework` 一致（iso26k → `iso26k`，iso20400 → `ISO20400`）。
- 自動產生的 finding：`framework`、`topic_slug`、`source_score`、`threshold` **必須**填入。
- 手動建立的 finding：以上四個欄位均可為 null，`category`（E/S/G）繼續有效。
- `category` 欄位保留，不設 NOT NULL 約束，允許 null（自動 finding 不填）。

### API 回傳格式

`GET /api/v1/cap/{id}` 的 findings 陣列中，每條 finding 新增欄位：

```json
{
  "id": "...",
  "cap_id": "...",
  "category": null,
  "framework": "iso26k",
  "topic_slug": "iso26k.env.climate",
  "topic_label_zh": "氣候變遷",
  "source_score": 28.0,
  "threshold": 60.0,
  "finding": "氣候變遷 評分 28/100，低於及格線 60",
  "root_cause": null,
  "corrective_action": null,
  "status": "open",
  "target_date": null
}
```

`topic_label_zh` 為 runtime JOIN `question_tags.label_zh` 查詢，不儲存於 cap_findings（避免與 question_tags 主檔脫鉤）。
