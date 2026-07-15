## Context

系統目前以 Supplier MDM 管理上游，下游客戶僅有 Shipment.target_market（VARCHAR(10) 地區代碼）。TradeGood.product_code 存放客戶外部料號，但無客戶主鍵綁定。出口商品、生產批號、出貨單的下游客戶識別鏈斷裂。

架構三服務：Laravel/MySQL（業務流程）、FastAPI/PostgreSQL（計算）、Vue 3（前端）。Customer MDM 屬業務流程，全部在 Laravel 層。

## Goals / Non-Goals

**Goals:**
- Customer 主檔 CRUD（含 customer_contacts）
- EORI Number 格式與條件驗證（EU 成員國才必填）
- TradeGood.customer_id 綁定，UNIQUE(customer_id, product_code)
- Shipment.customer_id、customer_po_no；target_market 自動帶入
- 前端 CustomerMdmView 管理頁 + TradeGood/Shipment 表單整合

**Non-Goals:**
- Customer Portal（客戶不需要登入系統）
- Customer 的 ESG 評核（非本次範圍）
- 既有 TradeGood 資料自動補填 customer_id（手動補填）

## Decisions

### 1. Customer 主檔欄位
```
customers
  id             UUID PK
  code           VARCHAR(50) UNIQUE   內部客戶代碼
  name           VARCHAR(255)
  country_code   CHAR(2)              ISO 3166，CBAM 判斷依據
  eori_number    VARCHAR(20) NULL     EU 進口商識別碼
  vat_number     VARCHAR(50) NULL
  customer_type  ENUM: brand/retailer/distributor/agent/oem
  address        TEXT NULL
  website        VARCHAR(255) NULL
  notes          TEXT NULL
  status         ENUM: active/inactive  DEFAULT active
  created_by     UUID NULL FK users
  timestamps + softDeletes

customer_contacts
  id             UUID PK
  customer_id    UUID FK (cascadeOnDelete)
  name           VARCHAR(100)
  email          VARCHAR(150) UNIQUE
  phone          VARCHAR(50) NULL
  title          VARCHAR(100) NULL
  is_primary     BOOLEAN DEFAULT false
  timestamps
```

### 2. TradeGood 修改
- 加 `customer_id UUID NULL FK customers`
- 加 UNIQUE(customer_id, product_code)（MySQL NULL 不衝突，允許多筆 NULL）
- customer_id nullable：既有資料不中斷，漸進補填

### 3. Shipment 修改
- 加 `customer_id UUID NULL FK customers`
- 加 `customer_po_no VARCHAR(100) NULL`
- `target_market` 保留（region 快篩用），ShipmentService.create() 自動從 customer.country_code 帶入

### 4. EORI 驗證邏輯
EU_COUNTRIES = ['AT','BE','BG','CY','CZ','DE','DK','EE','ES','FI','FR','GR','HR','HU','IE','IT','LT','LU','LV','MT','NL','PL','PT','RO','SE','SI','SK']
- required_if: country_code in EU_COUNTRIES
- regex: /^[A-Z]{2}[A-Z0-9]{1,15}$/
- 前兩碼必須等於 country_code

### 5. customer_type = agent 警告
- Shipment 建立時，若 Customer.customer_type = 'agent'，API response 加 warning：「代理商可能非實際 CBAM 進口商，請確認」

### 6. target_market 自動帶入規則
```
DE / FR / IT / ... (EU 成員國) → "EU"
US / CA                        → "US" / "NA"
CN / JP / KR / TW              → "APAC"
其他                           → country_code 直接帶入
```
ShipmentService 內建 helper，不放 Controller。

## Risks / Trade-offs

- **既有 Shipment 資料**：customer_id 為 NULL，UI 需容許不選客戶（nullable FK）
- **UNIQUE(customer_id, product_code)**：若 customer_id NULL 的既有資料有重複 product_code，不受影響；補填 customer_id 後若有衝突需人工處理
- **agent 類型的 CBAM 責任**：代理商不是進口商，CBAM 申報義務方需由使用者自行確認，系統只做警告不阻擋
