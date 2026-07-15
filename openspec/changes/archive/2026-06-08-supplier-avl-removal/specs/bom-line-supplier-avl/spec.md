## REMOVED Requirements

### Requirement: BomLine 支援多供應商（AVL）

**Reason:** 採購商資料 100% 來自 ERP 匯入，ERP 匯入流程完全繞過 AVL 驗證（`source=erp_designated`），AVL 長期為空、從未發揮守門作用。以 `supplier.status=certified` 作為唯一門檻更有業務意義且自動反映 Supplier MDM 審核狀態，無需額外維護清單。

**Migration:** `buyer_product_suppliers` 表將以 drop migration 移除。`bom_line_suppliers` 表與 `BomLineSupplier` model 保留，多供應商（primary/alternate）功能不受影響。供應商指派門檻改由 `bom-line-supplier-management` 規格定義。

### Requirement: AVL 管理移至 BOM Panel 底部

**Reason:** AVL 管理 UI 區塊（buyer_product_suppliers CRUD）隨 `buyer_product_suppliers` 表一併移除。BOM 供應商選取改為開放 Combobox，見 `bom-line-supplier-open-picker` 規格。

**Migration:** `BuyerProductsView.vue` 移除 AVL 管理區塊（「已認可供應商」清單、新增/移除按鈕、AVL 說明文字）。API 端點 `GET/POST/DELETE /api/v1/buyer-products/{id}/suppliers` 一併移除。
