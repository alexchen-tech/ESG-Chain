# ESG·Chain — 開發任務清單 (Tasks)

任務依模組分組，每個任務標註優先級（P0 = 必須 / P1 = 重要 / P2 = 次要）、負責層（前端 / 後端 / AI）、以及對應 FRS 編號。

---

## 基礎建設（Foundation）

- [ ] **[P0][後端]** 初始化 esgchain-api（Laravel 12.11.1 + PHP 8.5.1）
- [ ] **[P0][後端]** 設定 JWT RS256 認證（`php-open-source-saver/jwt-auth`）
- [ ] **[P0][後端]** 實作 Refresh Token Queue 機制（Redis jti 存儲 + Rotation）
- [ ] **[P0][後端]** 設定 CORS、Rate Limiting（throttle middleware）
- [ ] **[P0][後端]** 統一 API 回應格式（`success / data / pagination / error_code`）
- [ ] **[P0][前端]** 初始化 esgchain-web（Vue 3 + TypeScript + Pinia + Vite）
- [ ] **[P0][前端]** 實作 `src/api/http.ts` axios 攔截器（含 Token 換發 Queue）
- [ ] **[P0][前端]** 實作 `src/utils/datetime.ts`（`formatDateTime` / `formatDate`）
- [ ] **[P0][前端]** 設定 dayjs（utc + timezone 插件，預設 Asia/Taipei）
- [ ] **[P0][AI]** 初始化 esgchain-ai（FastAPI 0.115+ + Python 3.12.4 + uv）
- [ ] **[P0][AI]** 設定 PostgreSQL + pgvector 連線
- [ ] **[P0][AI]** 設定 Celery + Redis（Broker + Result Backend）
- [ ] **[P0][Infra]** 設定 Nginx（SSE proxy_buffering off、路由分發）
- [ ] **[P0][Infra]** 建立 `docker-compose.yml`（本地開發）
- [ ] **[P0][Infra]** 建立 `docker-compose.prod.yml`（正式環境）

---

## M1 供應商主資料管理（FR-1）

- [ ] **[P0][後端]** Migration：`suppliers` 資料表（UUID 主鍵、SASB 分類欄位）
- [ ] **[P0][後端]** Migration：`supplier_groups` 資料表
- [ ] **[P0][後端]** CRUD API：`GET /api/v1/suppliers`（伺服器端分頁）
- [ ] **[P0][後端]** CRUD API：`POST /api/v1/suppliers`
- [ ] **[P0][後端]** CRUD API：`PUT /api/v1/suppliers/{id}`
- [ ] **[P0][後端]** CRUD API：`DELETE /api/v1/suppliers/{id}`（軟刪除）
- [ ] **[P1][後端]** API：`GET /api/v1/suppliers/{id}/risk-summary`（E/S/G/GP 四維度分數）
- [ ] **[P0][前端]** 供應商列表頁（`SupplierListView.vue`）：伺服器端分頁、流水號、搜尋篩選
- [ ] **[P0][前端]** 供應商新增 / 編輯表單
- [ ] **[P1][前端]** 供應商詳細頁（含 E/S/G/GP 風險概覽）

---

## M2 ESG 問卷作業管理（FR-2）

### 問卷範本

- [ ] **[P0][後端]** Migration：`questionnaire_templates` 資料表
- [ ] **[P0][後端]** CRUD API：問卷範本管理（FR-9.4.x 由 M9 提供範本，M2 引用）

### 問卷發送與生命週期

- [ ] **[P0][後端]** Migration：`supplier_questionnaires` 資料表（含 `status` enum 七狀態）
- [ ] **[P0][後端]** Migration：`saq_review_history` 資料表（稽核追蹤）
- [ ] **[P0][後端]** API：`POST /api/v1/questionnaires/send`（發送問卷，支援多輪）
- [ ] **[P0][後端]** API：`GET /api/v1/questionnaires`（列表，支援狀態篩選）
- [ ] **[P0][後端]** API：`POST /api/v1/questionnaires/{id}/submit`（供應商提交）
- [ ] **[P0][後端]** API：`POST /api/v1/questionnaires/{id}/start-review`（開始審核，狀態 → `under_review`）
- [ ] **[P0][後端]** API：`POST /api/v1/questionnaires/{id}/complete-review`（審核通過，狀態 → `completed`）
- [ ] **[P0][後端]** API：`POST /api/v1/questionnaires/{id}/return-review`（退回修改，狀態 → `review_returned`）
- [ ] **[P0][後端]** 狀態機守衛：`under_review` 狀態下供應商角色存取回傳 **403**
- [ ] **[P0][後端]** `isEditable` 欄位邏輯：`under_review`、`completed`、`reviewed` 回傳 `false`
- [ ] **[P0][後端]** 計數 API：`justSubmittedCount`（僅 `submitted`）vs `submittedCount`（累計）
- [ ] **[P1][後端]** 里程碑追蹤：`reviewStartedAt`、`reviewedById` 自動記錄
- [ ] **[P0][前端]** 問卷列表頁（狀態 Badge、計數 KPI 看板）
- [ ] **[P1][前端]** 問卷詳細頁（狀態流程時間軸）
- [ ] **[P0][前端]** 問卷填報介面（供應商視角，`under_review` 時全表單 disabled）

---

## M3 風險與稽核管理（FR-3）

