# 技術架構標準 (Technology Architecture Standards)

本文件為跨專案通用技術標準，Claude Code 在所有相關專案中應遵循以下規範進行開發。

---

## 一、技術選型原則

### 1.1 前端選型

所有專案統一使用 **Vue 3**，並遵守以下規範：

- 語言：**TypeScript**
- 元件風格：**Options API**（不使用 Composition API）
- 狀態管理：**Pinia**
- 建構工具：**Vite**

### 1.2 後端選型邏輯

依據核心業務性質選擇後端框架，判斷邏輯如下：

```
核心業務是「內容 / 流程 / 交易」？
    └─ 是 → Laravel 12.11.1 (PHP 8.5.1)

核心業務是「邏輯計算 / 人工智慧 / 數據密集」？
    └─ 是 → Python FastAPI

兩種類型都包括？
    └─ Python FastAPI 為優先
       視情況判斷是否採用 Laravel + FastAPI 雙後端混合架構
```

**Laravel 適用場景範例：** 訂單管理、會員系統、審核流程、權限控管、一般 CRUD API

**FastAPI 適用場景範例：** AI 推論服務、RAG 查詢、圖譜運算、PCF 碳足跡批量計算、資料管道

### 1.3 資料庫選型邏輯

```
需要向量搜尋 / RAG？
    └─ 是 → PostgreSQL + pgvector（首選）
             或 Qdrant（大規模向量場景）

不需要向量搜尋？
    └─ PostgreSQL 或 MySQL 皆可
       Laravel 專案建議 MySQL 8.4 LTS
       FastAPI 專案建議 PostgreSQL

需要圖關係查詢（知識圖譜、供應鏈關係網路）？
    └─ NebulaGraph 3.8+
```

---

## 二、完整技術棧與建議版本

### 2.1 前端 (Frontend)

| 技術 | 版本 | 用途 |
|------|------|------|
| Vue 3 | v3.5+ | UI 框架，Options API 風格 |
| Vite | v5.x+ | 建構工具 |
| Pinia | v2.x+ | 狀態管理 |
| TypeScript | v5.x+ | 型別安全 |
| Vue Router | v4.x+ | 路由管理 |

### 2.2 反向代理 (Proxy)

| 技術 | 版本 | 用途 |
|------|------|------|
| Nginx | v1.26+ | 靜態資源、SSL 終止、路由分發、SSE 長連接優化 |

> Nginx 處理 SSE 串流時，務必設定 `proxy_buffering off`。

### 2.3 業務後端 (Laravel)

| 技術 | 版本 | 用途 |
|------|------|------|
| PHP | 8.5.1 | 運行環境 |
| Laravel | 12.11.1 | 業務邏輯、RBAC、API |
| Guzzle | 8.2+ | 內部 HTTP 呼叫（呼叫 FastAPI 時 Timeout 應 > 60 秒） |
| MySQL | 8.4 LTS | 業務資料庫 |

### 2.4 AI / 數據後端 (FastAPI)

| 技術 | 版本 | 用途 |
|------|------|------|
| Python | 3.12.4+ | 運行環境 |
| FastAPI | v0.115+ | AI API、非同步串流 |
| Uvicorn | v0.34+ | ASGI Worker |
| Gunicorn | v23.x | 進程管理，確保高併發穩定性 |
| uv (Astral) | Latest | Python 套件與虛擬環境管理，取代 pip/poetry |

### 2.5 任務隊列 (Task Queue)

| 技術 | 版本 | 用途 |
|------|------|------|
| Celery | v5.x+ | 後台排程與非同步任務處理器 |
| Redis | v7.x+ | Celery Broker / Result Backend、耗時批量計算（如 PCF）的訊息佇列 |

### 2.6 關聯式資料庫 (Relational DB)

| 技術 | 版本 | 用途 |
|------|------|------|
| PostgreSQL | v16+ | FastAPI 專案主資料庫、MDM、審核日誌 |
| MySQL | 8.4 LTS | Laravel 專案業務資料庫 |

### 2.7 向量資料庫 (Vector DB)

| 技術 | 版本 | 用途 |
|------|------|------|
| pgvector | v0.7+ | PostgreSQL 擴充，RAG 向量儲存（首選） |
| Qdrant | v1.9+ | 獨立向量資料庫，適用大規模向量場景（備選） |

### 2.8 圖資料庫 (Graph DB)

| 技術 | 版本 | 用途 |
|------|------|------|
| NebulaGraph | v3.8+ | 分散式圖資料庫，儲存供應鏈關係網路、知識圖譜、法規條文關聯 |

> Python 驅動使用 `nebula3-python`。

### 2.9 AI 整合 (AI Integration)

