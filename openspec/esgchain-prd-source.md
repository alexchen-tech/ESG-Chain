# ESG·Chain — 產品需求規格書素材
> 供 Claude AI 轉換為正式 PRD 使用
> 版本：v2.0 · 日期：2026-06-05 · 永創數智

---

## 【給 Claude 的指令】

請根據以下所有素材，產出一份完整的「產品需求規格書（PRD）」，格式要求如下：

1. **語言**：繁體中文（技術縮寫保留英文）
2. **結構**：依照標準 PRD 格式（目的與背景 → 目標用戶 → 功能模組 → 使用者故事 → 系統架構 → 資料模型 → API 規格摘要 → 非功能需求 → 驗收條件）
3. **優先級標示**：P0（核心）/ P1（重要）/ P2（加分）
4. **驗收條件格式**：每個需求使用 Given-When-Then 格式
5. **每個功能模組**獨立一個章節，包含：目的、主要 Actor、核心流程、邊界條件

---

## 壹、產品概述

### 1.1 產品定位

**ESG·Chain** 是永創數智開發的**永續供應鏈管理平台（Sustainable Supply Chain Management Platform）**。

**核心價值主張**：
- 幫助品牌採購商管理供應商 ESG 風險
- 追蹤供應鏈碳足跡（Product Carbon Footprint, PCF）
- 滿足歐盟 CBAM / EUDR 等跨境法規合規要求
- 提供供應商問卷（SAQ）自動評分與矯正行動計畫（CAP）管理
- 支援 CSRD / CDP 報告驗證

**商業模式**：
- 品牌採購商（Buyer）、永續團隊、法遵部門訂閱使用
- 供應商入口（Portal）免費提供給上游廠商填寫問卷與查閱矯正行動

### 1.2 設計哲學（三層驅動鏈）

```
ISO 26000（社會責任哲學）
    ↓
ISO 20400（永續採購指引）
    ↓
ESG·Chain 功能設計
```

### 1.3 目標用戶

| 角色 | 職稱/部門 | 主要使用場景 |
|------|---------|------------|
| `admin` | 平台管理員 | 全功能管理、系統設定、用戶管理 |
| `buyer` | 採購部門 | 供應商管理、貿易商品合規、出口申報 |
| `sustain` | 永續長/永續部門 | SAQ 問卷管理、矯正行動、ESG 報告 |
| `comply` | 法遵部門 | 合規審核、CBAM/EUDR 報告、矩陣視角 |
| `analyst` | 分析師 | 數據分析、ESG 評分、風險矩陣 |
| `supplier` | 供應商主要聯絡人 | 填寫問卷、查閱 CAP（Portal） |
| `sup_esg` | 供應商 ESG 專員 | 填寫 ESG 資料、上傳文件（Portal） |

### 1.4 RBAC 權限矩陣

| 功能模組 | admin | buyer | sustain | comply | analyst | supplier | sup_esg |
|---------|-------|-------|---------|--------|---------|----------|---------|
| 儀表板 | ✓ | ✓ | ✓ | ✓ | ✓ | — | — |
| 供應商管理 | ✓ | ✓ | ✓ | ✓ | ✓ | — | — |
| 永續問卷（SAQ） | ✓ | — | ✓ | ✓ | ✓ | — | — |
| 矯正行動（CAP） | ✓ | ✓ | ✓ | ✓ | — | — | — |
| 貿易商品（Trade Goods） | ✓ | ✓ | — | ✓ | — | — | — |
| 商品合規管理 | ✓ | ✓ | — | ✓ | — | — | — |
| 報告管理 | ✓ | — | ✓ | ✓ | ✓ | — | — |
| 系統設定 | ✓ | — | — | — | — | — | — |
| 供應商入口（Portal） | ✓ | — | — | — | — | ✓ | ✓ |

---

## 貳、功能模組規格

### 模組一：供應商主檔管理（Supplier MDM）

**目的**：建立並維護供應商完整主檔，支援多層級供應鏈管理，記錄 ESG 相關資訊與稽核歷程。

