# 物料群組模組 功能規格書

**版本**：v1.0  
**最後更新**：2026-06-05  
**模組範圍**：MaterialGroup（物料群組）＋ MaterialItem（物料主檔）  
**對應路由**：`/settings/material-groups`、`/settings/material-items`

---

## 1. 模組概覽

物料群組模組是 ESG·Chain 供應鏈合規的基礎主資料層，提供：

- **物料群組（MaterialGroup）**：以 HS Code 前綴與合規文件需求定義材料類別
- **物料主檔（MaterialItem）**：管理料號（SKU）主資料，掛接群組與 HS Code

兩者共同服務下游模組：

| 下游模組 | 依賴關係 |
|---------|---------|
| BOM 管理（ProductBomLine） | BomLine 可連結 material_item_id，取得 HS Code / 群組資訊 |
| 供應商合規文件 | SupplierComplianceDoc.material_group_id 決定所需文件類型 |
| 貿易商品（TradeGood） | TradeGood.material_group_id 判斷 CBAM 適用性 |
| PCF 碳足跡（MaterialItemEmission） | 依 material_item × supplier 紀錄排放量 |

---

## 2. 資料模型

### 2.1 MaterialGroup

| 欄位 | 類型 | 說明 |
|------|------|------|
| `id` | UUID（PK） | HasUuids，不可變 |
| `name` | VARCHAR(100) | 群組名稱，全系統唯一 |
| `group_type` | VARCHAR(50) | 物料大類（預留擴充） |
| `description` | TEXT \| NULL | 說明文字 |
| `hs_code_prefixes` | JSON → string[] | HS Code 前綴列表，供自動推薦比對 |
| `required_doc_types` | JSON → string[] | 此類別必要合規文件類型（見 §4.3） |
| `is_system` | BOOLEAN | true = 系統預載（不可刪除） |

**關聯**：
- `complianceDocs` → SupplierComplianceDoc[]（HasMany）
- `tradeGoods` → TradeGood[]（HasMany）

### 2.2 MaterialItem

| 欄位 | 類型 | 說明 |
|------|------|------|
| `id` | UUID（PK） | HasUuids |
| `item_code` | VARCHAR(100) | 料號代碼，全系統唯一 |
| `name` | VARCHAR(200) | 品名/規格描述 |
| `hs_code` | VARCHAR(20) \| NULL | 完整 HS Code（6-10 碼） |
| `unit` | VARCHAR(20) \| NULL | 計量單位（KG、PCS、M²…） |
| `material_group_id` | UUID FK \| NULL | 所屬物料群組 |
| `description` | TEXT \| NULL | 補充說明 |
| `is_active` | BOOLEAN | 停用時不在 BomLine 下拉顯示 |
| `deleted_at` | TIMESTAMP \| NULL | SoftDeletes |

**關聯**：
- `materialGroup` → MaterialGroup（BelongsTo）
- `bomLines` → ProductBomLine[]（HasMany）
- `emissions` → MaterialItemEmission[]（HasMany）

---

## 3. 功能規格

### 3.1 物料群組管理（MaterialGroupsView）

#### FR-MG-01 群組清單

- 路徑：`GET /api/v1/compliance/material-groups`
- 回傳所有群組，依 `name` 升冪排列（不分頁）
- 欄位：id, name, description, hs_code_prefixes, required_doc_types, is_system
- 前端以表格呈現：群組名稱、說明、HS Code 前綴（chip 列）、合規文件類型（badge 列）、系統標誌、操作按鈕

**驗收條件：**
```
GIVEN 系統已有物料群組記錄
WHEN 管理員進入 /settings/material-groups
THEN 以表格顯示所有群組，is_system=true 者操作欄只顯示「編輯」（無刪除按鈕）
```

#### FR-MG-02 新增物料群組

- 路徑：`POST /api/v1/compliance/material-groups`
- 前端：點「＋ 新增物料群組」開啟 Modal
- 欄位：
  - `name`（必填，唯一）
  - `description`（選填）
  - `hs_code_prefixes`（Enter 新增 chip，點擊移除）
  - `required_doc_types`（checkbox 多選，見 §4.3）
