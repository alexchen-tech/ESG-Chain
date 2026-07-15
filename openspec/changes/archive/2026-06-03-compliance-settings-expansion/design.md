## Context

物料合規模組（Phase 1）目前的資料層已建立：MaterialGroup、BuyerProduct、ProductBomLine、BomLineSupplier 均有後端 Model 和 API，但缺少以下設定管理能力：

1. MaterialGroup 只能透過 seeder 建立，無前端 CRUD
2. SupplierGroup 沒有 required_doc_types 欄位，廠商資格合規軌道未建立
3. 料號（MaterialItem）概念不存在，BomLine 的 material_name 是自由文字，無法跨產品追蹤同一物料
4. 目標市場（MarketDefinition）不存在，BuyerProduct.applicable_regulations 只能從 BomLine 推算，無法主動宣告銷售市場

設定頁目前有 5 個 Tab（組織架構、供應商分組、SASB 產業分類、計分模型 ↗、標籤庫 ↗）。料號主檔規模可能達數千筆，適合獨立子頁模式（同計分模型、標籤庫）。

## Goals / Non-Goals

**Goals:**
- SupplierGroup 加 `required_doc_types`，UI 提供多選文件類型
- MaterialGroup 設定頁 Tab，支援 CRUD（is_system=true 不可刪除）
- MaterialItem 料號主檔獨立子頁（`/settings/material-items`），CRUD + CSV 匯入
- MarketDefinition 設定頁 Tab，自訂市場代碼，與 ERP 解耦
- BomLine 新增 nullable `material_item_id` FK，有料號時以料號資料為 effective 值

**Non-Goals:**
- ComplianceFramework 及市場→框架激活邏輯（Phase 2）
- BuyerProduct 的 target_markets 欄位（Phase 2）
- ProductSku（型號）和 ProductionBatch（批號）（Phase 3）
- 合規缺口計算（Phase 2）

## Decisions

### D1：料號主檔用獨立子頁，不在 Tab 內

**選擇**：`/settings/material-items`（MaterialItemsView.vue），設定頁以 link tab 呈現（同計分模型、標籤庫）。

**理由**：料號可能達數千筆，需完整的分頁、搜尋、CSV 匯入功能，在 Tab 的 v-show 區塊內會過重。獨立路由也讓直連分享更自然。

---

### D2：BomLine 的 material_item_id 為 nullable，雙軌並存

**選擇**：`product_bom_lines.material_item_id` 為 nullable FK，現有資料不遷移。

**解析優先序（effective 值）**：
```
有 material_item_id → 用 MaterialItem 的 name / hs_code / material_group
無 material_item_id → 用 BomLine 本地的 material_name / hs_code / material_group_id
```

**理由**：現有 BomLine 不需要遷移，不同成熟度的使用者可自行決定是否建立料號主檔。料號一旦連結，MaterialGroup 的維護集中在料號層，不需逐 BomLine 重複設定。

---

### D3：MarketDefinition 純 CRUD，不含 framework 激活邏輯

**選擇**：`market_definitions` 表只存 `code`、`label`、`description`，本次不存 `framework_ids`。

**理由**：Phase 2 才設計 ComplianceFramework 和激活邏輯。現在只需讓管理員建立市場代碼，BuyerProduct.target_markets 欄位在 Phase 2 加入。避免設計過早鎖定框架關聯結構。

---

### D4：MaterialGroup destroy 需檢查使用中的料號和 BomLine

**選擇**：`is_system=true` 直接拒絕刪除；`is_system=false` 需檢查是否有 MaterialItem 或 ProductBomLine 正在參照，有則返回 422。

**理由**：MaterialGroup 是合規驅動鍵，誤刪會導致現有 BomLine 的法規欄位消失，需保護。

---

### D5：CSV 匯入格式與 ERP 解耦

**選擇**：CSV 欄位用 `material_group_name`（文字名稱）對應，不用 `material_group_id`。

**理由**：ERP 匯出的料號表不會包含系統 UUID。用名稱對應讓匯入不依賴任何 ERP 系統的 ID 結構。匯入時模糊比對 MaterialGroup.name，找不到時回傳 warning，不阻擋整批匯入。

---

### D6：SupplierGroup required_doc_types 值域

**選擇**：廠商資格文件類型值域與 MaterialGroup.required_doc_types **不共用**，另建一組枚舉：`SMETA_AUDIT`、`ISO_9001`、`FACTORY_AUDIT`、`HIGG_FEM`、`OEKO_TEX`、`BSCI`、`ZDHC_MRSL`。

**理由**：MaterialGroup 的 doc_types 是物料/出貨文件（UFLPA、SDS 等），SupplierGroup 的是廠商長效資格文件，兩個軌道性質不同，混用會讓 UI 和計算邏輯混淆。

## Risks / Trade-offs

**[BomLine 雙軌並存導致 UI 顯示邏輯複雜]**
→ 在 BomLine API response 加入 `effective_material_name`、`effective_material_group` 計算欄位，前端統一使用 effective 值，不在前端做 if/else 判斷。

**[料號主檔與現有 BomLine 的資料不一致]**
→ Phase 1 不強制 BomLine 連結料號，使用者自行決定。BomLine 清單顯示「已連結料號 / 自由文字」狀態標示，讓使用者知道哪些尚未對應。

**[MarketDefinition 過早建立，Phase 2 可能需要 schema 調整]**
→ 本次只存 code/label/description，欄位設計保持最小化。Phase 2 加欄位（framework_ids JSON）比改欄位安全。

**[MaterialGroup 刪除保護可能讓使用者困惑]**
→ 刪除失敗時回傳清楚訊息，說明哪些料號或 BomLine 正在使用，並提供「查看使用清單」的 UI 動作。

## Migration Plan

1. 執行新 migrations（不破壞現有資料）：
   - `alter_supplier_groups_add_required_doc_types`
   - `create_material_items_table`
   - `alter_product_bom_lines_add_material_item_id`
   - `create_market_definitions_table`
2. 執行 seeder：`MaterialGroupSeeder`（補齊前端 CRUD 所需的 group_type 欄位已存在）、`MarketDefinitionSeeder`（預載 US_MARKET、EU_MARKET、JP_MARKET 等常用市場）
3. 前端部署：設定頁新 Tab 和子頁對現有功能無影響，可直接上線

**Rollback**：migrations 均有 down()，可 `migrate:rollback` 回退；前端 Tab 為新增，不影響現有頁面。

## Open Questions

- MaterialItem 的 `item_code` 是否需要支援多個 ERP 系統的對應碼（erp_vendor_codes 的概念）？目前設計為單一 unique code，如有多 ERP 需求，Phase 2 再擴充 `erp_item_codes` JSON 欄位。
- MarketDefinition 是否需要支援「繼承」（例如 EU_MARKET 繼承 GLOBAL 的框架要求）？Phase 2 設計框架激活邏輯時再決定。