**核心實體**：`Supplier`、`SupplierContact`、`SupplierGroup`、`SupplierStatusHistory`

#### 1.1 供應商基本資訊

**必填欄位**：
- `name`：供應商名稱
- `tier`：供應商層級（Tier 1 / 2 / 3）
- `status`：狀態（active / inactive / suspended）

**選填欄位**：
- `code`：供應商代碼（系統唯一）
- `country_code`：國家地區碼（ISO 3166-1 alpha-2）
- `industry`：產業描述
- `sasb_industry_id`：SASB 產業分類（關聯 SASB 標準）
- `group_id`：所屬供應商群組
- `address`：公司地址
- `website`：公司網站
- `vat_number`：統編 / VAT 稅籍號碼
- `erp_vendor_codes`：ERP 廠商代碼（多組以陣列儲存）
- `spend_amount`：年採購金額（USD）
- `risk_score`：綜合風險評分（由 esgchain-ai 計算）
- `profile_completed`：資料完整度旗標

#### 1.2 供應商狀態流轉

```
潛在（potential）
    → 邀請中（invited）
    → 審核中（reviewing）
    → 已認證（certified）
    → 暫停（suspended）
    → 終止（terminated）
```

**規則**：
- 每次狀態變更需記錄 `SupplierStatusHistory`（from_status / to_status / reason / changed_by / timestamp）
- 狀態變更 API：`POST /api/v1/suppliers/{id}/transition`

#### 1.3 聯絡人管理

- 每位供應商可有多個聯絡人（`SupplierContact`）
- 欄位：name / title / email / phone / is_primary
- 同一供應商只能有一個 is_primary = true
- 操作 API：`POST/PUT/DELETE /api/v1/suppliers/{id}/contacts/{contactId}`

#### 1.4 合規文件（SupplierComplianceDoc）

文件效期狀態動態計算：
- `valid`：expires_at > 今日 + 30 天
- `expiring_soon`：今日 < expires_at ≤ 今日 + 30 天
- `expired`：expires_at ≤ 今日
- `pending`：expires_at 為 null

**觸發 CAP**：expiring_soon / expired 狀態的文件，每日排程自動建立矯正行動計畫。

#### 1.5 供應商合規健康度

健康度指標彙總（`GET /api/v1/suppliers/{id}/compliance-docs`）：
```json
{
  "total_docs": 12,
  "valid_count": 8,
  "expiring_soon_count": 2,
  "expired_count": 1,
  "pending_count": 1,
  "missing_required_types": ["CMRT", "SDS"]
}
```

缺漏文件來源（取聯集）：
1. 供應商 TradeGoods 綁定的 MaterialGroup.required_doc_types
2. 採購商 ProductBomLines 中 bom_line_suppliers 指向此供應商的物料群組需求

---

### 模組二：永續問卷管理（SAQ）

**目的**：以「問卷專案」為單位，系統化管理品牌採購商向供應商發送、回收、評分、審核問卷的完整流程。

#### 2.1 問卷範本（SAQTemplate）

**範本生命週期**：草稿（draft）→ 發布（published）

**欄位**：
- `name`：範本名稱
- `domain`：對應 L1 領域（用於計分過濾）
- `questions`：題目清單（JSON 結構）
- `tag_assignments`：題目標籤（關聯 QuestionTag）
- `compliance_domains`：合規範疇標記（CMRT / EUDR / UFLPA / SDS / CE）

**題目庫**：
- 管理 URL：`/settings/question-bank`
- 支援依 compliance_domains 篩選
- BankImportModal 顯示 compliance_domains chip，輔助問卷設計

#### 2.2 SAQ 問卷專案（SaqProject）

**狀態機**：

```
draft ──(首次發送)──▶ active ──(手動結案)──▶ closed
```

| 操作 | 來源狀態 | 結果 |
|------|---------|------|
| 首次 send | draft | → active |
| 再次 send | active | 維持 active |
| close | active | → closed |
| send | closed | ❌ 422 |