| 技術 | 版本 | 用途 |
|------|------|------|
| LangChain | v0.3.x+ | RAG 框架、文件載入、向量檢索 |
| LangGraph | v0.2.x+ | AI Agent 多步驟工作流程編排（有 AI 多步驟邏輯時優先使用，可配合 LangChain） |
| MCP Python SDK | Latest | MCP Server/Client，擴展 AI 工具能力 |
| OpenAI SDK | Latest | GPT 系列模型整合 |
| Anthropic SDK | Latest | Claude 系列模型整合 |
| Google Generative AI | Latest | Gemini 系列模型整合 |
| Ollama | Latest | 本地自建 LLM 運行環境 |

> **LangGraph 使用原則：** 凡涉及 AI 多步驟推理、條件分支、Agent 循環、工具調用串聯等邏輯，優先使用 LangGraph 編排，可搭配 LangChain 處理 RAG 與文件操作。

---

## 三、專案目錄結構

專案根目錄為 `{project-name}/`，第二層依功能分為前端、業務後端、AI 後端等子目錄。並非每個專案都需要全部子目錄，依實際技術選型保留對應子目錄即可。

### 3.1 整體根目錄結構

```
{project-name}/
├── {project-name}-web/         # Vue 3 前端
├── {project-name}-api/         # Laravel 業務後端（視選型決定是否存在）
├── {project-name}-ai/          # FastAPI AI 後端（視選型決定是否存在）
├── docker-compose.yml          # 本地開發容器編排
├── docker-compose.prod.yml     # 正式環境容器編排
├── nginx/
│   └── default.conf            # Nginx 路由設定
└── README.md
```

---

### 3.2 Vue 3 前端（`{project-name}-web/`）

```
{project-name}-web/
├── public/
│   └── favicon.ico
├── src/
│   ├── api/                    # API 呼叫封裝（依模組分檔）
│   │   ├── http.ts             # axios 實例與攔截器
│   │   └── modules/
│   │       └── auth.ts
│   ├── assets/                 # 靜態資源（圖片、字型）
│   ├── components/             # 可重用元件
│   │   ├── common/             # 全局通用元件
│   │   └── ui/                 # UI 基礎元件
│   ├── layouts/                # 版面配置元件
│   ├── router/
│   │   └── index.ts            # Vue Router 路由定義
│   ├── stores/                 # Pinia 狀態管理
│   │   └── auth.ts
│   ├── types/                  # TypeScript 型別定義
│   │   └── index.d.ts
│   ├── utils/                  # 工具函式
│   ├── views/                  # 頁面元件（對應路由）
│   │   ├── HomeView.vue
│   │   └── auth/
│   ├── App.vue
│   └── main.ts
├── .env.development            # 開發環境變數
├── .env.production             # 正式環境變數
├── .env.test                   # 測試環境變數
├── tsconfig.json
├── tsconfig.app.json
├── vite.config.ts
└── package.json
```

---

### 3.3 Laravel 業務後端（`{project-name}-api/`）

```
{project-name}-api/
├── app/
│   ├── Http/
│   │   ├── Controllers/        # 控制器（依模組分資料夾）
│   │   │   └── Api/
│   │   ├── Middleware/         # 中介層
│   │   └── Requests/          # Form Request 驗證
│   ├── Models/                 # Eloquent 模型
│   ├── Services/               # 業務邏輯服務層
│   ├── Repositories/           # 資料存取層
│   ├── Jobs/                   # 佇列任務
│   ├── Events/                 # 事件
│   ├── Listeners/              # 事件監聽器
│   └── Providers/              # Service Provider
├── config/
├── database/
│   ├── migrations/
│   ├── seeders/
│   └── factories/
├── routes/
│   ├── api.php                 # API 路由
│   └── web.php
├── storage/
├── tests/
│   ├── Feature/
│   └── Unit/
├── .env                        # 正式環境（不入版控）
├── .env.example                # 範本（入版控）
├── .env.testing                # 測試環境
└── composer.json
```

---

### 3.4 FastAPI AI 後端（`{project-name}-ai/`）

