## Context

ESG-Chain 的採購品合規模組存在兩層供應商管理結構：

- **BuyerProductSupplier（AVL）**：產品層級的核可供應商清單，手動維護，用於限制 BOM 線供應商選取範圍
- **BomLineSupplier**：BOM 線層級的實際供應商指派，含 ERP 匯入（`erp_designated`）與手動（`manual`）兩種來源

由於採購商的 BOM 資料 100% 來自 ERP 匯入，`erp_designated` 供應商完全繞過 AVL 驗證，AVL 從未被填入、長期為空。手動指派場景極少，且存在 `syncApplicableRegulations()` 未定義的 runtime bug（應為 `syncInferredRegulations()`）。

現有供應商控管機制：`suppliers.status`（`certified / pending / suspended / terminated`）— 這個欄位比 AVL 更有業務意義，是 Supplier MDM 核心資料。

## Goals / Non-Goals

**Goals:**
- 移除 `buyer_product_suppliers` 表與所有相關 API、UI
- 移除 `BomLineSupplierController` 中的 AVL 驗證邏輯
- 將 BOM 線供應商手動選取的控管門檻改為 `status=certified`
- 新增具搜尋/篩選功能的開放供應商選單，替代 AVL 限制的下拉
- 修復三處 `syncApplicableRegulations()` → `syncInferredRegulations()` bug

**Non-Goals:**
- 不改動 ERP 匯入流程（`BomLineImportService` 保持不變）
- 不新增供應商審核流程或 AVL 替代主資料結構
- 不改動 `BomLineSupplier` 表結構（`role`、`source`、`sort_order` 保留）
- 不影響 `TradeGoodSupplier` 或任何其他供應商關聯

## Decisions

### 決策 1：以 `status=certified` 取代 AVL 作為手動指派門檻

**選擇**：手動新增 BomLineSupplier 時，驗證 `supplier.status === 'certified'`，而非 AVL 成員資格。

**原因**：`status=certified` 是 Supplier MDM 中已存在、有業務意義的核可狀態，代表供應商已通過 SAQ 與合規審核。相較於 AVL 需要人工額外維護一份清單，`status=certified` 是自動反映供應商審核結果的主資料，不需重複管理。

**替代方案考慮**：
- 保留 AVL 但自動從 ERP 匯入時填入 → 語意模糊（AVL 變成「曾供貨紀錄」而非「核可清單」），且增加匯入複雜度
- 完全不設門檻（任何供應商都可指派）→ 風險過高，可能指派 suspended/terminated 供應商

### 決策 2：供應商選單改為帶搜尋的 Combobox，而非固定下拉

**選擇**：BOM 線供應商選取改為 Combobox 元件，輸入關鍵字後 API 動態搜尋 `certified` 供應商（`GET /api/v1/suppliers?status=certified&q=keyword&per_page=20`），支援 Tier 篩選。

**原因**：移除 AVL 後候選池從「數個 AVL 成員」擴大為「所有 certified 供應商」（可能數百筆），固定下拉清單無法使用，需要即時搜尋。

**替代方案考慮**：
- 一次載入全部 certified 供應商到前端過濾 → 如果供應商數量多（>500）會有效能問題，且無 Tier 篩選

### 決策 3：drop migration 而非軟刪除

**選擇**：用 `Schema::dropIfExists('buyer_product_suppliers')` 新增 migration 刪除整個表。

**原因**：`buyer_product_suppliers` 在 ERP-first 場景下從未被有效填入，資料無業務保存價值。保留空表只增加維護負擔。

**替代方案考慮**：
- 保留表但不使用 → 會讓未來開發者困惑，技術債累積

## Risks / Trade-offs

**[風險] 已有少量手動填入的 AVL 記錄被刪除** → 執行 migration 前先備份（`SELECT * FROM buyer_product_suppliers`），預計影響極低（ERP-first 場景下幾乎為零）

**[風險] API breaking change：移除 `/api/v1/buyer-products/{id}/suppliers` 端點** → 確認無外部系統呼叫此端點（目前僅前端使用），前端同步移除呼叫

**[風險] `syncApplicableRegulations()` bug 修復可能影響現有合規推論結果** → 改為 `syncInferredRegulations()` 後會觸發正確的合規推論，需確認現有 `applicable_regulations` 欄位資料仍正確

**[Trade-off] Combobox 取代下拉，需多一次鍵盤輸入** → 換取無限供應商候選池與 Tier 篩選能力，整體 UX 更合理

## Migration Plan

1. **資料備份**：記錄 `buyer_product_suppliers` 現有行數，確認影響範圍
2. **後端變更**（無 downtime）：
   - 新增 drop migration
   - 移除 Controller、routes、model 關聯
   - 修復 `syncApplicableRegulations` bug
   - 修改 BomLineSupplierController 驗證邏輯
3. **前端變更**（同步部署）：
   - 移除 AVL 管理 UI 區塊
   - 替換供應商選單元件為 Combobox
4. **部署**：後端 + 前端同步發佈，避免 API 404 期間前端仍呼叫舊端點
5. **驗證**：確認 ERP 匯入流程正常、手動新增 BomLineSupplier 使用新驗證邏輯