**建立 Modal 參數**：
- 名稱、選用範本、目標供應商/群組
- 供應商群組（選填）：系統依群組推導 compliance_domains，在題目清單中 highlight 合規相關題目
- 「僅顯示合規相關題目」toggle：過濾非合規題目

#### 2.3 SAQ 個別問卷狀態

| 狀態 | 意義 |
|------|------|
| `sent` | 已發送，待供應商填寫 |
| `in_progress` | 供應商填寫中 |
| `submitted` | 已繳交，待審核 |
| `under_review` | 審核中 |
| `review_returned` | 退回修改 |
| `completed` | 審核完成（通過） |
| `reviewed` | 複核確認 |

**狀態流轉**：
```
sent → in_progress → submitted → under_review → completed → reviewed
                                              → review_returned → submitted（重送）
sent → submitted（直接送出）
```

#### 2.4 計分引擎

- 計分服務：esgchain-ai（FastAPI）
- 計分維度：E（環境）/ S（社會）/ G（治理），三類加權合計 100%
- 評核等級：對應 SGS 五級（Excellent / Good / Fair / Poor / Unacceptable）
- 計分鍵：透過 `question_tags.scoring_engine_key` 關聯計分邏輯

---

### 模組三：矯正行動計畫（CAP）

**目的**：追蹤供應商問題改善進度，與 SAQ 評分結果和合規文件到期自動連結。

**來源**：
- SAQ 審核退回 → 人工建立
- 合規文件到期/即將到期 → 排程自動建立（source_type = 'compliance_doc'）

**關鍵規則**：
- 逾期自動更新狀態
- 同一供應商同類型問題不重複建立（重複保護）
- 供應商可在 Portal 查閱自身的 CAP 項目

---

### 模組四：採購商產品清單（Buyer Product Registry）

**目的**：採購商建立並管理自身的產品目錄，每個產品含 BOM（物料清單）結構，作為合規評估的起點。

#### 4.1 產品基本資訊

- `name`：產品名稱（必填）
- `product_code`：產品編號（選填，SKU）
- `description`：產品說明
- `applicable_regulations`：適用法規標記陣列（如 ['ESPR', 'CBAM']）

#### 4.2 產品 BOM 結構

三層物料清單：
```
BuyerProduct
└── ProductBomLine（材料 / 半成品 / 包材）
    ├── material_name、hs_code、bom_line_type
    ├── material_group_id（關聯物料群組）
    └── BomLineSupplier（指定哪些供應商供應此 BomLine）
        └── role：primary（主要）/ backup（備選）
```

**合規評估起點**：所有合規計算從 `product_bom_lines` + `bom_line_suppliers` 出發（ProductSupplier 只作 AVL，不參與合規）

#### 4.3 產品供應商關聯（AVL）

- `buyer_product_suppliers`：記錄「認可供應商清單」（Approved Vendor List）
- 純 AVL 語義，不驅動合規評估

#### 4.4 ERP 料號橋接

- `buyer_product_trade_goods.erp_product_code`：用於 ERP Webhook 自動匹配生產批號
- 欄位選填，不影響既有功能

---

### 模組五：貿易商品管理（Trade Goods）

**目的**：管理出口商品的 HS Code、CBAM 適用性判斷，以及與採購品的關聯。

**CBAM 適用判斷**（自動）：
- 鋼鐵（HS 72–73）
- 水泥（HS 2523）
- 鋁（HS 76）
- 化肥（HS 31）
- 電力（HS 2716）
- 氫（HS 2804.10）

**出口商品連結（ExportLink / buyer_product_trade_goods）**：
- 連結採購品（BuyerProduct）與出口貿易商品（TradeGood）
- 欄位：erp_product_code / relation_type / note
- 顯示在「採購連結」Tab

---

### 模組六：商品合規管理

#### 6.1 物料群組管理（MaterialGroup）