```
{project-name}-ai/
├── app/
│   ├── api/
│   │   ├── routes/             # API 路由定義（依模組分檔）
│   │   │   ├── chat.py
│   │   │   └── rag.py
│   │   └── dependencies.py     # FastAPI 依賴注入
│   ├── core/
│   │   ├── config.py           # 環境變數設定（Pydantic Settings）
│   │   └── security.py         # 認證 / JWT 驗證
│   ├── db/
│   │   ├── postgresql.py       # PostgreSQL 連線（SQLAlchemy async）
│   │   ├── vector.py           # pgvector / Qdrant 連線
│   │   └── nebula.py           # NebulaGraph 連線（nebula3-python）
│   ├── models/                 # SQLAlchemy ORM 模型
│   ├── schemas/                # Pydantic 資料結構（Request / Response）
│   ├── services/               # 業務邏輯服務層
│   ├── agents/                 # LangGraph Agent 定義
│   │   └── supply_chain_agent.py
│   ├── graphs/                 # LangGraph 工作流程（StateGraph）
│   │   └── rag_pipeline.py
│   ├── chains/                 # LangChain Chain 定義（RAG、文件處理）
│   ├── tools/                  # MCP / LangChain Tool 定義
│   │   └── mcp_tools.py
│   ├── tasks/                  # Celery 非同步任務
│   │   ├── celery_app.py       # Celery 初始化
│   │   └── pcf_tasks.py        # PCF 批量計算任務
│   └── main.py                 # FastAPI 應用程式進入點
├── tests/
│   ├── test_api/
│   └── test_services/
├── .env                        # 正式環境（不入版控）
├── .env.example                # 範本（入版控）
├── .env.test                   # 測試環境
├── pyproject.toml              # 套件定義（uv 管理）
├── uv.lock                     # 鎖定檔（入版控）
└── Dockerfile
```

---

## 四、設定檔標準

以下為各類服務的環境變數規範。**標準協定預設值直接填入；環境相依的值（主機、帳密、金鑰）列出項目，值由各專案依環境補齊。**

---

### 4.1 資料庫連線設定

#### PostgreSQL（含 pgvector）

```env
# ===== 正式環境 =====
POSTGRES_HOST=
POSTGRES_PORT=5432
POSTGRES_DB=
POSTGRES_USER=
POSTGRES_PASSWORD=
POSTGRES_SCHEMA=public
POSTGRES_SSL_MODE=require

# pgvector
PGVECTOR_ENABLED=true
PGVECTOR_DIMENSION=1536

# ===== 測試環境 =====
TEST_POSTGRES_HOST=
TEST_POSTGRES_PORT=5432
TEST_POSTGRES_DB=
TEST_POSTGRES_USER=
TEST_POSTGRES_PASSWORD=
TEST_POSTGRES_SSL_MODE=disable
```

#### MySQL

```env
# ===== 正式環境 =====
MYSQL_HOST=
MYSQL_PORT=3306
MYSQL_DATABASE=
MYSQL_USERNAME=
MYSQL_PASSWORD=
MYSQL_CHARSET=utf8mb4
MYSQL_COLLATION=utf8mb4_unicode_ci

# ===== 測試環境 =====
TEST_MYSQL_HOST=
TEST_MYSQL_PORT=3306
TEST_MYSQL_DATABASE=
TEST_MYSQL_USERNAME=
TEST_MYSQL_PASSWORD=
```

#### Qdrant（向量資料庫，備選）

```env
# ===== 正式環境 =====
QDRANT_HOST=
QDRANT_PORT=6333
QDRANT_GRPC_PORT=6334
QDRANT_API_KEY=
QDRANT_COLLECTION=

# ===== 測試環境 =====
TEST_QDRANT_HOST=
TEST_QDRANT_PORT=6333
TEST_QDRANT_API_KEY=
```

#### NebulaGraph

```env
# ===== 正式環境 =====
NEBULA_HOST=
NEBULA_PORT=9669
NEBULA_USER=
NEBULA_PASSWORD=
NEBULA_SPACE=
NEBULA_MAX_CONNECTION_POOL_SIZE=10

# ===== 測試環境 =====
TEST_NEBULA_HOST=
TEST_NEBULA_PORT=9669
TEST_NEBULA_USER=
TEST_NEBULA_PASSWORD=
TEST_NEBULA_SPACE=
```

#### Redis

```env
# ===== 正式環境 =====
REDIS_HOST=
REDIS_PORT=6379
REDIS_PASSWORD=
REDIS_DB_CACHE=0
REDIS_DB_QUEUE=1
REDIS_DB_RESULT=2

# ===== 測試環境 =====
TEST_REDIS_HOST=
TEST_REDIS_PORT=6379
TEST_REDIS_PASSWORD=
TEST_REDIS_DB_CACHE=10
TEST_REDIS_DB_QUEUE=11
TEST_REDIS_DB_RESULT=12
```

---

### 4.2 前端連線設定

Vue 3 使用 Vite 的 `.env` 機制，變數須以 `VITE_` 為前綴才能在瀏覽器端讀取。

#### `.env.production`（正式環境）

```env
VITE_APP_ENV=production

# 業務 API（Laravel）
VITE_API_BASE_URL=
VITE_API_TIMEOUT=30000

# AI API（FastAPI SSE）
VITE_AI_API_BASE_URL=
VITE_AI_API_TIMEOUT=120000

# 其他第三方服務（視專案需求補充）
VITE_SENTRY_DSN=
VITE_GA_ID=
```

#### `.env.development`（開發 / 測試環境）

