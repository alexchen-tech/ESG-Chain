## MODIFIED Requirements

### Requirement: BomLine 支援多供應商（AVL）
系統 SHALL 在每條 BomLine 上允許指定一個主要供應商（primary）與多個替代供應商（alternate），儲存於 `bom_line_suppliers` 表。每個 BomLineSupplier 記錄包含：`bom_line_id`、`supplier_id`、`role`（primary/alternate）、`source`（erp_designated/manual）、`sort_order`。BomLineSupplier 的新增與移除 SHALL 透過 `BomLineSupplierController` 的 REST 端點執行，並可在 BuyerProductsView 的 BOM 表格 sub-row 中直接操作。

#### Scenario: 主要供應商存在
- **WHEN** 一條 BomLine 建立並指定主要供應商
- **THEN** `bom_line_suppliers` 中存在一筆 `role=primary` 記錄，關聯至正確的 `supplier_id`

#### Scenario: 新增替代供應商
- **WHEN** 使用者為某 BomLine 新增替代供應商
- **THEN** `bom_line_suppliers` 新增一筆 `role=alternate` 記錄，且原主要供應商記錄不受影響

#### Scenario: 每條 BomLine 最多一個主要供應商
- **WHEN** 同一 BomLine 嘗試新增第二個 `role=primary` 供應商
- **THEN** 系統 SHALL 回傳錯誤，提示每條 BomLine 只能有一個主要供應商

### Requirement: BomLineSupplier 必須通過 MDM 驗證
所有 BomLineSupplier 中的 `supplier_id` 必須對應 `suppliers` 表中已存在的記錄（Supplier MDM）。

#### Scenario: 使用已存在供應商
- **WHEN** BomLineSupplier 指向一個 MDM 中已存在的供應商
- **THEN** 建立成功

#### Scenario: 使用不存在供應商
- **WHEN** BomLineSupplier 指向一個 MDM 中不存在的 `supplier_id`
- **THEN** 系統 SHALL 回傳 422 錯誤，訊息說明該供應商代碼不存在

### Requirement: BomLineSupplier 來源標記
每筆 BomLineSupplier 記錄 SHALL 標記 `source`：`erp_designated`（由 ERP BOM 匯入指定）或 `manual`（人工於 ESG-Chain 介面新增）。

#### Scenario: ERP 匯入的供應商
- **WHEN** BOM 從 ERP 匯入並帶有指定供應商
- **THEN** 對應 BomLineSupplier 的 `source` 為 `erp_designated`

#### Scenario: 手動新增的替代供應商
- **WHEN** 使用者在 UI 手動為 BomLine 新增替代供應商
- **THEN** 對應 BomLineSupplier 的 `source` 為 `manual`

### Requirement: AVL 管理移至 BOM Panel 底部
產品層級的已認可供應商清單（AVL）管理介面 SHALL 位於 BOM Panel 底部，以分隔線與 BOM 表格區隔，標題為「已認可供應商（AVL）」，並顯示說明文字「AVL 廠商需在 BOM 明細中指定為供應商，才會納入合規計算」。

#### Scenario: AVL 區塊可見
- **WHEN** BOM Panel 展開
- **THEN** 使用者 SHALL 在 BOM 表格下方看到 AVL 供應商清單，可新增或移除

#### Scenario: AVL 供應商說明
- **WHEN** 使用者查看 AVL 清單
- **THEN** SHALL 顯示說明文字，引導使用者理解 AVL 與合規計算的關係