| 欄位 | 說明 |
|------|------|
| `name` | 群組名稱 |
| `group_type` | 類型（raw_material / packaging / component） |
| `hs_code_prefixes` | HS Code 前綴（用於自動推薦） |
| `required_doc_types` | 必要文件類型陣列 |
| `is_system` | 系統預載（true）或用戶自建（false） |

**HS Code 自動推薦**：輸入 HS Code（≥ 4 碼）後自動匹配物料群組。

**系統預載群組**（is_system=true）：不可刪除，可編輯 required_doc_types。

#### 6.2 合規矩陣（Compliance Matrix）

**視圖**：MaterialComplianceView 的「矩陣視角」Tab

- 行 = 有 required_doc_types 的 MaterialGroup
- 列 = 5 種文件類型：EUDR_DDS / UFLPA_DECLARATION / CMRT / SDS / CE_DOC
- 格子內容：合規數/總數（百分比）
- 顏色：≥90% 綠 / 50–89% 黃 / <50% 紅 / null 灰（不要求）

**格子下鑽（Drill-Down Drawer）**：
- 寬 360px 右側 Drawer
- 依狀態排序：missing → expired → expiring_soon → valid
- 顯示：供應商名稱、群組、文件狀態、到期日
- 底部「查看 CAP」按鈕

**篩選**：供應商群組單選下拉

#### 6.3 ESPR / DPP 就緒度

**計算指標**：
- 材料完整性 = material 類型 BomLine 中有 material_group_id 者 / 總數
- 供應商合規覆蓋率 = primary 供應商 valid/expiring_soon 文件數 / 所有 required doc 總數
- ESPR 標記 = applicable_regulations 含 'ESPR'

**就緒狀態**：
- `ready`：三項全部滿足（ESPR 已標記 + 材料 100% + 供應商 ≥80%）
- `partial`：部分完成
- `not_started`：BomLine 為空或無 primary 供應商

---

### 模組七：生產批號管理（Production Batch）

**目的**：記錄工廠每批次的生產事實，追蹤原料溯源，作為出口申報的碳足跡依據。

#### 7.1 資料模型

```
ProductionBatch
├── erp_batch_no（UNIQUE，重複推送執行 upsert）
├── supplier_id（工廠）
├── buyer_product_trade_good_id（關聯採購品，null=待匹配）
├── quantity + unit（生產量）
├── production_date
├── lot_pcf（批次碳足跡，kgCO2e/unit）
├── lot_pcf_source（reported / estimated）
└── RawMaterialOrigin（原料溯源）
    ├── material_name
    ├── origin_country
    ├── supplier_name（原料供應商）
    ├── gps_lat / gps_lng（農場/礦場座標）
    └── area_ha（土地面積，EUDR 需要）
```

#### 7.2 資料接入方式

**ERP Webhook**：`POST /api/v1/erp/webhook/production-batches`
- 驗證模式：HMAC-SHA256（預設）或 API Key
- 依 erp_product_code 匹配 ExportLink，依 supplier_code 匹配供應商
- 重複 erp_batch_no 執行 upsert

**CSV 匯入備援**：`POST /api/v1/erp/import/production-batches`
- 必填：erp_batch_no / supplier_code / quantity / unit
- 選填：erp_order_no / erp_product_code / production_date / lot_pcf
- 部分列錯誤不中斷整批，回傳 `{ imported: N, errors: [] }`

---

### 模組八：出口申報管理（Export Shipment）

**目的**：管理每批出口申報（Shipment），追蹤貨物組成、生產批號分配、加權 PCF 計算，以及 EUDR 盡職調查聲明（DDS）生成。

#### 8.1 三層資料結構

```
Shipment（出口申報）
├── shipment_no（唯一，SHIP-YYYYMM-NNN）
├── target_market（目標市場，如 EU / JP / US）
├── export_date
├── eudr_dds_status（not_required / draft / submitted / approved）
└── ShipmentLine（出口商品項目）
    ├── trade_good_id
    ├── quantity + unit
    ├── weighted_pcf（加權平均 PCF）
    └── ShipmentLineBatch（批號分配）
        ├── production_batch_id
        └── allocated_quantity
```