```env
VITE_APP_ENV=development

# 業務 API（Laravel）
VITE_API_BASE_URL=http://localhost:8080
VITE_API_TIMEOUT=30000

# AI API（FastAPI SSE）
VITE_AI_API_BASE_URL=http://localhost:8000
VITE_AI_API_TIMEOUT=120000

VITE_SENTRY_DSN=
VITE_GA_ID=
```

---

### 4.3 後端連線設定

#### Laravel `.env`

```env
# ----- 應用程式 -----
APP_NAME=
APP_ENV=production          # 測試環境改為 testing
APP_KEY=
APP_DEBUG=false             # 測試環境改為 true
APP_URL=

# ----- 資料庫（MySQL）-----
DB_CONNECTION=mysql
DB_HOST=
DB_PORT=3306
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci

# ----- 快取 -----
CACHE_DRIVER=redis
SESSION_DRIVER=redis

# ----- 佇列 -----
QUEUE_CONNECTION=redis

# ----- Redis -----
REDIS_HOST=
REDIS_PORT=6379
REDIS_PASSWORD=
REDIS_DB=0

# ----- AI 服務（FastAPI）-----
AI_SERVICE_URL=
AI_SERVICE_TIMEOUT=120      # 秒，需大於 LLM 生成時間

# ----- 郵件 -----
MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=
MAIL_FROM_NAME=

# ----- JWT（RS256）-----
JWT_PRIVATE_KEY=              # 私鑰路徑或內容（Laravel 簽發使用）
JWT_PUBLIC_KEY=               # 公鑰路徑或內容（驗證使用）
JWT_ACCESS_TOKEN_TTL=3600
JWT_REFRESH_TOKEN_TTL=604800

# ===== 測試環境額外覆蓋（.env.testing）=====
# APP_ENV=testing
# APP_DEBUG=true
# DB_DATABASE=（測試用資料庫）
# CACHE_DRIVER=array
# QUEUE_CONNECTION=sync
```

#### FastAPI `.env`

```env
# ----- 應用程式 -----
APP_ENV=production          # 測試環境改為 test
APP_DEBUG=false             # 測試環境改為 true
APP_HOST=0.0.0.0
APP_PORT=8000
SECRET_KEY=

# ----- PostgreSQL -----
DATABASE_URL=postgresql+asyncpg://{user}:{password}@{host}:5432/{dbname}
DATABASE_POOL_SIZE=10
DATABASE_MAX_OVERFLOW=20

# ----- pgvector -----
VECTOR_EMBEDDING_MODEL=text-embedding-3-small
VECTOR_DIMENSION=1536

# ----- Qdrant（備選，有需要時啟用）-----
# QDRANT_URL=http://{host}:6333
# QDRANT_API_KEY=

# ----- NebulaGraph -----
NEBULA_HOST=
NEBULA_PORT=9669
NEBULA_USER=
NEBULA_PASSWORD=
NEBULA_SPACE=
NEBULA_MAX_CONNECTION_POOL_SIZE=10

# ----- Redis -----
REDIS_URL=redis://:{password}@{host}:6379/0

# ----- Celery -----
CELERY_BROKER_URL=redis://:{password}@{host}:6379/1
CELERY_RESULT_BACKEND=redis://:{password}@{host}:6379/2
CELERY_TASK_SERIALIZER=json
CELERY_RESULT_SERIALIZER=json
CELERY_TIMEZONE=Asia/Taipei

# ----- LLM API 金鑰 -----
OPENAI_API_KEY=
ANTHROPIC_API_KEY=
GOOGLE_API_KEY=

# ----- Ollama（本地 LLM，可選）-----
OLLAMA_BASE_URL=http://localhost:11434

# ----- LangSmith（LangChain 追蹤，可選）-----
LANGCHAIN_TRACING_V2=false
LANGCHAIN_API_KEY=
LANGCHAIN_PROJECT=

# ----- JWT（RS256）-----
JWT_PUBLIC_KEY=               # 公鑰路徑或內容（驗證 Laravel 發行的 Token）
JWT_ACCESS_TOKEN_TTL=3600

# ===== 測試環境額外覆蓋（.env.test）=====
# APP_ENV=test
# APP_DEBUG=true
# DATABASE_URL=postgresql+asyncpg://{user}:{password}@{host}:5432/{test_dbname}
# CELERY_TASK_ALWAYS_EAGER=true    # 測試時 Celery 同步執行
```

---

## 五、開發規範補充

### 5.1 API 設計

- 所有 API 路徑統一使用 **kebab-case**，例如 `/api/supply-chain/carbon-footprint`
- RESTful 資源命名使用複數名詞，例如 `/api/products`、`/api/orders`
- 版本控制加在路徑前綴，例如 `/api/v1/`

#### 成功回應格式

```json
{
  "success": true,
  "data": { },
  "message": "操作成功"
}
```

分頁列表回應額外包含 `pagination`：

