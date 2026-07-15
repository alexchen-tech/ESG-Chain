## ADDED Requirements

### Requirement: 採購商產品清單 CRUD
系統 SHALL 允許採購商（admin/buyer/sustain/comply）管理自身的產品清單，每筆產品記錄包含名稱、產品編號（選填）、說明，以及關聯的供應商與物料群組。

#### Scenario: 建立產品
- **WHEN** 採購商送出包含 name 的建立請求（product_code、description 為選填）
- **THEN** 系統 SHALL 建立產品記錄並回傳 201，applicable_regulations 初始為空陣列

#### Scenario: 供應商角色無法存取產品清單
- **WHEN** supplier/sup_esg 角色呼叫產品清單 API
- **THEN** 系統 SHALL 回傳 403

#### Scenario: 刪除產品
- **WHEN** admin 刪除一筆產品
- **THEN** 系統 SHALL 軟刪除該產品及其所有 buyer_product_suppliers 關聯記錄

#### Scenario: 展開 BOM 明細 Panel
- **WHEN** 採購商點擊產品卡片上的「BOM 明細」標籤
- **THEN** 系統 SHALL 展開 BOM panel 並載入該產品的 ProductBomLines，Panel header 顯示筆數

### Requirement: 產品關聯供應商（純 AVL，不承擔合規語義）
系統 SHALL 允許採購商將產品與一個或多個供應商關聯，記錄「此產品的採購商認可哪些供應商」（AVL）。`buyer_product_suppliers` 表 SHALL 移除 `material_group_id` 和 `material_group_source` 欄位。ProductSupplier 不再參與合規計算，合規評估從 `product_bom_lines` + `bom_line_suppliers` 出發。

#### Scenario: 新增產品供應商關聯
- **WHEN** 採購商送出 { supplier_id } 至產品關聯 API
- **THEN** 系統 SHALL 建立 buyer_product_suppliers 記錄，回傳 201

#### Scenario: ProductSupplier 清單不影響合規評估
- **WHEN** 合規引擎計算產品合規狀態
- **THEN** 計算過程不查詢 `buyer_product_suppliers` 表；所有合規評估從 `product_bom_lines` + `bom_line_suppliers` 出發

#### Scenario: 供應商在 ProductSupplier 中但不在任何 BomLine
- **WHEN** 供應商 A 列在某產品的 ProductSupplier 清單，但未出現在該產品任何 BomLine 的 `bom_line_suppliers` 中
- **THEN** 供應商 A 不產生任何合規評估結果（僅作為認可供應商存在，不觸發合規義務）

#### Scenario: 移除產品供應商關聯
- **WHEN** 採購商刪除特定 buyer_product_suppliers 記錄
- **THEN** 系統 SHALL 刪除該關聯，不影響供應商主檔與合規文件

## REMOVED Requirements

### Requirement: ProductSupplier.material_group_id 驅動合規

**Reason**: 物料群組語義應由 BomLine 承載，ProductSupplier 對同一供應商的物料群組標記在跨產品使用時語義模糊（同一染整廠為不同產品標記不同物料群組）。

**Migration**: DROP COLUMN `material_group_id` 和 `material_group_source` from `buyer_product_suppliers`。現有資料棄置（不遷移，因合規計算邏輯已切換至 BomLine 路徑）。

### Requirement: CSV 批量匯入產品清單
系統 SHALL 支援採購商上傳 CSV 檔案批量建立產品與關聯。

#### Scenario: CSV 匯入成功
- **WHEN** 採購商上傳格式正確的 CSV（欄位：name, product_code, description, supplier_tax_id_or_name, material_group_name）
- **THEN** 系統 SHALL 建立產品記錄及對應關聯，回傳匯入結果摘要（created_count, skipped_count, warnings[]）

#### Scenario: CSV 中供應商名稱無法比對
- **WHEN** CSV 某行的 supplier_tax_id_or_name 在系統中找不到對應供應商
- **THEN** 系統 SHALL 跳過該行的供應商關聯，繼續處理其他行，並在 warnings[] 列出無法比對的行號與原因

#### Scenario: CSV 格式錯誤
- **WHEN** 上傳的 CSV 缺少必要欄位 name
- **THEN** 系統 SHALL 回傳 422，不執行任何匯入

### Requirement: ExportLink ERP 料號橋接欄位

**What**: `buyer_product_trade_goods` 新增 `erp_product_code`（nullable string, max 100）欄位，供 Phase 2 ERP Webhook 匹配用。

**Behavior**:

- 欄位選填，不填不影響既有功能
- API `store()` 接受 `erp_product_code` 參數（nullable string）
- API `index()` 回傳值包含 `erp_product_code`
- 前端 Modal 提供「ERP 料號（選填）」輸入欄

#### Scenario: 建立含 ERP 料號的出口連結

- **WHEN** 使用者在「新增出口商品連結」Modal 填入 ERP 料號後送出
- **THEN** `buyer_product_trade_goods.erp_product_code` 儲存該值

#### Scenario: 建立不含 ERP 料號的出口連結

- **WHEN** 使用者不填 ERP 料號直接送出
- **THEN** `erp_product_code` 為 null，連結正常建立，行為與修改前完全相同

#### Scenario: 列表顯示 ERP 料號

- **WHEN** 出口連結的 `erp_product_code` 不為 null
- **THEN** 在連結列表該筆顯示 ERP 料號小字（灰色），有值才顯示，無值不佔空間
## MODIFIED Requirements

### Requirement: 產品法規欄位結構
BuyerProduct SHALL 維護兩個獨立的法規欄位：`inferred_regulations`（系統自動推算，JSON array）與 `applicable_regulations`（人工聲明，JSON array）。前端顯示時 SHALL 合併兩者為 union，並視覺區分來源（推算 vs 人工）。`applicable_regulations` 主要用於 ESPR 及系統無法自動推算的邊緣案例。

#### Scenario: 顯示合規標籤
- **WHEN** 前端渲染產品的法規標籤
- **THEN** 推算來源標籤顯示「系統」標記，人工聲明標籤顯示「手動」標記，兩者均顯示於同一標籤列

#### Scenario: 人工編輯 applicable_regulations
- **WHEN** 使用者在產品 edit modal 勾選/取消 ESPR 等法規
- **THEN** `applicable_regulations` 更新，`inferred_regulations` 不受影響

#### Scenario: 重算後人工聲明保留
- **WHEN** 系統執行 `syncProductInferredRegulations()` 更新 `inferred_regulations`
- **THEN** `applicable_regulations` 欄位內容不變