#### 8.2 EUDR DDS 狀態自動判斷

- 建立 Shipment / 新增 ShipmentLine 時，若任一 TradeGood.is_eudr_applicable = true → eudr_dds_status = 'draft'
- 無 EUDR 適用商品 → eudr_dds_status = 'not_required'

#### 8.3 加權 PCF 計算

```
weighted_pcf = Σ(lot_pcf × allocated_qty) / Σ(allocated_qty)
```

- 任一批號 lot_pcf = null → weighted_pcf = null（不估算）
- 批號超額分配：回傳 warnings 但允許寫入

#### 8.4 EUDR DDS 草稿生成

`GET /api/v1/shipments/{id}/dds-draft` 回傳結構化 JSON：
- Shipment 基本資訊
- Commodities 陣列（每個 ShipmentLine）
- 每個 Commodity 含 production_batches
- 每個 Batch 含 raw_material_origins（無溯源標記 origins_missing: true）
- eudr_risk_assessment = "pending"（Phase 3 再接入）

---

### 模組九：標籤庫管理（Question Tag Library）

**目的**：提供三層分類標籤，作為 SAQ 題目分類、計分引擎路由、合規領域識別的系統 Source of Truth。

#### 9.1 三層結構

- **L1 領域**：ISO26000 / ESG / ISO20400 / Geo-Risk / Product-Compliance
- **L2 調查分類**：各 L1 下的核心維度
- **L3 調查主題**：具體衡量變數，含唯一 slug

#### 9.2 Slug 規則

- 格式：`{l1_key}.{l2_key}.{l3_key}`（全小寫 snake_case，以 . 分隔）
- **建立後永久不可修改**（計分引擎強依賴）
- 廢棄時設 `deprecated_at`，建替代 slug

#### 9.3 五大 L1 領域（共 87 個 L3 主題）

| L1 | 主題數 | 定位 |
|----|-------|------|
| ISO26000 | 30 | 社會責任哲學框架（7 大核心主題） |
| ESG | 30 | 供應商自身 ESG 表現 |
| ISO20400 | 9 | 品牌方永續採購管理 |
| Geo-Risk | 6 | 外部地緣/天災風險 |
| Product-Compliance | 12 | EU/US 出口法規合規 |

#### 9.4 E/S/G 分類對應

- **E（環境）**：esg.env.* / esg.sc_env.* / iso26k.env.* / prod_comp.cbam.* / prod_comp.eudr.*
- **S（社會）**：esg.labor.* / esg.ohs.* / esg.comm.* / iso26k.hr.* / iso26k.labor.* / iso26k.consumer.* / iso26k.comm.*
- **G（治理）**：esg.gov.* / iso26k.gov.* / iso26k.fair.* / iso20400.*
- **Product-Compliance**：獨立法規合規門檻（pass/fail，不參與 E/S/G 加權）

---

### 模組十：報告管理（Reports）

**目的**：支援多種 ESG 報告框架的資料彙整與驗證。

**支援框架**：
- CSRD（企業永續報告指令）
- CBAM（碳邊境調整機制）
- CDP（碳揭露項目）
- Custom（自訂報告）

**保證等級**：Limited / Reasonable / None

---

### 模組十一：供應商入口（Portal）

**目的**：供應商（supplier / sup_esg 角色）的獨立操作介面，無主 Sidebar，功能聚焦問卷填寫與矯正行動查閱。

**功能**：
- 查看並填寫 SAQ 問卷
- 上傳合規文件
- 查閱 CAP 矯正行動計畫
- 查看問卷歷史與分數

**路由**：登入後導向 `/supplier/portal`（供應商角色專屬）

---

## 參、系統架構

### 3.1 三層服務架構

```
瀏覽器（Vue 3 SPA）
        ↓
Nginx 反向代理
   ├── /       → esgchain-web（Vue 3 + Vite）
   ├── /api    → esgchain-api（Laravel 12 + MySQL 8.4）
   └── /ai     → esgchain-ai（FastAPI + PostgreSQL + Celery + Redis）
```