```json
{
  "success": true,
  "data": [],
  "pagination": {
    "current_page": 1,
    "per_page": 20,
    "total": 100,
    "last_page": 5
  },
  "message": ""
}
```

#### 錯誤回應格式

所有錯誤統一結構，HTTP 狀態碼與 `error_code` 共同表達錯誤類型：

```json
{
  "success": false,
  "error_code": "VALIDATION_ERROR",
  "message": "輸入資料有誤",
  "errors": {
    "email": ["email 格式不正確"],
    "name": ["name 為必填欄位"]
  }
}
```

**常用 HTTP 狀態碼對應**

| 狀態碼 | 情境 |
|--------|------|
| `200` | 成功 |
| `201` | 建立成功 |
| `400` | 請求參數錯誤 / 業務邏輯錯誤 |
| `401` | 未認證（JWT 無效或過期） |
| `403` | 無權限（RBAC 不允許） |
| `404` | 資源不存在 |
| `422` | 驗證失敗（表單欄位錯誤） |
| `429` | 請求頻率超限 |
| `500` | 伺服器內部錯誤 |

**常用 `error_code` 清單**

| error_code | 說明 |
|------------|------|
| `VALIDATION_ERROR` | 表單驗證失敗 |
| `UNAUTHORIZED` | JWT 無效或未帶 Token |
| `TOKEN_EXPIRED` | Access Token 已過期 |
| `FORBIDDEN` | 權限不足 |
| `NOT_FOUND` | 資源不存在 |
| `DUPLICATE_ENTRY` | 資料重複（唯一鍵衝突） |
| `BUSINESS_ERROR` | 業務邏輯錯誤 |
| `SERVER_ERROR` | 伺服器內部錯誤 |

### 5.2 AI 多步驟邏輯

- 有 AI 多步驟推理、條件分支、Agent 循環、工具調用串聯時，**優先使用 LangGraph** 編排
- LangGraph 的 `StateGraph` 定義放在 `app/graphs/` 目錄
- LangGraph 的 Agent 定義放在 `app/agents/` 目錄
- LangChain 的 Chain、RAG Retriever、文件處理放在 `app/chains/` 目錄

### 5.3 SSE 串流

- FastAPI 使用 `StreamingResponse` 搭配 `text/event-stream` Content-Type
- Nginx 務必設定以下參數以支援 SSE：
  ```nginx
  proxy_buffering off;
  proxy_cache off;
  proxy_read_timeout 300s;
  chunked_transfer_encoding on;
  ```
- Vue 3 前端使用原生 `EventSource` 或封裝的 SSE client 接收串流

### 5.4 環境管理

- Python 環境統一使用 **uv** 管理，`uv.lock` 必須入版控
- 不使用 `pip install` 直接安裝，所有套件透過 `pyproject.toml` 管理
- `.env` 不入版控，`.env.example` 必須入版控並保持更新

### 5.5 安全性

- 所有敏感設定（API Key、資料庫密碼）只能放在環境變數中，絕不寫入程式碼或入版控
- Laravel 使用 Role-Based Access Control（RBAC，角色為基礎的存取控制）進行權限控管，FastAPI 端的 AI 服務對 Laravel 的內部呼叫需驗證來源
- 前端呼叫 AI SSE 端點需通過 Nginx 路由，不直接暴露 FastAPI port

#### JWT 認證規範

所有對外 API 統一使用 **JWT（JSON Web Token）** 進行身份認證，遵守以下規範：

**發行與驗證**
- JWT 由 **Laravel** 負責發行（登入、換發）
- FastAPI 收到請求時需驗證 JWT 簽章，不另行發行 Token
- 演算法統一使用 **RS256**（非對稱加密），Laravel 持有私鑰簽發，FastAPI 使用公鑰驗證

**Token 結構**
- `sub`：使用者 ID
- `roles`：使用者角色清單（供 RBAC 判斷）
- `exp`：過期時間
- `iat`：發行時間
- `jti`：Token 唯一識別碼（用於 Revoke 機制）

**有效期與換發**
- Access Token 有效期：**60 分鐘**
- Refresh Token 有效期：**7 天**
- 前端於 Access Token 過期前自動以 Refresh Token 換發，換發後舊 Refresh Token 立即失效（Rotation）

**環境變數（加入 Laravel `.env` 與 FastAPI `.env`）**
```env
# JWT 金鑰（RS256）
JWT_PRIVATE_KEY=          # 私鑰路徑或內容（Laravel 使用）
JWT_PUBLIC_KEY=           # 公鑰路徑或內容（Laravel / FastAPI 共用驗證）
JWT_ACCESS_TOKEN_TTL=3600
JWT_REFRESH_TOKEN_TTL=604800
```

