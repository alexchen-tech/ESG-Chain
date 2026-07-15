# ESG·Chain — Claude Code 專案設定

永續供應鏈管理平台，永創數智開發。提供品牌採購商、永續團隊、法遵部門訂閱使用，並提供供應商入口（Portal）讓上游廠商填寫問卷與碳排填報。

---

## 系統邊界（最重要，永遠優先）

**ESG-Chain 是 ERP 資料之上的永續情報層，不是 ERP 替代品。**

```text
ERP（唯一主檔來源）
  供應商 / 物料 / BOM / 出口裝運
      ↓  CSV上傳 | Webhook推送 | 排程拉取
ESG-Chain（加入永續情報）
  ├─ 永續韌性：SAQ問卷 → AI評分 → 風險矩陣 → CAP矯正行動
  └─ 產品合規：BOM碳排 → PCF計算 → CBAM/EUDR/UFLPA申報
      ↓
輸出：CSRD / PCF報告 / CBAM申報 / EUDR DDS
```

### 欄位歸屬（ERP sync 時嚴格遵守）

| 擁有者        | 實體              | 欄位                                                                                  | Sync 行為          |
|---------------|-------------------|---------------------------------------------------------------------------------------|--------------------|
| **ERP**       | Supplier          | code, name, hs_code, quantity, supplier_code, status                                  | 每次同步可覆蓋     |
| **ERP**       | SalesProduct      | name, product_code, hs_code, quantity（出口申報主檔）                                 | 每次同步可覆蓋     |
| **ESG-Chain** | Supplier          | onboarding_stage, saq_score, risk_level, emission_factor                              | 永不被 sync 覆蓋   |
| **ESG-Chain** | SalesProduct      | applicable_regulations, inferred_regulations, embedded_emissions, emissions_source    | 永不被 sync 覆蓋   |

> **SalesProduct（銷售產品）** = 原 TradeGood，是唯一的產品主檔。BuyerProduct（採購商品）已廢棄合併至此。
> ERP 負責建立 SalesProduct 基本資料；ESG-Chain 負責在其上附加 ESG 情報（法規、碳排、BOM）。

### 禁止事項

- ❌ 不可手動建立 ERP 已管理的實體（Supplier code/name、MaterialItem item_code、BOM quantity、SalesProduct hs_code）
- ❌ ERP sync 時不可覆蓋 `onboarding_stage`、`saq_score`、`risk_level`、`emission_factor`、`applicable_regulations`、`inferred_regulations`
- ❌ 計算邏輯（SAQ評分、PCF計算）不可寫在 esgchain-api，一律 call esgchain-ai
- ❌ PcfSnapshot append-only，不可更新或刪除舊版本
- ❌ Shipment 建立後 `snapshot_id` 鎖定，不隨後續 PCF 重算自動變動
- ❌ BomLine 不可形成循環參照（A→B→A），新增時呼叫 `ProductBomLineService::assertNoCycle()`

---

## 供應商狀態機

`onboarding_stage`（ESG-Chain 擁有）只有三個值，與 ERP `status` 語意保持一致：

```text
active ──→ suspended ──→ terminated
             └──→ active
```

- `status`（ERP 擁有，active/inactive/suspended）：唯讀，不可從 UI 修改
- `onboarding_stage`（ESG-Chain 擁有）：透過 `POST /api/v1/suppliers/{id}/onboarding-transition` 變更，每次需留稽核日誌

---

## 三層架構

| 服務               | 技術                                                  | 職責                    | Port |
|--------------------|-------------------------------------------------------|-------------------------|------|
| **esgchain-web**   | Vue 3 + Vite + Pinia + TypeScript                     | 前端 SPA                | 5173 |
| **esgchain-api**   | Laravel 12 / PHP 8.5 + MySQL 8.4                      | 業務流程、狀態機、CRUD  | 8081 |
| **esgchain-ai**    | FastAPI / Python 3.12 + PostgreSQL + Celery + Redis   | 評分、PCF計算、AI       | 8000 |

**Nginx** 路由：`/api` → Laravel、`/ai` → FastAPI、`/` → Vue 3（SSE：`proxy_buffering off`）

### 資料庫歸屬

- **MySQL**（esgchain-api）：Supplier, SAQ, CAP, BOM, Shipment, PcfRequest, MaterialItemEmission
- **PostgreSQL**（esgchain-ai）：EmissionFactor, ScoringModel, SasbIndustry, RiskAssessment