| 服務 | 技術棧 | 職責 |
|------|--------|------|
| esgchain-web | Vue 3 + Vite + Pinia + TypeScript | 前端 SPA，含 RBAC 路由守衛 |
| esgchain-api | Laravel 12（PHP 8.5）+ MySQL 8.4 | 業務後端：流程/狀態/CRUD/JWT 發行 |
| esgchain-ai | FastAPI（Python 3.12）+ PostgreSQL + Celery + Redis | 計算引擎：評分/碳足跡/AI 推論 |

### 3.2 認證架構

- 演算法：JWT RS256（非對稱加密）
- Laravel：私鑰發行 Access Token（TTL 3600s）+ Refresh Token（TTL 604800s）
- FastAPI：公鑰驗證（只驗不發）
- Payload：`{ sub, roles, supplierId, exp, iat, jti }`
- Token 換發：前端 axios 攔截器自動處理 401 → 換發

### 3.3 資料庫職責分工

**MySQL（esgchain-api）—業務流程**：
```
OrganizationUnit, User, UserPermission
SupplierGroup, Supplier, SupplierContact, SupplierExternalData
SupplierStatusHistory, SupplierCertification
SAQTemplate, SAQQuestion, SaqProject, SaqRound
SAQ, SAQResponse, SaqReviewHistory
CAP, CAPFinding
TradeGood, BuyerProduct, ProductBomLine, BomLineSupplier
Shipment, ShipmentLine, ShipmentLineBatch
ProductionBatch, RawMaterialOrigin
MaterialGroup, MaterialItem
QuestionTag, QuestionTagAssignment
Report, Notification, AuditLog
DecarbPlan, DecarbMilestone
```

**PostgreSQL（esgchain-ai）—計算引擎**：
```
EmissionFactor, ScoringModel, SasbIndustry, SasbStandard
PCFRecord, RiskAssessment, RiskDimensionScore, RiskFactor
LCCAnalysis, EPDRequest, Scope3Report
```

### 3.4 設計系統（Warm Paper Light）

```css
--bg: #f5f3ee;            /* 主背景 */
--surface: #ffffff;        /* 卡片/元件背景 */
--surface-2: #f0ede6;     /* 次要背景 */
--border: #e2ddd6;         /* 邊框 */
--text-primary: #1c1917;  /* 主要文字 */
--text-secondary: #78716c; /* 次要文字 */
--accent: #1a4d3e;         /* 強調色（深綠） */
--sidebar-bg: #1a1714;     /* 側邊欄背景 */
```

字型：Syne（標題）/ Fira Code（數字/等寬）/ Noto Sans TC（中文內文）

---

## 肆、核心 API 端點摘要

### 供應商管理
```
GET    /api/v1/suppliers                     # 分頁列表（支援多維篩選）
POST   /api/v1/suppliers                     # 建立供應商
GET    /api/v1/suppliers/{id}                # 供應商詳情（含 contacts, statusHistories）
PUT    /api/v1/suppliers/{id}                # 更新供應商
POST   /api/v1/suppliers/{id}/transition     # 狀態流轉
POST   /api/v1/suppliers/{id}/contacts       # 新增聯絡人
PUT    /api/v1/suppliers/{id}/contacts/{cid} # 更新聯絡人
DELETE /api/v1/suppliers/{id}/contacts/{cid} # 刪除聯絡人
GET    /api/v1/suppliers/{id}/compliance-docs # 合規文件列表
GET    /api/v1/suppliers/{id}/risk-summary   # 風險評估摘要（E/S/G/GP）
```

### SAQ 問卷管理
```
GET    /api/v1/saq-projects                   # 問卷專案列表
POST   /api/v1/saq-projects                   # 建立問卷專案
POST   /api/v1/saq-projects/{id}/send         # 發送問卷（draft→active）
POST   /api/v1/saq-projects/{id}/close        # 結案（active→closed）
GET    /api/v1/questionnaires                 # 問卷列表（含 supplier_id 篩選）
POST   /api/v1/questionnaires/{id}/transition # 問卷狀態流轉
```