**實作要點**
- Laravel：使用 `php-open-source-saver/jwt-auth` 或 `tymon/jwt-auth` 套件
- FastAPI：使用 `python-jose` 或 `PyJWT` 套件驗證，在 `app/core/security.py` 實作驗證邏輯，並透過 FastAPI Dependency Injection 套用至需要認證的路由
- Refresh Token 的 `jti` 存入 Redis，Revoke 時從 Redis 刪除對應 `jti`
- 所有需要認證的 API 端點須在 Request Header 帶入 `Authorization: Bearer {token}`

---

### 5.6 CORS 設定規範

前後端分離架構下，Laravel 與 FastAPI 均需正確設定 CORS。

**通用原則**
- 正式環境的 `ALLOWED_ORIGINS` 只允許已知前端網域，**不得使用萬用字元 `*`**
- 開發環境可允許 `http://localhost:5173`（Vite 預設 port）
- 允許的 Headers 須包含 `Authorization`、`Content-Type`、`Accept`、`X-Requested-With`
- SSE 端點需額外允許 `Cache-Control` Header

**Laravel（`config/cors.php`）**

```php
'paths' => ['api/*'],
'allowed_origins' => [env('FRONTEND_URL')],
'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
'allowed_headers' => ['Authorization', 'Content-Type', 'Accept', 'X-Requested-With'],
'exposed_headers' => [],
'max_age' => 86400,
'supports_credentials' => true,
```

**FastAPI（`app/core/config.py`）**

```python
CORS_ALLOWED_ORIGINS: list[str] = []   # 從環境變數載入
CORS_ALLOW_CREDENTIALS: bool = True
CORS_ALLOW_METHODS: list[str] = ["GET", "POST", "PUT", "PATCH", "DELETE", "OPTIONS"]
CORS_ALLOW_HEADERS: list[str] = ["Authorization", "Content-Type", "Accept", "Cache-Control"]
```

**環境變數（加入各後端 `.env`）**

```env
FRONTEND_URL=                 # 正式環境前端網址，例如 https://app.example.com
CORS_ALLOWED_ORIGINS=         # 多個來源用逗號分隔
```

---

### 5.7 日誌規範 (Logging)

**通用原則**
- 正式環境日誌等級：`WARNING` 以上
- 開發 / 測試環境日誌等級：`DEBUG`
- 日誌格式統一使用結構化 JSON，方便後續集中收集（ELK / Loki）
- 禁止在日誌中輸出密碼、Token、個人識別資訊（PII）

**Laravel**

使用 Laravel 內建 `Log` Facade，channel 設定於 `config/logging.php`：

```php
// 正式環境建議使用 stack channel，同時寫入 daily 檔案與外部服務
Log::channel('stack')->info('訂單建立', ['order_id' => $id, 'user_id' => $userId]);
```

日誌等級對應：

| 等級 | 使用場景 |
|------|---------|
| `debug` | 開發除錯資訊 |
| `info` | 正常業務事件（訂單建立、使用者登入） |
| `warning` | 非預期但可容忍的狀況（重試、降級） |
| `error` | 需要介入處理的錯誤 |
| `critical` | 服務不可用、資料遺失等嚴重事件 |

**FastAPI**

使用 Python 標準 `logging` 模組，搭配結構化輸出：

```python
import logging
import json

logger = logging.getLogger(__name__)

# 使用方式
logger.info("RAG 查詢完成", extra={"user_id": user_id, "query": query, "duration_ms": duration})
```

- 日誌初始化設定放在 `app/core/config.py`
- 每個模組使用 `logging.getLogger(__name__)` 取得獨立 logger
- 正式環境 log 輸出為 JSON 格式，開發環境可使用純文字格式

---

### 5.8 前端 UI 規範

#### 資料列表頁

- 所有資料列表頁**必須實作分頁**，預設每頁顯示筆數建議為 20 筆
- 資料表格**第一欄固定為流水號**（序號），由前端依當前頁計算，例如第 2 頁第 1 筆 = `(page - 1) * perPage + 1`
- 分頁必須為**伺服器端分頁（Server-side Pagination）**，每次換頁需呼叫 API 並帶入 `page` 與 `per_page` 參數，禁止一次載入全部資料後在前端切頁
- `currentPage`、`perPage`、`total` 統一用 Pinia store 管理

#### 資料操作 Loading 效果

進行任何資料送出（新增、修改、刪除、查詢）時，**必須顯示處理中狀態**，規範如下：

- 觸發操作後立即將按鈕切換為 **disabled + loading 狀態**，防止重複送出
- 畫面層級的操作（如整頁查詢、送出表單）需顯示 **全域 loading 遮罩**，搭配「處理中...」文字與旋轉動畫
- 操作完成（成功或失敗）後立即解除 loading 狀態
- 使用 Pinia store 統一管理 `isLoading` 狀態，不在元件內各自維護

