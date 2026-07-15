## MODIFIED Requirements

### Requirement: BomLine 行內供應商管理 UI
每條 BomLine 顯示列 SHALL 提供「供應商 N」badge，點擊展開 sub-row，顯示該 BomLine 的 BomLineSupplier 清單，並支援新增與移除。手動新增供應商時，候選池 SHALL 限定為該產品的 AVL 成員（`product_suppliers`）。

#### Scenario: 展開供應商 sub-row
- **WHEN** 使用者點擊 BomLine 列的「供應商 N」badge
- **THEN** 系統 SHALL 在該列下方插入 sub-row，顯示現有 BomLineSupplier 清單（supplier 名稱、role primary/alternate、來源 badge）

#### Scenario: AVL 有成員時的新增表單
- **WHEN** 產品 AVL 有至少一個成員，且 sub-row 展開
- **THEN** SHALL 顯示新增供應商 inline form，供應商下拉僅列出 AVL 成員（不顯示 MDM 中其他供應商）

#### Scenario: AVL 為空時的新增提示
- **WHEN** 產品 AVL 尚無成員，且 sub-row 展開
- **THEN** SHALL 隱藏新增供應商 form，顯示提示文字「請先在下方新增已認可供應商（AVL）後，再指定 BomLine 供應商」

#### Scenario: 新增 BomLineSupplier（成功）
- **WHEN** 使用者從 AVL 下拉選擇供應商並選擇角色（primary/alternate），點擊確認
- **THEN** 系統 SHALL 呼叫 POST /api/v1/products/{id}/bom-lines/{lineId}/suppliers，成功後重新載入該 BomLine 的供應商清單

#### Scenario: 新增第二個主要供應商
- **WHEN** 使用者嘗試為已有 primary 供應商的 BomLine 再新增 primary
- **THEN** 系統 SHALL 顯示錯誤提示「每條 BomLine 只能有一個主要供應商」，阻止送出

#### Scenario: 移除 BomLineSupplier
- **WHEN** 使用者點擊某供應商記錄的「移除」按鈕
- **THEN** 系統 SHALL 呼叫 DELETE API，成功後從 sub-row 移除該列

### Requirement: BomLineSupplier 後端 AVL 約束
手動新增 BomLineSupplier（`source=manual`）時，系統 SHALL 驗證 `supplier_id` 必須存在於該產品的 `buyer_product_suppliers`（AVL）。ERP 匯入路徑（`source=erp_designated`）不受此約束。

#### Scenario: 手動新增 AVL 成員供應商
- **WHEN** POST /api/v1/products/{buyerProduct}/bom-lines/{bomLine}/suppliers，supplier_id 存在於該 buyerProduct 的 buyer_product_suppliers
- **THEN** 系統 SHALL 建立 BomLineSupplier，回傳 201

#### Scenario: 手動新增非 AVL 供應商
- **WHEN** POST /api/v1/products/{buyerProduct}/bom-lines/{bomLine}/suppliers，supplier_id 不在該 buyerProduct 的 AVL 中
- **THEN** 系統 SHALL 回傳 422，訊息「此供應商不在產品已認可供應商清單（AVL）中，請先將其加入 AVL」

#### Scenario: ERP 匯入供應商跳過 AVL 驗證
- **WHEN** BomLineImportService 建立 BomLineSupplier（source=erp_designated）
- **THEN** 系統 SHALL 不驗證 AVL，直接建立記錄