### 商品合規
```
GET    /api/v1/material-groups                # 物料群組列表
POST   /api/v1/material-groups                # 建立物料群組
GET    /api/v1/compliance/matrix              # 合規矩陣（聚合）
GET    /api/v1/compliance/matrix/drill        # 矩陣格子下鑽詳情
GET    /api/v1/compliance/dpp-readiness       # ESPR/DPP 就緒度列表
GET    /api/v1/compliance/dpp-readiness/{id}  # 單一產品 DPP 明細
```

### 出口申報
```
GET    /api/v1/shipments                      # 出口申報列表
POST   /api/v1/shipments                      # 建立出口申報
GET    /api/v1/shipments/{id}                 # 申報詳情
PATCH  /api/v1/shipments/{id}                 # 更新申報（含 DDS 狀態）
GET    /api/v1/shipments/{id}/dds-draft       # 產出 EUDR DDS 草稿 JSON
```

### 生產批號
```
POST   /api/v1/erp/webhook/production-batches # ERP Webhook 接入
POST   /api/v1/erp/import/production-batches  # CSV 批次匯入
GET    /api/v1/production-batches             # 批號列表（含匹配狀態篩選）
```

### 標籤庫
```
GET    /api/v1/question-tags                  # 標籤列表（支援 l1_domain, l2_pillar 篩選）
POST   /api/v1/question-tags                  # 建立 L3 主題
PUT    /api/v1/question-tags/{id}             # 編輯（slug 不可修改）
PATCH  /api/v1/question-tags/{id}/deprecate   # 停用 L3
PATCH  /api/v1/question-tags/{id}/restore     # 恢復 L3
```

---

## 伍、非功能需求

### 5.1 效能
- 列表頁 API 響應 < 500ms（P95）
- SAQ 計分（非同步）< 30s（Celery 任務）
- PCF 計算（非同步）< 60s

### 5.2 安全
- 所有 API 需攜帶有效 JWT（除 /auth/login）
- RBAC 在 Controller 層強制驗證角色
- 供應商角色（supplier/sup_esg）只能存取自身資料
- ERP Webhook 需驗簽（HMAC-SHA256 或 API Key）

### 5.3 資料完整性
- 所有主鍵使用 UUID（有序 UUID v7）
- 狀態變更必須記錄稽核日誌（AuditLog）
- Slug 建立後永久不可修改（DB UNIQUE 索引保護）
- 軟刪除（SoftDeletes）保留歷史資料

### 5.4 多語系
- 前端 i18n 同時維護 zh-TW / en
- UI 文字使用繁體中文，技術縮寫保留英文
- 標籤庫 label_zh / label_en 雙語維護

### 5.5 可維護性
- Server-side Pagination（所有列表頁）
- 按鈕操作立即 disabled + loading（防重複送出）
- 錯誤統一回傳 { success: false, message: "..." }

---

## 陸、驗收條件（關鍵場景）

### AC-001：供應商狀態流轉稽核
- **Given** 供應商狀態為 active
- **When** admin 執行狀態變更為 suspended，填入原因「重大勞工違規」
- **Then** 系統儲存 SupplierStatusHistory（from=active, to=suspended, reason=..., changed_by=admin_id）
- **And** 供應商詳情頁狀態歷程 Timeline 顯示此筆記錄

### AC-002：SAQ 問卷計分
- **Given** 供應商提交問卷（status=submitted）
- **When** 審核者執行「完成審核」
- **Then** 系統呼叫 esgchain-ai 計分 API（非同步）
- **And** 計分完成後 SAQ.score 寫入（E/S/G 三維分數）
- **And** 對應 SGS 五級評核結果更新

### AC-003：合規文件到期觸發 CAP
- **Given** 供應商 A 的 CMRT 合規文件 expires_at = 今日 + 20 天（expiring_soon）
- **When** 每日排程執行
- **Then** 系統建立 CAP（source_type='compliance_doc', status='open'）
- **And** 同一文件不重複建立 CAP（重複保護）