- 建立時自動設定 `is_system = false`

**驗收條件：**
```
GIVEN 管理員填寫名稱「棉紗」、HS 前綴 [5205, 5206]、文件 [UFLPA_DECLARATION]
WHEN 送出
THEN API 回傳 201，清單即時新增該群組；`is_system=false`
```

```
GIVEN 管理員輸入已存在的群組名稱
WHEN 送出
THEN API 回傳 422，提示「名稱已存在」
```

#### FR-MG-03 編輯物料群組

- 路徑：`PUT /api/v1/compliance/material-groups/{id}`
- `is_system=true` 的群組可編輯 name、description、required_doc_types
- hs_code_prefixes 可增減

**驗收條件：**
```
GIVEN 管理員編輯系統群組的 required_doc_types
WHEN 儲存
THEN API 回傳 200，清單即時更新；群組仍為 is_system=true
```

#### FR-MG-04 刪除物料群組

- 路徑：`DELETE /api/v1/compliance/material-groups/{id}`
- `is_system=true`：拒絕（422）
- 有 MaterialItem 或 ProductBomLine 參照：拒絕（422），回傳使用數量
- 無任何參照且 `is_system=false`：允許刪除

**驗收條件：**
```
GIVEN 物料群組有 5 個 MaterialItem 使用中
WHEN 管理員點刪除
THEN API 回傳 422，提示「被 5 個料號使用中，無法刪除」；清單不變
```

```
GIVEN 物料群組無任何參照、is_system=false
WHEN 管理員點刪除並確認
THEN API 回傳 200，清單移除該群組
```

#### FR-MG-05 HS Code 前綴自動推薦

- 觸發：BomLine 或 MaterialItem 輸入 HS Code（≥4 碼）時
- 邏輯：`MaterialGroup::findByHsCode()` — 比對 `hs_code_prefixes` 中各前綴，`str_starts_with` 符合即推薦
- 前端：在物料群組欄位下方顯示推薦 chip，使用者可一鍵套用

**驗收條件：**
```
GIVEN HS Code 5208 對應到「棉紡原料」群組（前綴 [5208, 5209]）
WHEN 使用者在 MaterialItem 輸入 HS Code「52081200」
THEN 物料群組欄位下方出現推薦提示「棉紡原料」
```

---

### 3.2 物料主檔管理（MaterialItemsView）

#### FR-MI-01 料號清單（Server-side Pagination）

- 路徑：`GET /api/v1/compliance/material-items?per_page=20&search=&material_group_ids=&active_only=`
- 預設每頁 20 筆，依 `item_code` 升冪排列
- 篩選：關鍵字搜尋（item_code / name）、物料群組多選、is_active 切換
- 前端：搜尋列 + 群組多選下拉（checkbox）+ 「僅顯示啟用」切換

**驗收條件：**
```
GIVEN 系統有 150 個料號
WHEN 管理員進入 /settings/material-items
THEN 顯示第 1 頁 20 筆，分頁控制列顯示「共 150 筆」
```

```
GIVEN 管理員篩選群組「棉紡原料」並勾選「僅顯示啟用」
WHEN 套用篩選
THEN API 帶 material_group_ids 與 active_only=true 查詢，清單更新
```

#### FR-MI-02 新增料號

- 路徑：`POST /api/v1/compliance/material-items`
- 欄位：
  - `item_code`（必填，全系統唯一）
  - `name`（必填）
  - `hs_code`（選填，6-10 碼，輸入時觸發群組自動推薦）
  - `unit`（選填，如 KG / PCS / M²）
  - `material_group_id`（選填，UUID FK）
  - `description`（選填）

**驗收條件：**
```
GIVEN 管理員填寫料號「RAW-5208-001」、品名「精梳棉紗 20s」、群組「棉紡原料」
WHEN 送出
THEN API 回傳 201，料號代碼與品名出現在清單
```

