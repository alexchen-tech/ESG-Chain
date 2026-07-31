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
輸出：CSRD / PCF報告 / CBAM申報 / EUDR DDS 草稿
```

> **系統功能邊界止於「出口前合規檢查」**：ESG-Chain 只負責在生產批號出口前判斷法規義務是否達標、產出合規佐證文件（如 EUDR DDS 草稿）。實際出口交易執行——客戶/PO 綁定、報關、多批集貨、送件狀態追蹤——屬 ERP 範疇，不在本系統內建置（曾規劃過的「出口申報 Shipment」模組已移除，相關合規文件產出改掛在生產批號詳情頁）。

### 欄位歸屬（ERP sync 時嚴格遵守）

| 擁有者        | 實體              | 欄位                                                                                  | Sync 行為          |
|---------------|-------------------|---------------------------------------------------------------------------------------|--------------------|
| **ERP**       | Supplier          | code, name, hs_code, quantity, supplier_code, status                                  | 每次同步可覆蓋     |
| **ERP**       | SalesProduct      | name, product_code, hs_code, quantity（銷售產品主檔）                                 | 每次同步可覆蓋     |
| **ESG-Chain** | Supplier          | onboarding_stage, saq_score, risk_level, emission_factor                              | 永不被 sync 覆蓋   |
| **ESG-Chain** | SalesProduct      | applicable_regulations, inferred_regulations, embedded_emissions, emissions_source    | 永不被 sync 覆蓋   |

> **SalesProduct（銷售產品）** = 原 TradeGood，是唯一的產品主檔。BuyerProduct（採購商品）已廢棄合併至此。
> ERP 負責建立 SalesProduct 基本資料；ESG-Chain 負責在其上附加 ESG 情報（法規、碳排、BOM）。

### 禁止事項

- ❌ 不可手動建立 ERP 已管理的實體（Supplier code/name、MaterialItem item_code、BOM quantity、SalesProduct hs_code）
- ❌ ERP sync 時不可覆蓋 `onboarding_stage`、`saq_score`、`risk_level`、`emission_factor`、`applicable_regulations`、`inferred_regulations`
- ❌ 計算邏輯（SAQ評分、PCF計算）不可寫在 esgchain-api，一律 call esgchain-ai
- ❌ PcfSnapshot append-only，不可更新或刪除舊版本
- ❌ BomLine 不可形成循環參照（A→B→A），新增時呼叫 `ProductBomLineService::assertNoCycle()`

---

## 供應商狀態機

`onboarding_stage`（ESG-Chain 擁有）只有三個值，與 ERP `status` 語意保持一致：

```text
active ──→ suspended ──→ terminated
             └──→ active
