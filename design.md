# ESG·Chain — 技術架構設計 (Design)

## 1. 系統全局架構

```
┌──────────────────────────────────────────────────────┐
│                     Nginx 1.26+                       │
│   /          → esgchain-web (Vue 3 SPA)               │
│   /api/v1/   → esgchain-api (Laravel)                 │
│   /ai/       → esgchain-ai (FastAPI, SSE)             │
└──────────────────────────────────────────────────────┘
         │                    │                  │
    ┌────▼────┐         ┌─────▼─────┐    ┌──────▼──────┐
    │Vue 3 SPA│         │  Laravel  │    │   FastAPI   │
    │TS + Pinia│        │12.11.1    │    │ + LangGraph │
    └─────────┘         └─────┬─────┘    └──────┬──────┘
                              │                  │
                         ┌────▼────┐      ┌──────▼──────┐
                         │MySQL 8.4│      │PostgreSQL 16│
                         │ (業務DB)│      │+ pgvector   │
                         └─────────┘      └─────────────┘
                              │
                         ┌────▼────────────────────┐
                         │  Redis 7 (Celery Broker) │
                         │  + NebulaGraph 3.8       │
                         └─────────────────────────┘
```

## 2. 前端架構（esgchain-web）

### 技術約束（強制）

- **Vue 3 Options API**，全專案禁止使用 Composition API（`ref`、`computed` 等）
- TypeScript 5.x+，嚴格模式
- Pinia 管理所有跨元件狀態，包含 `isLoading`、分頁參數、認證 Token

### 目錄結構

```
src/
├── api/
│   ├── http.ts              # axios 實例 + 攔截器（全模組共用）
│   └── modules/
│       ├── auth.ts
│       ├── supplier.ts      # M1
│       ├── questionnaire.ts # M2
│       ├── risk.ts          # M3
│       ├── commodity.ts     # M4
│       ├── pcf.ts           # M5
│       ├── decarbonization.ts
│       ├── report.ts        # M7
│       └── settings.ts      # M9
├── stores/
│   ├── auth.ts
│   └── ui.ts                # 全域 isLoading、toast
├── utils/
│   └── datetime.ts          # formatDateTime / formatDate
└── views/                   # 路由頁面，依模組分資料夾
```

### axios 攔截器行為規範

| HTTP 狀態 | 處理行為 |
|-----------|---------|
| 401（Token 過期）| 自動換發 Refresh Token，換發成功後重試原請求 |
| 401（換發失敗）| 清除 Token，導向登入頁 |
| 403 | 顯示「權限不足」toast |
| 422 | 回傳 `errors` 物件給呼叫方（**攔截器不彈 toast**）|
| 429 | 顯示「操作過於頻繁，請稍後再試」toast |
| 500 | 顯示「系統發生錯誤，請聯繫管理員」toast |

**Token 換發 Queue 機制**：多個請求同時觸發 401 時，只發出一次 Refresh 請求，其餘加入佇列等待換發完成後統一重試。

### 時間顯示

```typescript
// src/utils/datetime.ts
import dayjs from 'dayjs'
import utc from 'dayjs/plugin/utc'
import timezone from 'dayjs/plugin/timezone'
dayjs.extend(utc)
dayjs.extend(timezone)
dayjs.tz.setDefault('Asia/Taipei')

// 稽核追蹤用：YYYY/MM/DD HH:mm:ss
export const formatDateTime = (v: string) => dayjs.tz(v).format('YYYY/MM/DD HH:mm:ss')

// 截止日期 / 里程碑用：YYYY/MM/DD
export const formatDate = (v: string) => dayjs.tz(v).format('YYYY/MM/DD')
```

**禁止直接渲染原始 UTC 字串**，所有 API 時間欄位一律透過上述函式轉換。

---

## 3. 業務後端架構（esgchain-api）

### 分層結構

```
Controller（HTTP 接口）
    └── Request（Form Request 驗證）
        └── Service（業務邏輯）
            └── Repository（資料存取）
                └── Model（Eloquent ORM）
```