```
GIVEN 管理員輸入已存在的 item_code
WHEN 送出
THEN API 回傳 422「料號代碼已存在」
```

#### FR-MI-03 編輯料號

- 路徑：`PUT /api/v1/compliance/material-items/{id}`
- 所有欄位（除 id）均可更新
- item_code 唯一性排除自身（same-id exclusion）

#### FR-MI-04 停用料號

- `is_active=false` 時：料號保留，但 BomLine 建立介面的料號下拉不顯示此料號
- 已連結的 BomLine 不受影響（不斷開 FK）

**驗收條件：**
```
GIVEN 料號「RAW-5208-001」已被 3 條 BomLine 引用
WHEN 管理員將其停用
THEN is_active=false，舊有 BomLine 保持連結；新增 BomLine 時下拉不顯示此料號
```

#### FR-MI-05 刪除料號

- 路徑：`DELETE /api/v1/compliance/material-items/{id}`
- 有 ProductBomLine 引用：拒絕（422），提示數量與「可改用停用」選項
- 無引用：SoftDelete（deleted_at 記錄時間戳，資料保留）

**驗收條件：**
```
GIVEN 料號被 2 條 BomLine 使用
WHEN 管理員點刪除
THEN API 回傳 422「被 2 條 BOM 明細使用，無法刪除，可選擇停用」
```

#### FR-MI-06 CSV 批次匯入

- 路徑：`POST /api/v1/compliance/material-items/import`
- 格式（CSV header）：`item_code, name, hs_code, material_group_name, unit`
- 比對邏輯：以 `material_group_name` 文字比對 MaterialGroup.name
- Upsert：item_code 存在 → 更新；不存在 → 新建
- 錯誤處理：找不到 material_group_name → warnings（含行號），不阻斷整批；item_code / name 空白 → 跳過並警告

**驗收條件：**
```
GIVEN CSV 共 50 列，2 列 material_group_name 無法比對，1 列 item_code / name 為空
WHEN 管理員上傳
THEN API 回傳 200，created/updated 計數，warnings 含 3 行警告；其餘 47 筆正常匯入
```

---

## 4. 補充規格

### 4.1 合規文件類型列舉（required_doc_types）

| 值 | 說明 |
|----|------|
| `UFLPA_DECLARATION` | 新疆強迫勞動防制法聲明 |
| `EUDR_DDS` | 歐盟零毀林盡職調查聲明 |
| `CMRT` | 衝突礦物盡職調查報告 |
| `SDS` | 化學品安全資料表 |
| `CE_DOC` | CE 符合性聲明 |
| `ORIGIN_CERT` | 原產地證明 |
| `OTHER` | 其他 |

### 4.2 系統預載物料群組（is_system=true）

系統 Seed 提供以下群組（不可刪除）：

| 群組名稱 | HS 前綴 | 主要合規文件 |
|---------|---------|-------------|
| 鋼鐵及鋼鐵製品 | 72, 73 | CBAM_REPORT, CMRT |
| 鋁及鋁製品 | 76 | CBAM_REPORT, CMRT |
| 水泥及熟料 | 2523, 6810 | CBAM_REPORT |
| 化肥原料 | 31 | CBAM_REPORT |
| 棉紡原料 | 5201, 5202, 5203, 5205, 5206, 5207, 5208, 5209 | UFLPA_DECLARATION |
| 電子零組件 | 8532, 8533, 8541, 8542 | CMRT, REACH_SVHC, ROHS_DOC |
| 化學品 | 28, 29, 38 | SDS |

### 4.3 BomLine 連結料號的有效值規則（Effective Value）

當 `ProductBomLine.material_item_id` 非 null 時：

| BomLine 欄位 | 實際使用值 |
|-------------|-----------|
| `effective_material_name` | MaterialItem.name |
| `effective_hs_code` | MaterialItem.hs_code |
| `effective_material_group` | MaterialItem.materialGroup |

當 `material_item_id` 為 null 時，使用 BomLine 本地欄位（`material_name`、`hs_code`、`material_group_id`）。

### 4.4 API 路由摘要