- [ ] **[P0][後端]** Migration：`risk_assessments` 資料表（E/S/G/GP 四欄獨立分數）
- [ ] **[P0][後端]** API：`GET /api/v1/risk/matrix?dimension={E|S|G|GP}`（回傳 5×5 矩陣格子資料 + 供應商清單）
- [ ] **[P1][後端]** API：風險評分計算邏輯（依問卷回覆自動計算）
- [ ] **[P0][前端]** 5×5 風險矩陣熱圖元件（`RiskMatrix.vue`）
  - 色彩：極低 #85B7EB → 低 #97C459 → 中 #F5C842 → 高 #E8863A → 極高 #A32D2D
  - 互動：點擊格子 → 右側顯示供應商清單
  - 維度切換：E / S / G / GP Tab 切換
- [ ] **[P1][後端]** CAP（改善行動計畫）追蹤 API
- [ ] **[P2][後端]** 工人申訴機制 API（QR Code + 身份加密，FR-3.6）
- [ ] **[P0][後端]** 即時警示推播（Email + 站內通知，延遲 ≤ 5 分鐘，FR-3.7）

---

## M4 交易往來商品管理（FR-4）

- [ ] **[P1][後端]** Migration：`commodities` 資料表
- [ ] **[P1][後端]** CRUD API：商品管理
- [ ] **[P2][後端]** LCC 分析 API（ISO 20400 §7.2.3）
- [ ] **[P1][前端]** 商品列表 / 管理頁面

---

## M5 產品碳足跡（FR-5）

- [ ] **[P0][AI]** Celery Task：`calculate_pcf_batch`（批量 PCF 計算）
- [ ] **[P0][後端]** API：`POST /api/v1/pcf/calculate`（觸發 Celery 任務，回傳 task_id）
- [ ] **[P0][後端]** API：`GET /api/v1/pcf/status/{task_id}`（查詢計算進度）
- [ ] **[P1][前端]** PCF 計算觸發介面（含進度顯示）
- [ ] **[P1][前端]** PCF 結果報告頁面

---

## M6 去碳化（FR-6）

- [ ] **[P1][後端]** Migration：`decarbonization_targets` 資料表（SBTi 目標）
- [ ] **[P1][後端]** API：去碳化路徑管理
- [ ] **[P2][前端]** 減碳路徑可視化圖表

---

## M7 報告（FR-8）

- [ ] **[P0][後端]** API：`GET /api/v1/reports/scope3`（Scope 3 十五類別排放彙總，FR-8.7）
- [ ] **[P1][後端]** 報告匯出（Excel / PDF）
- [ ] **[P0][前端]** Scope 3 報告頁面（十五類別表格 + 圖表）

---

## M8 供應商入口網站（FR-8）

- [ ] **[P0][後端]** 供應商獨立認證流程（Token 與主系統隔離或分 Guard）
- [ ] **[P0][前端]** 供應商入口登入頁
- [ ] **[P0][前端]** 供應商問卷填報頁（與 M2 共用元件，供應商視角）
- [ ] **[P0][前端]** `under_review` 狀態：全表單 disabled + 說明文字「問卷審核中，暫時無法編輯」

---

## M9 系統設定（FR-9）

- [ ] **[P0][後端]** Migration：`questionnaire_templates` + `template_sections` + `template_questions`（FR-9.4.x 階層）
- [ ] **[P0][後端]** CRUD API：問卷範本設計（FR-9.4.1 ~ FR-9.4.7）
- [ ] **[P0][後端]** CRUD API：供應商分組管理（FR-9.2）
- [ ] **[P0][後端]** CRUD API：SASB 產業分類管理（FR-9.3）
- [ ] **[P0][前端]** 問卷範本設計工具（拖拉排序題目、支援多語言題目、選填規則）
- [ ] **[P1][前端]** 供應商分組管理頁面
- [ ] **[P1][前端]** SASB 產業分類管理頁面

---

## 命名規範快速參考

### Laravel

| 類型 | 規則 | ESG·Chain 範例 |
|------|------|--------------|
| Controller | PascalCase + Controller | `QuestionnaireController` |
| Model | PascalCase 單數 | `SupplierQuestionnaire` |
| Service | PascalCase + Service | `QuestionnaireService` |
| Repository | PascalCase + Repository | `RiskAssessmentRepository` |
| Job | PascalCase + Job | `CalculatePcfBatchJob` |
| Event | PascalCase 動詞過去式 | `QuestionnaireSubmitted` |
| Request | PascalCase + Request | `SendQuestionnaireRequest` |

### FastAPI（Python）

| 類型 | 規則 | 範例 |
|------|------|------|
| 檔案 | snake_case | `questionnaire_service.py` |
| Class | PascalCase | `PcfCalculationService` |
| 函式 | snake_case | `calculate_scope3_emissions` |
| Celery Task | snake_case | `calculate_pcf_batch` |
| Pydantic Schema | PascalCase + Request/Response | `SubmitQuestionnaireRequest` |

### Vue 3

| 類型 | 規則 | 範例 |
|------|------|------|
| 元件 | PascalCase | `RiskMatrix.vue`、`QuestionnaireStatusBadge.vue` |
| 頁面 | PascalCase + View | `SupplierListView.vue` |
| Pinia store | use 前綴 + camelCase | `useQuestionnaireStore` |
| API 模組 | camelCase | `questionnaireApi.ts` |
