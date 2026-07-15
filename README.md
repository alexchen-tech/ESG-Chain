# ESG·Chain

永續供應鏈管理平台 — Vue 3 + Laravel + FastAPI

## 快速啟動

### 1. 產生 JWT RS256 金鑰對

```bash
mkdir -p keys
openssl genrsa -out keys/jwt-private.pem 4096
openssl rsa -in keys/jwt-private.pem -pubout -out keys/jwt-public.pem
```

### 2. 複製環境變數

```bash
cp esgchain-api/.env.example esgchain-api/.env
cp esgchain-ai/.env.example esgchain-ai/.env
```

### 3. 啟動所有服務

```bash
docker compose up --build
```

初次啟動後執行資料庫 migration 與 seed：

```bash
docker compose exec esgchain-api php artisan migrate --seed
```

### 4. 驗證認證流程

```bash
# 登入（取得 JWT）
curl -X POST http://localhost:8080/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@esgchain.com","password":"demo1234"}'

# 用 Token 呼叫 FastAPI 受保護端點
curl http://localhost:8000/ai/v1/health/auth \
  -H "Authorization: Bearer <your_token>"
```

## 服務端口

| 服務 | URL |
|------|-----|
| Vue 3 前端 | http://localhost:5173 |
| Laravel API | http://localhost:8080 |
| FastAPI AI | http://localhost:8000 |
| Nginx（統一入口） | http://localhost:80 |
| FastAPI Docs | http://localhost:8000/ai/docs |

## 測試帳號

| 角色 | Email | 密碼 |
|------|-------|------|
| 管理員 | admin@esgchain.com | demo1234 |
| 採購商 | buyer@esgchain.com | demo1234 |
| 永續長 | sustain@esgchain.com | demo1234 |
| 分析師 | analyst@esgchain.com | demo1234 |
