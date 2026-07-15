## Context

ESG·Chain 的物料合規模組目前使用**雙路徑計算**：

```
ProductSupplier（產品-供應商清單）
    └─ material_group_id → applicable_regulations → 查文件
BomLine（BOM 明細）
    └─ designated_supplier_id + material_group_id → applicable_regulations → 查文件（優先）
```

此架構源自初期設計，當時假設 ProductSupplier 是合規評估的主要入口，BomLine 是可選的細化路徑。但實際業務流程是：

1. 中心廠（品牌採購商）在 PLM 系統設計 BOM 表
2. BOM 匯入 ESG-Chain，每條 BomLine 攜帶：物料名稱、HS Code、物料群組、指定供應商
3. 合規管理依 BomLine 的物料群組判斷所需文件

ProductSupplier 在此流程中沒有獨立的合規語義；它的存在是「哪些供應商被認可供應此產品」，而非「此供應商提供什麼物料」。

**已確認的缺口（來自實際資料驗證）：**
- BomLine 指定的供應商若不在 ProductSupplier 清單，合規計算完全看不到它（TEX-007 / Prym Germany 案例）
- 同一供應商在 ProductSupplier 和 BomLine 中的 material_group 不一致時，BomLine 路徑覆蓋 ProductSupplier 路徑，舊需求靜默消失
- 成衣廠（GMN-001）被標記為「棉紡原料供應商」（TEX-001）和「合纖供應商」（TEX-002），物料群組對服務型供應商毫無意義

## Goals / Non-Goals

**Goals:**
- BomLine 成為合規計算的**唯一**事實來源
- 每條 BomLine 支援多供應商（主要 + 替代），全部納入合規評估
- 以 `bom_line_type` 明確區分原物料行（material）與服務行（service）
- MaterialGroup 擴充服務類型，使染整廠、成衣廠的合規需求有正確的語義標籤
- ProductSupplier 退化為純 AVL（已認可供應商清單），不再承擔合規計算角色
- 全面消除靜默合規缺口

**Non-Goals:**
- 不修改 SAQ 問卷、CAP、報告等模組
- 不改變前端 BOM 匯入 UI 流程（僅更新顯示欄位）
- 不處理 Scope 3 碳足跡計算（另一個 Change）
- 不引入外部 PLM 系統整合（假設手動或 ERP CSV 匯入）

## Decisions

### D1：新增 `bom_line_suppliers` 表，移除 `designated_supplier_id`

**決定**：用獨立關聯表取代 BomLine 上的單一 FK。

```sql
bom_line_suppliers (
  id           UUID PK,
  bom_line_id  UUID FK → product_bom_lines,
  supplier_id  UUID FK → suppliers,
  role         ENUM('primary', 'alternate'),
  source       ENUM('erp_designated', 'manual'),
  sort_order   TINYINT DEFAULT 0,
  created_at, updated_at
)
```

**為什麼不保留 designated_supplier_id + 另加 alternates 表？**
將主要供應商和替代供應商統一在同一張表，合規計算邏輯只需 `WHERE bom_line_id = ?` 一次查詢，不需要 UNION。角色差異透過 `role` 欄位區分，UI 也可依 `sort_order` 排序顯示。

---

### D2：`bom_line_type` 預設為 `material`，服務行需手動標記

**決定**：現有所有 BomLine 遷移時預設設為 `material`，服務行（成衣縫製、染整加工）透過 Seeder 或 UI 標記為 `service`。

**為什麼不從 MaterialGroup 推斷？**
MaterialGroup 在遷移完成前就需要被分類，chicken-and-egg 問題。顯式欄位讓業務邏輯更透明，UI 也可讓使用者直接選擇。

---

### D3：MaterialGroup 新增 `group_type` 欄位（`material` | `service`）

**決定**：在 `material_groups` 表新增 `group_type` ENUM，現有群組標為 `material`，新增服務類群組標為 `service`。

新增服務類群組：
| group_type | name | applicable_regulations |
|---|---|---|
| service | 成衣縫製服務 | UFLPA_DECLARATION（勞工追溯） |
| service | 染整加工服務 | SDS（製程化學品） |
| service | 木製包材服務 | EUDR_DDS |

**為什麼合規需求不根據 bom_line_type 而是仍用 MaterialGroup？**
MaterialGroup 已有 `applicable_regulations` 機制，服務型 MaterialGroup 可重用同一套文件評估邏輯，無需在合規引擎中新增分支。

---

### D4：合規計算改為迭代 BomLine

現行邏輯（偽碼）：
```
foreach ProductSupplier as ps:
    reqs = ps.materialGroup.applicable_regulations
    docs = supplier.complianceDocs.where(type IN reqs)
    evaluate(docs)
```