```typescript
// 範例：Options API 風格
export default {
  data() {
    return {
      isLoading: false,
    }
  },
  methods: {
    async handleSubmit() {
      this.isLoading = true
      try {
        await this.submitData()
      } finally {
        this.isLoading = false
      }
    }
  }
}
```

#### 欄位文字對齊

- 畫面中所有欄位標籤（Label）與表格欄位內容，**未特別指定者一律靠左對齊**
- 例外允許靠右：金額、數量等數值欄位
- 例外允許置中：狀態標籤（Badge）、操作按鈕欄

---

### 5.9 命名規範 (Naming Conventions)

#### Laravel (PHP)

| 類型 | 規則 | 範例 |
|------|------|------|
| Controller | PascalCase + `Controller` | `OrderController` |
| Model | PascalCase 單數 | `Order`、`OrderItem` |
| Service | PascalCase + `Service` | `OrderService` |
| Repository | PascalCase + `Repository` | `OrderRepository` |
| Job | PascalCase + `Job` | `ProcessCarbonFootprintJob` |
| Event | PascalCase 動詞過去式 | `OrderCreated`、`UserLoggedIn` |
| Request | PascalCase + `Request` | `CreateOrderRequest` |
| Migration | snake_case，時間戳前綴 | `2024_01_01_000000_create_orders_table` |
| Route | kebab-case | `/api/v1/order-items` |

#### Python / FastAPI

| 類型 | 規則 | 範例 |
|------|------|------|
| 檔案 / 模組 | snake_case | `order_service.py` |
| Class | PascalCase | `OrderService` |
| 函式 / 方法 | snake_case | `get_order_by_id` |
| 常數 | UPPER_SNAKE_CASE | `MAX_RETRY_COUNT` |
| Pydantic Schema（請求） | PascalCase + `Request` | `CreateOrderRequest` |
| Pydantic Schema（回應） | PascalCase + `Response` | `OrderResponse` |
| Celery Task 函式 | snake_case | `process_pcf_batch` |
| LangGraph StateGraph | snake_case | `rag_pipeline`、`supply_chain_agent` |

#### Vue 3 (TypeScript)

| 類型 | 規則 | 範例 |
|------|------|------|
| 元件檔名 | PascalCase | `OrderTable.vue`、`UserCard.vue` |
| 頁面元件（views） | PascalCase + `View` | `OrderListView.vue` |
| Props | camelCase（script）/ kebab-case（template） | `orderList` / `:order-list` |
| Emit 事件 | kebab-case | `@update:model-value`、`@row-click` |
| Pinia store | camelCase，`use` 前綴 | `useOrderStore`、`useAuthStore` |
| API 模組檔 | camelCase | `orderApi.ts`、`authApi.ts` |
| TypeScript 型別 / Interface | PascalCase | `Order`、`PaginationMeta` |

#### 資料庫（通用）

| 類型 | 規則 | 範例 |
|------|------|------|
| Table 名稱 | snake_case，複數 | `orders`、`order_items` |
| Column 名稱 | snake_case | `created_at`、`user_id` |
| 主鍵 | 統一使用 `id`，格式為 **UUID**，未特別說明一律如此 | `id` (UUID) |
| 外鍵 | `{單數資料表名}_id` | `order_id`、`user_id` |
| 索引 | `idx_{table}_{column}` | `idx_orders_user_id` |
| 時間戳記 | 每張表皆須包含 | `created_at`、`updated_at` |
| 軟刪除 | 使用 `deleted_at` | `deleted_at` |

**主鍵 UUID 規範補充**

- 所有資料表主鍵 `id` 欄位，**未特別說明一律使用 UUID 格式**，禁止使用自動遞增整數（Auto Increment）
- 目的：方便跨系統資料遷移、合併，避免 ID 衝突
- Laravel：Model 加入 `use HasUuids;` trait，migration 欄位使用 `$table->uuid('id')->primary()`
- FastAPI（SQLAlchemy）：欄位型別使用 `UUID`，預設值設為 `uuid.uuid4`
- PostgreSQL：欄位型別使用 `UUID`，預設值 `gen_random_uuid()`
- MySQL：欄位型別使用 `CHAR(36)`，應用層產生 UUID 後存入

---

### 5.10 前端 axios 攔截器規範

`src/api/http.ts` 的 axios 實例須實作以下攔截器邏輯，所有 API 模組共用此實例。

**Request 攔截器**
- 自動從 Pinia `useAuthStore` 取得 Access Token，帶入 `Authorization: Bearer {token}` Header

**Response 攔截器**