### JWT 分工

- **esgchain-api**：RS256 私鑰發行與換發，Payload `{ sub, roles, supplierId, exp, iat, jti }`
- **esgchain-ai**：RS256 公鑰驗證，只驗不發
- 供應商角色（`supplier` / `sup_esg`）登入後導向 `/supplier/portal`

---

## Docker 同步規則 ⚠️

每次 `docker cp` Laravel 檔案後，**必須** `docker restart esgchain-api`，否則 PHP-FPM 不載入新檔案。

```bash
# Laravel 標準同步流程
docker cp <本地路徑> esgchain-api:<容器路徑>
docker restart esgchain-api
# 3秒後驗證
curl -s -X POST http://localhost:8081/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@esgchain.com","password":"demo1234"}' | head -c 80

# Vue 前端同步（觸發 Vite HMR）
docker cp <本地路徑> esgchain-web:<容器路徑>
docker exec esgchain-web touch <容器路徑>
```

### 常見陷阱

- **`docker restart` 未生效**：若容器內缺少新增的目錄/類別（如全新 Service 命名空間），單純 restart 可能無法正常啟動或仍讀舊碼。改用 `docker compose up -d esgchain-api`；仍不行則 `docker compose build esgchain-api && docker compose up -d esgchain-api` 重建 image。
- **`esgchain-queue-worker` 容易被漏同步**：它與 esgchain-api 共用程式碼但是獨立容器。新增/修改 Job、Listener 類別後，若只同步 API 容器，worker 執行舊碼會導致排入佇列的任務反序列化失敗。務必同步重建/重啟 `esgchain-queue-worker`。
- **新增路由後 404**：容器若執行過 `route:cache`，新路由要 `docker exec esgchain-api php artisan route:cache` 重建快取才會生效。

---

## 程式碼慣例

### 通用

- 註解與 UI 文字使用**繁體中文**
- 新增資料格式後，自動填入預設 seed 資料
- 未被要求修改時，保留既有程式碼

### Laravel（esgchain-api）

- Model 一律加 `use HasUuids`，主鍵為 UUID
- 路由 kebab-case：`/api/v1/supply-chain`
- Controller 僅轉發請求，業務邏輯放 Service，每個 action 對應一個 Service method

### FastAPI（esgchain-ai）

- datetime 一律 timezone-aware UTC：`datetime.now(timezone.utc)`
- Pydantic Schema：Request 類別加 `Request` 後綴，Response 加 `Response` 後綴
- 路由 kebab-case：`/ai/v1/saq-scoring`
- 計算密集任務一律透過 Celery 非同步執行

### Vue 3（esgchain-web）

- 元件風格：**Options API**（不使用 Composition API）
- 頁面元件命名：`XxxView.vue`，放 `views/`
- 所有列表頁實作 Server-side Pagination，每頁固定 **20 筆**（`per_page: 20`）
- 操作按鈕立即 disabled + loading，防止重複送出
- 數字欄位加 `font-mono` class

---

## RBAC 角色權限

| 角色                   | 可存取模組                                           |
|------------------------|------------------------------------------------------|
| `admin`                | 全部（含 settings, portal）                          |
| `buyer`                | dashboard, suppliers, tradegoods, cap                |
| `sustain`              | dashboard, suppliers, saq, cap, reports              |
| `comply`               | dashboard, suppliers, saq, cap, tradegoods, reports  |
| `analyst`              | dashboard, suppliers, saq, reports                   |
| `supplier` / `sup_esg` | portal                                               |

---

## 測試帳號（密碼均為 `demo1234`）

| 角色       | Email                      |
|------------|----------------------------|
| 管理員     | `admin@esgchain.com`       |
| 採購商     | `buyer@esgchain.com`       |
| 永續長     | `sustain@esgchain.com`     |
| 分析師     | `analyst@esgchain.com`     |
| 供應商     | `supplier1@tpsteel.com.tw` |
| 供應商 ESG | `esg@vge.vn`               |

---

## 設計系統

Warm Paper Light。色票與字型定義在 `esgchain-web/src/assets/global.css`。
強調色 `--accent: #1a4d3e`（深綠），側邊欄 `--sidebar-bg: #1a1714`。

---

## 規格開發

新增或修改功能時，參照 `openspec/changes/` 下對應的 `proposal.md / design.md / tasks.md`。
業務細節（PCF 計算邏輯、BOM 匯入規則、事件觸發鏈）記錄在 openspec，不寫入本檔。