新邏輯：
```
foreach BomLine as line:
    reqs = line.materialGroup.applicable_regulations
    foreach BomLineSupplier as bls (where bom_line_id = line.id):
        docs = bls.supplier.complianceDocs.where(type IN reqs)
        evaluate(docs, context: { line, supplier: bls.supplier, role: bls.role })
```

**結果聚合**：每個 (BomLine, Supplier) 組合產生一組合規狀態，前端可按 BomLine 展開或按 Supplier 聚合。

---

### D5：`syncApplicableRegulations` 從 BomLine 驅動

現行：`SupplierService::syncApplicableRegulations()` 從 `supplier.productSuppliers[].materialGroup` 聚合。

新行為：從 `BomLineSupplier` JOIN `BomLine.materialGroup` 聚合，對每個供應商取其所有 BomLine 的 `applicable_regulations` UNION，寫入 `suppliers.applicable_regulations`。

---

### D6：ProductSupplier 保留，但移除 `material_group_id`

**決定**：`buyer_product_suppliers` 表保留，移除 `material_group_id` 和 `material_group_source` 欄位，退化為純 AVL。

**理由**：ProductSupplier 仍有業務用途——「這個產品的採購商認可哪些供應商」是供應商管理的基礎資料，不應刪除。只是它不再參與合規計算。

---

### D7：ERP 驗證策略

BomLine 匯入時，`designated_supplier_id`（或新的 BomLineSupplier）必須對應 Supplier MDM 中已存在的供應商。驗證在 Service 層而非 DB constraint，以提供友善的錯誤訊息（「供應商代碼 XXX 不存在」）。

## Risks / Trade-offs

**[風險] 現有 ProductSupplier 合規資料遺失**
遷移後，ProductSupplier.material_group_id 的資料被棄置。若有供應商**只存在於 ProductSupplier 而不在任何 BomLine**，它將從合規計算消失。
→ 緩解：遷移前執行 audit query，列出只在 PS 而不在 BomLine 的供應商，人工確認是否需要補建 BomLine。

**[風險] 合規計算結果變化影響現有 Dashboard**
新計算邏輯會產生和舊邏輯不同的結果（消除錯誤的靜默缺口，也可能出現新的缺口告警）。
→ 緩解：部署前先在測試環境比對新舊計算結果，告知使用者這是準確度提升而非系統錯誤。

**[Trade-off] BomLine 供應商多對多增加查詢複雜度**
原先一個 BomLine = 一個供應商，現在一個 BomLine 可有多個供應商，合規計算的查詢量增加。
→ 接受：紡織業每條 BomLine 通常有 1-3 個替代供應商，查詢量在可接受範圍。必要時加 index 在 `bom_line_suppliers.bom_line_id`。

**[風險] 前端 BuyerProductsView BOM 表格顯示複雜化**
多供應商需要 expandable row 或 badge list 顯示，UI 改動較大。
→ 緩解：MVP 先顯示主要供應商（role=primary），替代供應商以數字標記「+N 替代」，點擊展開。

## Migration Plan

1. **建立 `bom_line_suppliers` 表**（新 migration）
2. **資料遷移**：將 `product_bom_lines.designated_supplier_id` 遷移至 `bom_line_suppliers`（role: primary, source: erp_designated）
3. **新增欄位**：`product_bom_lines.bom_line_type` DEFAULT 'material'
4. **新增欄位**：`material_groups.group_type` DEFAULT 'material'
5. **移除欄位**：`buyer_product_suppliers.material_group_id`（需確認無程式碼依賴）
6. **移除欄位**：`product_bom_lines.designated_supplier_id`（需確認無程式碼依賴）
7. **更新 Seeder**：為服務型供應商（成衣廠、染整廠）的 BomLine 標記正確的 `bom_line_type` 和 `material_group`
8. **後端邏輯重寫**：`SupplierComplianceStatusService`、`syncApplicableRegulations`
9. **前端更新**：BOM 表格、合規詳細頁、供應商入口採購需求頁

**Rollback**：migration 可 rollback（`down()` 還原欄位），但合規計算邏輯切換需要 feature flag 或分支部署。

## Open Questions

- Q1：替代供應商（alternate）的合規狀態是否影響產品整體合規評分？（建議：主要供應商決定合規狀態，替代供應商作為參考）
- Q2：服務型 BomLine（成衣縫製服務）的 HS Code 如何處理？（成衣 HS Code 是最終產品碼，不是服務碼）
- Q3：未來是否需要支援 BomLine 層級的合規豁免（exemption）？（暫時 Non-Goal，但 DB schema 應保留擴充空間）