### 認證機制

- JWT RS256，Laravel 持有私鑰發行，FastAPI 使用公鑰驗證
- Access Token TTL：60 分鐘
- Refresh Token TTL：7 天，使用後立即輪換（Rotation）
- Refresh Token jti 存入 Redis，Revoke 時刪除對應 key

### Rate Limiting（`routes/api.php`）

| 端點類型 | 限制 |
|---------|------|
| 一般 API | `throttle:60,1` |
| 認證端點（登入 / 換發）| `throttle:10,1` |
| 高成本操作（批量匯入）| `throttle:10,1` |

### CORS（`config/cors.php`）

```php
'paths' => ['api/*'],
'allowed_origins' => [env('FRONTEND_URL')],
'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
'allowed_headers' => ['Authorization', 'Content-Type', 'Accept', 'X-Requested-With'],
'max_age' => 86400,
'supports_credentials' => true,
```

正式環境禁止 `*`。

### 時區設定

`config/app.php` → `'timezone' => 'UTC'`
所有 Carbon 操作在 UTC 下進行，API 回傳 ISO 8601 格式。

---

## 4. AI 後端架構（esgchain-ai）

### 用途

- PCF（產品碳足跡）批量計算（Celery 非同步）
- RAG 查詢（LangChain + pgvector）
- 供應鏈關係推理（LangGraph Agent + NebulaGraph）

### LangGraph 使用原則

凡涉及 AI 多步驟推理、條件分支、Agent 循環、工具調用串聯，**優先使用 LangGraph 編排**，可搭配 LangChain 處理 RAG 與文件操作。

```
app/
├── agents/       # LangGraph Agent 定義
├── graphs/       # StateGraph 工作流程
├── chains/       # LangChain RAG / 文件處理
├── tools/        # MCP / LangChain Tool
└── tasks/        # Celery 非同步任務
    ├── celery_app.py
    └── pcf_tasks.py
```

### SSE 串流規範

- FastAPI 使用 `StreamingResponse` + `text/event-stream`
- Nginx 必須設定：
  ```nginx
  proxy_buffering off;
  proxy_cache off;
  proxy_read_timeout 300s;
  chunked_transfer_encoding on;
  ```
- SSE 端點不套用 Rate Limiting（長連接，限流會中斷串流）

### Rate Limiting（slowapi）

| 端點類型 | 限制 |
|---------|------|
| 一般 API | 60 次/分鐘 |
| AI 推論 / RAG 查詢 | 20 次/分鐘 |
| SSE 串流 | **不限流** |

---

## 5. 全域強制規範

### 主鍵

所有資料表 `id` 欄位一律 UUID，禁止 Auto Increment：

| 平台 | 實作方式 |
|------|---------|
| Laravel | `use HasUuids;` + `$table->uuid('id')->primary()` |
| FastAPI | `UUID` 型別 + `default=uuid.uuid4` |
| PostgreSQL | `UUID DEFAULT gen_random_uuid()` |
| MySQL | `CHAR(36)` + 應用層產生 |

### API 回應格式

```json
// 成功
{ "success": true, "data": {}, "message": "操作成功" }

// 分頁列表
{ "success": true, "data": [], "pagination": { "current_page": 1, "per_page": 20, "total": 100, "last_page": 5 }, "message": "" }

// 錯誤
{ "success": false, "error_code": "VALIDATION_ERROR", "message": "...", "errors": {} }
```

### 日誌

- 正式環境：`WARNING` 以上，結構化 JSON 格式
- 開發 / 測試：`DEBUG`
- 禁止輸出密碼、Token、PII

### 前端 UI

- 所有列表頁必須伺服器端分頁，預設每頁 20 筆
- 第一欄固定流水號：`(currentPage - 1) * perPage + index + 1`
- 任何資料操作需 disabled + loading 按鈕狀態，防止重複送出
- 欄位對齊：預設靠左；數值欄位靠右；狀態 Badge / 操作按鈕欄置中
