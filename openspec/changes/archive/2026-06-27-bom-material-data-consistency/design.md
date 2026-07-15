## Context

`ProductBomLine` 採用「快照 + 關聯」混合設計：

- 快照欄位：`material_name`、`hs_code`（ERP 匯入時寫入，供離線/歷史對照）
- 關聯欄位：`material_item_id` → `MaterialItem`、`material_group_id` → `MaterialGroup`

`ProductBomLineController::index()` 已經計算出 `effective_material_name`/`effective_hs_code`/`effective_material_group`（優先採用 `materialItem` 的即時值，沒有關聯才 fallback 快照），但：

1. 前端 `SalesProductDetailView.vue` 沒有使用這些 effective 欄位，仍顯示快照
2. 手動建立路徑（`store()`）不會像 ERP 匯入路徑（`BomLineImportService::importFromArray()`）一樣自動建立/連結 `MaterialItem`，造成同一張表有些行有 effective 來源、有些沒有
3. `SalesProduct::syncInferredRegulations()` 直接吃 BomLine 自己的 `material_group_id`，沒有走 effective 邏輯，與 `index()` 的判斷準則不一致
4. `material_item_id` 是碳排填報的隱性必要條件，但建立時沒有任何提示

## Goals / Non-Goals

**Goals:**
- 前端顯示與後端「有效值」判斷邏輯一致，使用者看到的名稱/HS Code 永遠反映物料主檔現況（若已關聯）
- 法規推算與「合規文件需求顯示」採用同一套 effective 來源判斷準則
- 手動建立 BOM 明細時，若使用者選擇了物料主檔，快照欄位自動帶入，避免顯示與主檔不同步
- 在使用者建立/檢視 BOM 明細的當下，提示「未綁定物料主檔」會影響碳排填報，而非等到操作失敗才發現

**Non-Goals:**
- 不新增資料庫欄位或 migration
- 不改變 ERP 匯入路徑既有的 `material_group_source` 保護邏輯（manual 標註不被 ERP 覆蓋）
- 不強制要求建立 BOM 明細時必填 `material_item_id`（部分明細本來就可能尚無對應物料主檔，例如服務類 `bom_line_type=service`）
- 不處理 `BomLineSupplier` 循環參照檢查（`assertNoCycle()` 已存在且運作正常，不在本次範圍）

## Decisions

### D1：前端改用 effective 欄位顯示

**選擇**：`SalesProductDetailView.vue` 的 BOM Tab 改用 `bl.effective_material_name`、`bl.effective_hs_code`，taking fallback to快照 only when API 未回傳 effective（向下相容）。

**理由**：後端已經做好這個計算，前端應該信任並使用它，而非重複實作一套不同步的顯示邏輯。

---

### D2：手動建立時自動回填快照

**選擇**：`ProductBomLineController::store()` 在驗證後，若 `material_item_id` 有值，查詢該 `MaterialItem` 並用其 `name`/`hs_code` 覆蓋（或補齊）`material_name`/`hs_code` 後再呼叫 `bomLineService->create()`。

**捨棄方案**：完全移除快照欄位，全部即時 join `MaterialItem` — 影響範圍過大（ERP 匯入、CSV 匯入都依賴快照欄位作為歷史快照，且服務類明細無物料主檔），不在本次範圍。

**理由**：最小改動達成「快照與關聯不互相矛盾」，沿用既有的「快照 + 關聯」架構，只是補上手動路徑缺的自動同步步驟。

---

### D3：法規推算統一改用 effective material group

**選擇**：`syncInferredRegulations()` 改為：對每條 BomLine，先嘗試 `materialItem?->materialGroup`，若無則 fallback `materialGroup`（line 自身關聯），取得 `required_doc_types` 後續邏輯不變。

**理由**：與 `ProductBomLineController::index()` 的 `effective_material_group` 計算邏輯統一，確保「畫面上看到的合規文件需求」與「法規推算依據的物料群組」是同一個。

---

### D4：未綁定物料主檔的視覺提示

**選擇**：BOM 明細列表中，若該行 `material_item_id` 為 null 且 `bom_line_type === 'material'`（排除 service 類型），顯示 inline 警示標籤（如「未綁定物料主檔」），不阻擋任何操作，純資訊提示。

**理由**：問題的本質是「使用者在建立後、申請填報前不知道會卡住」，最小成本的解法是提前在列表呈現狀態，而非加驗證規則卡建立流程（建立流程卡關會影響 ERP 匯入與服務類明細的既有彈性）。

## Risks / Trade-offs

- **[風險] effective 欄位依賴關聯預載入**：若 `index()` 之外的 API（如 BOM 匯入回傳）沒有預載入 `materialItem`，effective 欄位會是 null → 緩解：前端統一 fallback 到快照欄位，不會出現空白
- **[Trade-off] 法規推算來源改變可能影響既有產品的 `inferred_regulations` 結果**：理論上只有「BomLine 自身 material_group_id 與其 materialItem 的 material_group_id 不一致」的少數異常資料才會改變結果，正常資料不受影響 → 部署後建議手動觸發一次批量重算（既有 `sync:product-regulations` Artisan Command）以校正歷史資料
- **[風險] 手動建立時自動回填可能與使用者輸入的自訂名稱衝突**：若使用者選了 `material_item_id` 但同時手動輸入了不同的 `material_name` → 決策：以 `material_item_id` 對應的主檔值為準（覆蓋），因為快照欄位的設計目的就是反映關聯物件，允許不一致會重新導致本次要修的問題