### AC-004：EUDR DDS 草稿生成
- **Given** Shipment 包含 EUDR 適用商品，各 ShipmentLine 已分配 ProductionBatch
- **When** 使用者呼叫「產出草稿」
- **Then** 回傳結構化 JSON 含 commodities / production_batches / raw_material_origins
- **And** 無 RawMaterialOrigin 的批號標記 origins_missing: true

### AC-005：批號超額分配警告
- **Given** ProductionBatch PRD-001 生產量 = 5000 件，已分配 4000 件
- **When** 新增分配 2000 件
- **Then** 分配記錄建立成功
- **And** API 回傳 warnings: ["批號 PRD-001 累計分配 6,000 件，超過生產量 5,000 件"]

### AC-006：SAQ 問卷關閉後無法再送
- **Given** SaqProject.status = closed
- **When** 任何人嘗試呼叫 send API
- **Then** 回傳 422，訊息「問卷專案已結案，無法再發送」

### AC-007：ERP Webhook 驗簽失敗
- **Given** ERP_AUTH_MODE = hmac
- **When** Webhook 請求帶入無效的 X-ERP-Signature
- **Then** 回傳 401，不建立任何記錄

### AC-008：合規矩陣格子下鑽
- **Given** 合規矩陣顯示「棉花原料 × UFLPA」格子：合規率 40%（紅色）
- **When** 採購商點擊該格子
- **Then** 右側 Drawer 展開，依 missing → expired → expiring_soon → valid 順序列出供應商
- **And** 每列顯示：供應商名稱、供應商群組、文件狀態 badge、到期日
- **And** 底部顯示「查看 CAP」按鈕（連結至 /cap?supplier_id=xxx）

### AC-009：DPP 就緒度計算
- **Given** BuyerProduct「有機棉T恤」：ESPR 已標記，所有 BomLine 有 material_group_id，primary 供應商 valid/expiring_soon 文件覆蓋率 90%
- **When** 計算 DPP 就緒度
- **Then** readiness_status = 'ready'（綠色 badge）

### AC-010：標籤 Slug 不可修改
- **Given** 標籤 slug = 'esg.labor.forced_labor' 已建立
- **When** Admin 嘗試 PUT /question-tags/{id} 帶入新 slug
- **Then** 後端忽略 slug 欄位，只更新 label_zh / label_en / scoring_engine_key
- **And** 資料庫中 slug 維持不變

---

## 柒、附錄

### A. 計分引擎鍵值對照

| scoring_engine_key | 用途 |
|-------------------|------|
| ghg_scoring_v1 | GHG 排放量評分 |
| energy_scoring_v1 | 能源強度評分 |
| decarb_scoring_v1 | 減碳路徑（SBTi）評估 |
| scope3_scoring_v1 | 範疇三排放評分 |
| labor_scoring_v1 | 勞工人權風險評分 |
| risk_scoring_v1 | 綜合永續風險評分 |
| cbam_scoring_v1 | CBAM 內含碳合規評分 |

### B. 文件類型列舉

| 代碼 | 全名 | 對應法規 |
|------|------|---------|
| EUDR_DDS | Due Diligence Statement | EUDR（EU 2023/1115） |
| UFLPA_DECLARATION | Forced Labor Declaration | UFLPA（US） |
| CMRT | Conflict Mineral Reporting Template | SEC Rule 13p-1 / 3TG |
| SDS | Safety Data Sheet | REACH / GHS |
| CE_DOC | CE Conformity Declaration | EU CE Marking |

### C. 測試帳號

| 角色 | Email | 密碼 |
|------|-------|------|
| admin | admin@esgchain.com | demo1234 |
| buyer | buyer@esgchain.com | demo1234 |
| sustain | sustain@esgchain.com | demo1234 |
| analyst | analyst@esgchain.com | demo1234 |
| supplier | supplier1@tpsteel.com.tw | demo1234 |
| sup_esg | esg@vge.vn | demo1234 |