```

- `status`（ERP 擁有，僅 active/inactive 兩種，active 為匯入時預設值）：唯讀，不可從 UI 修改，每次 ERP 同步依匯入資料決定並留稽核歷程
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

- **MySQL**（esgchain-api）：Supplier, SAQ, CAP, BOM, ProductionBatch, BatchExportReview, PcfRequest, MaterialItemEmission
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

- 註解與 UI 文字使用**繁體中文**，不可中英文夾雜：畫面上顯示給使用者看的功能名稱、分類名稱、狀態標籤等，一律使用繁體中文，不可直接顯示程式內部識別碼（如模組 key、enum value、permission 字串）當作標籤。若畫面需要依內部 key 分組或列舉（例如權限模組、狀態機值），須在該元件內建立 key→中文標籤的對照表（如 `RolesView.vue` 的 `MODULE_LABELS`），不可用 `{{ rawKey }}` 直接輸出。已有既定中文譯名的專有名詞縮寫（如「CAP 矯正行動」「PCF」「SAQ」）視為既有慣例可保留，不在此限
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
- 側邊欄選單（`AppSidebar.vue`）功能項目改掛到不同功能群組時，**必須同步修改路由 path 前綴**（`router/index.ts` 與相關頁面內的連結），使路由結構與選單分組一致，不可只搬選單、路由留在舊分組前綴下
- 新增「單一資料多分頁」詳情頁時，沿用 `components.css` 共用的 `.detail-tabs`/`.detail-grid` 等樣式（見下方「設計系統」章節），不要在頁面自己的 `<style scoped>` 重新定義

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

> 此表為簡化版模組層級參考，實際路由層級的權限判斷以 `role-permission-management`／`crud-permission-granularity` 建立的權限目錄（`permissions`/`role_has_permissions`/`model_has_permissions`）為準，此表可能與細粒度權限現況有落差，不可直接當作程式判斷依據。

### 統一使用者權限管理原則

- 權限模型一律遵守 **RBAC + CRUD 動作粒度**：權限字串格式固定為 `模組.動作`，動作限定 `view`/`create`/`update`/`delete` 四種（或因角色白名單不同而需要的更細分類），不可用單一模組級開關（如 `module.manage`）籠統控制一整個模組的所有操作
- 使用者權限一律先**繼承角色**（`role_has_permissions`），需要例外時才疊加個人直接授權（spatie `model_has_permissions`），不可略過角色直接為每個使用者手動兜權限
- **前端可操作性與後端存取權限必須一致**：畫面上「這個使用者看不看得到某個編輯/修改按鈕」，要跟後端該功能對應 API 路由所需的權限字串完全對應——不可只在前端用角色名稱擋按鈕、後端路由卻沒有對應的 `permission:module.action` middleware（或反過來，後端有擋、前端卻沒藏起來造成操作了才被 403）
- 新增任何「可編輯/修改」的功能（頁面、按鈕、API）時，一律要同步：(1) 在權限目錄新增對應 `module.action` 權限字串、(2) 後端路由掛 `permission:module.action` middleware、(3) 前端依使用者有效權限（角色繼承 + 個人覆寫）決定是否顯示/啟用該操作，三者缺一不可
- admin 角色與 admin 使用者一律視為固定擁有全部權限，不受角色權限矩陣或個人覆寫調整，避免管理員自我鎖死

---

## 測試帳號（密碼均為 `demo1234`）

| 角色       | Email                         |
|------------|-------------------------------|
| 管理員     | `admin@esgchain.com`          |
| 採購商     | `buyer@esgchain.com`          |
| 永續長     | `sustain@esgchain.com`        |
| 分析師     | `analyst@esgchain.com`        |
| 供應商     | `supplier1@twspinning.com.tw` |
| 供應商 ESG | `esg@vietgarment.vn`          |

---

## 設計系統

Warm Paper Light。色票與字型 token 定義在 `esgchain-web/src/assets/main.css`（`:root` 變數），
共用元件樣式（卡片、表格、badge、表單、詳情頁 grid 等）在 `esgchain-web/src/assets/components.css`，
兩者由 `main.css` 於檔首 `@import './components.css'` 一併載入，全站頁面共用，不建立 `global.css`。

強調色 `--accent: #1a4d3e`（深綠），側邊欄 `--sidebar-bg: #1a1714`。
`--accent-soft` / `--accent-soft-border` 為強調色淡底，用來標出頁面裡的重點數字/區塊
（KPI 卡、hero 指標），跟一般中性灰背景（`--surface-2`）區隔出層次，不要用純灰底表示重點內容。
`--accent-2`（暖陶土色）保留給少數需要跟主色區隔的第二重點，非必要不新增其他強調色，避免整站色彩雜亂。

**單一資料多分頁詳情頁**（例：供應商/物料/銷售產品詳情頁，網址列一個 `{id}`、上方一排 tab 切換概況/歷史/合規等分頁）
一律共用 `components.css` 裡的 `.detail-tabs` / `.detail-tab` / `.tab-panel-wrap` / `.detail-section` /
`.section-title` / `.detail-grid` / `.detail-item` / `.detail-label` / `.detail-value` / `.detail-link`
這一整套樣式，**不可在頁面自己的 `<style scoped>` 裡重新定義同名 class**——之前供應商/物料/銷售產品三個
詳情頁各自維護過一份，長期下來字級、格線粗細、tab 樣式都各自漂移到不一致的樣子，才回頭統一成共用版本。
新增這類詳情頁時直接沿用共用 class 即可，不需要也不應該覆寫。

---

## 規格開發

新增或修改功能時，參照 `openspec/changes/` 下對應的 `proposal.md / design.md / tasks.md`。
業務細節（PCF 計算邏輯、BOM 匯入規則、事件觸發鏈）記錄在 openspec，不寫入本檔。
