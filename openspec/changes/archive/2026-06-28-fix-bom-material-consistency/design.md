## Context

ProductBomLine 目前有兩個欄位語意問題：

1. `material_name` / `hs_code` 是建立當下從 MaterialItem 複製的快照，但 `BomLineSupplierController` 讀取時以快照為主（`$bomLine->hs_code ?? $bomLine->materialItem?->hs_code`），而 `ProductBomLineController::index()` 已正確實作 effective 邏輯（主檔優先）。兩處不一致。

2. `store()` 傳入 `material_item_id` 時會複製 name/hs_code，但**不複製 material_group_id**，導致 line.material_group_id 與 materialItem.material_group_id 可能不同。`syncInferredRegulations()` 因為使用 `materialItem?.materialGroup` 優先，計算結果仍正確，但前端直接顯示 line.material_group_id 時可能顯示錯誤分類。

3. `material_item_id` 可為 null，手動建立的 BomLine 事後才會在 `requestEmission()` 被 422 阻擋，無前置提示。

## Goals / Non-Goals

**Goals:**
- 所有讀取 hs_code / material_name 的路徑統一為「主檔優先」
- store()/update() 傳入 material_item_id 時自動同步 material_group_id
- ProductBomLine 新增 `linkage_status` 欄位，前端顯示待連結警告

**Non-Goals:**
- 不修改快照欄位的設計意圖（快照作為 fallback 仍保留）
- 不強制 material_item_id 必填（外部 ERP BOM 匯入初期可能無法立即解析）
- 不改動 syncInferredRegulations() 邏輯（已正確）

## Decisions

### D1：快照語意確認為「fallback」，主檔永遠優先

**決定**：所有讀取端一律採用 `materialItem?.field ?? line.field`，快照僅在無主檔連結時使用。

**理由**：`syncInferredRegulations()` 已採此語意，且 ESG-Chain 的設計原則是物料主檔為 source of truth。若要鎖定值（类似 PcfSnapshot），應建立獨立快照表，而非重用現有欄位。

**替代方案**：快照語意 = 建立當下鎖定值，事後主檔變更不影響 BomLine → 被排除，因為法規合規計算需要反映最新物料分類。

### D2：linkage_status 由後端自動計算，不由前端傳入

**決定**：`linkage_status` 在 store()/update() 時根據 `material_item_id` 是否存在自動設定，不開放 API 呼叫端傳入。

**理由**：避免前後端狀態不同步；linkage_status 是衍生欄位（derived from material_item_id），應永遠保持一致。

### D3：Migration 回填策略

**決定**：`linkage_status` 預設值透過 migration 回填：`material_item_id IS NOT NULL → linked`，否則 `unlinked`。

### D4：material_group_id 同步策略

**決定**：傳入 material_item_id 時，若呼叫端**未明確傳入 material_group_id**，則從 MaterialItem 帶入，並設 `material_group_source='erp_imported'`。若呼叫端明確傳入 material_group_id，則尊重呼叫端的值（維持 manual 語意）。

## Risks / Trade-offs

- **[風險] 主檔改名後 BOM 列表顯示名稱變動**：採用主檔優先後，MaterialItem.name 修改會立即反映在所有 BomLine 顯示。→ 可接受，符合 source of truth 設計；若需鎖定，應在 BOM CSV 匯出時做快照，而非資料庫層。
- **[風險] linkage_status 與 material_item_id 可能在直接 DB 操作後不同步**：→ 僅透過 Model Observer 或 Controller 層維護一致性；禁止直接更新 linkage_status 欄位。

## Migration Plan

1. 新增 migration：`product_bom_lines` 加 `linkage_status` enum `('linked','unlinked')` default `'unlinked'`
2. 同一 migration 回填：`UPDATE product_bom_lines SET linkage_status = IF(material_item_id IS NOT NULL, 'linked', 'unlinked')`
3. 更新 ProductBomLineController store()/update()
4. 更新 BomLineSupplierController 讀取順序
5. 更新前端 BOM 列表顯示 linkage_status 警告

**Rollback**：migration rollback 移除 linkage_status 欄位即可，應用程式行為退回現狀。