| 情境 | 處理方式 |
|------|---------|
| `401` Token 過期 | 自動呼叫 Refresh Token API 換發，換發成功後重試原請求 |
| `401` 換發失敗 | 清除 Token，導向登入頁 |
| `422` 驗證失敗 | 將 `errors` 物件回傳給呼叫方處理（不在攔截器彈 toast） |
| `403` 權限不足 | 顯示「權限不足」toast 通知 |
| `429` 頻率限制 | 顯示「操作過於頻繁，請稍後再試」toast |
| `500` 伺服器錯誤 | 顯示「系統發生錯誤，請聯繫管理員」toast |

**Token 換發的 Queue 機制**
- 當多個請求同時收到 `401` 時，只發出一次 Refresh Token 請求
- 其餘請求進入等待佇列，換發完成後統一重試
- 避免同時發出多個 Refresh Token 請求導致競態條件

---

### 5.11 時區統一規範

**核心原則：後端一律以 UTC 儲存，前端顯示時轉換為 `Asia/Taipei`（UTC+8）**

#### 後端

**Laravel**
- `config/app.php` 的 `timezone` 設為 `'UTC'`
- 所有 `Carbon` 物件操作均在 UTC 下進行
- API 回傳的時間格式統一為 **ISO 8601**，例如 `2024-01-15T08:30:00Z`

**FastAPI**
- 所有 `datetime` 物件須為 **timezone-aware UTC**，使用 `datetime.now(timezone.utc)`
- Pydantic Schema 的時間欄位型別使用 `datetime`，序列化時自動輸出 ISO 8601
- 禁止使用 `datetime.now()`（naive datetime，無時區資訊）

**資料庫**
- PostgreSQL：時間欄位使用 `TIMESTAMP WITH TIME ZONE`
- MySQL：時間欄位使用 `DATETIME`，MySQL server `time_zone` 設為 `'+00:00'`

**環境變數（加入各後端 `.env`）**
```env
APP_TIMEZONE=UTC
```

#### 前端

- 安裝 `dayjs` 並啟用 `utc` 與 `timezone` 插件
- 全域初始化於 `main.ts`：
  ```typescript
  import dayjs from 'dayjs'
  import utc from 'dayjs/plugin/utc'
  import timezone from 'dayjs/plugin/timezone'
  dayjs.extend(utc)
  dayjs.extend(timezone)
  dayjs.tz.setDefault('Asia/Taipei')
  ```
- 顯示 API 回傳的時間一律透過 `dayjs.tz(value).format()`，禁止直接渲染原始 UTC 字串

---

### 5.12 測試規範

#### Laravel（PHPUnit）

- Feature Test：測試 API 端點的完整請求 / 回應流程，放於 `tests/Feature/`
- Unit Test：測試單一 Service / Repository 的邏輯，放於 `tests/Unit/`
- 測試函式命名：`test_{情境}_{預期結果}`，例如 `test_create_order_returns_201`
- 測試資料使用 **Factory**，禁止在測試中直接寫死 ID 或固定資料
- 每個 Feature Test 使用 `RefreshDatabase` trait，確保測試間資料互不干擾
- 最低覆蓋率目標：**Service 層 70%**

#### FastAPI（pytest）

- 端點測試放於 `tests/test_api/`，使用 `httpx.AsyncClient` 發送請求
- 服務邏輯測試放於 `tests/test_services/`
- 非同步測試使用 `pytest-asyncio`，函式加上 `@pytest.mark.asyncio`
- 測試函式命名：`test_{情境}_{預期結果}`，例如 `test_rag_query_returns_stream`
- 使用 `pytest-cov` 追蹤覆蓋率，最低目標：**Service 層 70%**
- LangGraph / Celery 任務的整合測試需 mock 外部 LLM 呼叫，禁止在 CI 中真實呼叫 LLM API

---

### 5.13 Rate Limiting 規範

**Laravel（`throttle` Middleware）**

在 `routes/api.php` 依端點類型套用不同限流策略：

| 端點類型 | 限制 | Middleware 寫法 |
|---------|------|----------------|
| 一般 API | 每分鐘 60 次 | `throttle:60,1` |
| 認證端點（登入 / 換發） | 每分鐘 10 次 | `throttle:10,1` |
| 高成本操作（批量匯入等） | 每分鐘 10 次 | `throttle:10,1` |

**FastAPI（`slowapi`）**

```python
# 在 app/core/config.py 初始化 Limiter
from slowapi import Limiter
from slowapi.util import get_remote_address
limiter = Limiter(key_func=get_remote_address)
```

| 端點類型 | 限制 |
|---------|------|
| 一般 API | 每分鐘 60 次 |
| AI 推論 / RAG 查詢 | 每分鐘 20 次（運算成本高） |
| SSE 串流端點 | **不限流**（長連接，限流會中斷串流） |

**超限回應**
- HTTP `429 Too Many Requests`
- 回應 Header 帶入 `Retry-After` 秒數
- 前端攔截器統一顯示「操作過於頻繁，請稍後再試」toast（見 5.10）