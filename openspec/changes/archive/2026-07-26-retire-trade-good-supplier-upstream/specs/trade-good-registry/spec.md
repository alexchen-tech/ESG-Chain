## MODIFIED Requirements

### Requirement: 上游供應商 BOM 關聯管理

系統 SHALL 從產品的 BOM 明細（`product_bom_lines`）與物料核可供應商清單（`material_item_suppliers`）自動衍生上游供應商清單，不再要求使用者為每個產品手動登記 `trade_good_suppliers` 記錄。BOM 明細已套用物料核可清單（`bom_line_suppliers`）者優先採用，尚未套用者退回讀取該 BOM 行物料的核可清單。

#### Scenario: 產品的 BOM 明細已套用核可供應商

- **WHEN** 查詢某產品的上游供應商清單，其 BOM 明細已透過「套用物料核可清單」寫入 `bom_line_suppliers`
- **THEN** 系統回傳去重後的供應商清單，每筆含供應商名稱、來源物料群組、製程廠區（來自 `material_item_suppliers.supplier_facility_id`）

#### Scenario: 同一供應商供應多筆 BOM 行的不同物料

- **WHEN** 同一供應商在 BOM 明細中對應多筆不同物料群組
- **THEN** 系統回傳去重後的單一供應商項目，物料群組欄位彙總顯示其實際供應的所有物料群組

#### Scenario: 產品尚無任何 BOM 供應商設定

- **WHEN** 產品的 BOM 明細皆未套用核可供應商清單，對應物料也尚無核可供應商
- **THEN** 系統回傳空的上游供應商清單，視為「尚未設定」而非錯誤

### Requirement: 上游供應商合規展開面板

系統 SHALL 在前端「BOM 明細」分頁提供唯讀的上游供應商彙總區塊，列出 BOM 衍生的所有上游供應商及其每份合規文件的狀態；不再提供手動新增/移除供應商的操作介面。

#### Scenario: BOM 明細分頁顯示上游供應商彙總

- **WHEN** 使用者檢視某產品的「BOM 明細」分頁
- **THEN** 頁面下方顯示 BOM 衍生的上游供應商彙總表，每筆顯示供應商名稱、物料群組、製程廠區、各 required doc_type 的文件狀態與到期日

#### Scenario: 上游供應商分頁不再提供手動編輯入口

- **WHEN** 使用者檢視產品詳情頁
- **THEN** 不再顯示獨立的「上游供應商」分頁與新增/移除供應商表單

## REMOVED Requirements

### Requirement: EUDR 暴露判定（trade_good_suppliers 版本）

**Reason**：EUDR 適用性判定已改為讀取 BOM 明細（非 `trade_good_suppliers`），與本次「上游供應商 BOM 關聯管理」改為 BOM 衍生的方向一致，避免規格文件與既有程式碼行為不一致。
**Migration**：EUDR 暴露判定邏輯詳見 `product-regulation-inference` 能力規格（若尚未涵蓋此判定細節，於該規格後續補充）。