| 方法 | 路徑 | 說明 |
|------|------|------|
| GET | `/api/v1/compliance/material-groups` | 取得所有物料群組 |
| POST | `/api/v1/compliance/material-groups` | 新增物料群組 |
| PUT | `/api/v1/compliance/material-groups/{id}` | 更新物料群組 |
| DELETE | `/api/v1/compliance/material-groups/{id}` | 刪除物料群組 |
| GET | `/api/v1/compliance/material-items` | 料號清單（分頁+篩選） |
| POST | `/api/v1/compliance/material-items` | 新增料號 |
| PUT | `/api/v1/compliance/material-items/{id}` | 更新料號 |
| DELETE | `/api/v1/compliance/material-items/{id}` | 刪除料號（SoftDelete） |
| POST | `/api/v1/compliance/material-items/import` | CSV 批次匯入料號 |

### 4.5 RBAC 權限

| 角色 | 物料群組 | 物料主檔 |
|------|---------|---------|
| `admin` | CRUD | CRUD + Import |
| `buyer` | Read | Read |
| `sustain` | Read | Read |
| `comply` | Read | CRUD + Import |
| `analyst` | Read | Read |
| `supplier` / `sup_esg` | 無 | 無 |

---

## 5. UI/UX 規格

### 5.1 MaterialGroupsView（`/settings/material-groups`）

```
┌──────────────────────────────────────────────────────────────┐
│ 麵包屑：系統設定 › 物料群組              [＋ 新增物料群組]  │
├──────────────────────────────────────────────────────────────┤
│ 群組名稱   說明   HS Code 前綴   合規文件類型   系統  操作   │
├──────────────────────────────────────────────────────────────┤
│ 棉紡原料   …    [5205][5208]    [UFLPA_DECLARATION]  —  [編輯][刪除] │
│ 鋼鐵製品   …    [72][73]        [CBAM][CMRT]        ★系統 [編輯]  │
└──────────────────────────────────────────────────────────────┘
```

**新增/編輯 Modal**：
- 群組名稱（text input，必填）
- 說明（textarea，選填）
- HS Code 前綴（input + Enter 新增 → chip，點 × 移除）
- 合規文件類型（checkbox grid，§4.1 列舉）

### 5.2 MaterialItemsView（`/settings/material-items`）

```
┌─────────────────────────────────────────────────────────────────┐
│ 麵包屑：系統設定 › 物料主檔   [↑ CSV匯入]  [＋ 新增料號]      │
├──────────────────────────────────────────────────────────────────┤
│ 🔍搜尋  [群組多選▼]  [□ 僅顯示啟用]   共 150 筆               │
├──────────────────────────────────────────────────────────────────┤
│ 料號代碼  品名  HS Code  物料群組   單位  狀態  操作            │
├──────────────────────────────────────────────────────────────────┤
│ RAW-5208  精梳棉紗  5208.1200  棉紡原料  KG   啟用  [編輯][停用][刪] │
└──────────────────────────────────────────────────────────────────┘
```

**HS Code 輸入時群組推薦提示**：
```
HS Code: [52081200    ]
         ↳ 建議群組：棉紡原料  [套用]
```

**CSV 匯入結果 Toast**：
```
匯入完成：新增 47 筆、更新 0 筆
⚠ 3 筆警告（點此查看詳情）
```

---

## 6. 資料完整性保護

| 規則 | 實作層 |
|------|--------|
| MaterialGroup.name 全域唯一 | DB UNIQUE + Laravel unique validation |
| MaterialItem.item_code 全域唯一 | DB UNIQUE + same-id exclusion |
| is_system=true 不可刪除 | Controller 層檢查 |
| 有引用的群組/料號不可刪除 | Controller 層 count 檢查 |
| MaterialItem 刪除使用 SoftDeletes | 保留稽核軌跡 |
| BomLine 連結 material_item_id 為 nullable FK | 允許自由文字 BomLine |

---

*此規格書由程式碼逆向分析 + FRS v2.0 對照產生，2026-06-05*
