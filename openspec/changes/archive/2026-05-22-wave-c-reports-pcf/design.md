# Design: Wave C — Reports & PCF Async

## C1 PCF 非同步架構

### 流程圖

```
Vue 3              Laravel                    Redis/Celery          FastAPI
  │                   │                            │                   │
  │── POST /pcf/calc ▶│                            │                   │
  │                   │── 1. 建 pcf_tasks 記錄      │                   │
  │                   │── 2. dispatch Celery task ─▶│                   │
  │◀── 202 task_id ───│                            │                   │
  │                   │                            │── run task ───────▶│
  │── GET /status ───▶│                            │                   │── 計算
  │◀── {pending} ─────│                            │                   │── PCF
  │                   │                            │                   │
  │── GET /status ───▶│                            │◀── result ────────│
  │◀── {completed} ───│── 3. 更新 pcf_tasks 狀態 ──│                   │
  │                   │                            │                   │
  │── GET /results ──▶│                            │                   │
  │◀── {co2e data} ───│                            │                   │
```

### pcf_tasks 表（新增）

```sql
CREATE TABLE pcf_tasks (
    id           CHAR(36) PRIMARY KEY,
    celery_task_id VARCHAR(100) NOT NULL,
    supplier_ids  JSON NOT NULL,
    product_ids   JSON NULL,
    status        ENUM('pending','running','completed','failed') DEFAULT 'pending',
    progress      TINYINT DEFAULT 0,
    result_count  INT NULL,
    error_message TEXT NULL,
    created_by    CHAR(36) NULL,
    created_at    DATETIME,
    updated_at    DATETIME,
    INDEX(celery_task_id),
    INDEX(status)
);
```

### task_id vs celery_task_id

- `task_id`（API 對外）= pcf_tasks.id（UUID，Laravel 生成）
- `celery_task_id`（內部）= Celery task UUID

GET /pcf/status/{task_id} 用 task_id 查 pcf_tasks，再用 celery_task_id 問 Redis。

---

## C2 Scope 3 Report 設計

### GHG Protocol Category 對應

PCF 記錄的 `category` 欄位 → Scope 3 Category number：

| Category | 名稱 | PCF category 值 |
|----------|------|----------------|
| 1  | 採購商品和服務 | purchased_goods |
| 2  | 資本財 | capital_goods |
| 3  | 能源相關活動 | energy_activities |
| 4  | 上游運輸和配送 | upstream_transport |
| 5  | 營運產生的廢棄物 | waste |
| 6  | 商務旅行 | business_travel |
| 7  | 員工通勤 | employee_commuting |
| 8  | 上游租賃資產 | upstream_leased |
| 9  | 下游運輸和配送 | downstream_transport |
| 10 | 已售商品加工 | processing |
| 11 | 已售商品使用 | product_use |
| 12 | 已售商品報廢處理 | end_of_life |
| 13 | 下游租賃資產 | downstream_leased |
| 14 | 特許經營 | franchises |
| 15 | 投資 | investments |

### 彙總查詢（跨 MySQL + PostgreSQL 問題）

PCF 記錄目前在 **PostgreSQL**（FastAPI）。

選項：
1. 將 Scope 3 Report 放在 **FastAPI**（直接讀 PostgreSQL）
2. 在 Laravel 透過 HTTP 呼叫 FastAPI 取資料再彙總

→ **建議選項 1**：`GET /reports/scope3` 代理到 FastAPI `/ai/v1/reports/scope3`，Laravel 只做路由轉發和認證。匯出由 Laravel 接收 JSON 後生成檔案。

### xlsx 匯出結構

使用 `maatwebsite/excel`：

```
Sheet 1：摘要
  | 報告年度 | 總排放量(tCO2e) | 資料筆數 |

Sheet 2：15 類別明細
  | Category # | 類別名稱 | 排放量(kgCO2e) | 佔比(%) |

Sheet 3：供應商明細
  | 供應商 | Category | 排放量 | 數據品質 |
```
