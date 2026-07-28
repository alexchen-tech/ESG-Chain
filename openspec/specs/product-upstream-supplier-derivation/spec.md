### Requirement: 從 BOM 衍生產品上游供應商

系統 SHALL 提供 `ProductUpstreamResolver` 服務，從產品的 BOM 明細（`product_bom_lines`）與其物料的核可供應商清單（`material_item_suppliers`）衍生：(1) 必備文件類型集合、(2) 去重後的供應商 ID 清單、(3) 供應商摘要（含物料群組、製程廠區），取代直接讀取 `TradeGoodSupplier`。BOM 行已透過「套用物料核可清單」寫入 `bom_line_suppliers` 者優先採用該關聯；尚未套用者，退回讀取該 BOM 行物料的 `material_item_suppliers` 核可清單。

#### Scenario: 衍生必備文件類型集合

- **WHEN** 呼叫 `materialGroupDocTypes($product)`，產品的 BOM 明細分屬多個要求不同文件類型的物料群組
- **THEN** 回傳所有物料群組 `required_doc_types` 的聯集，去重

#### Scenario: 衍生供應商 ID 清單（BOM 行已套用核可清單）

- **WHEN** 呼叫 `supplierIds($product)`，產品的 BOM 明細已有 `bom_line_suppliers` 記錄
- **THEN** 回傳這些記錄的 `supplier_id`，去重

#### Scenario: 衍生供應商 ID 清單（BOM 行尚未套用核可清單）

- **WHEN** 呼叫 `supplierIds($product)`，某筆 BOM 行尚無 `bom_line_suppliers` 記錄，但其物料在 `material_item_suppliers` 有核可供應商
- **THEN** 回傳該物料核可清單中的供應商 ID

#### Scenario: 供應商摘要含製程廠區

- **WHEN** 呼叫 `supplierSummaries($product)`，某供應商在 `material_item_suppliers` 有指定 `supplier_facility_id`
- **THEN** 回傳結果包含該供應商的製程廠區名稱與類型

#### Scenario: 無任何 BOM 供應商資料

- **WHEN** 產品的 BOM 明細與其物料皆無任何供應商登記
- **THEN** 三個方法皆回傳空結果（空陣列/空集合），不拋出例外
