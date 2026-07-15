## Context

目前 `buyer_products` 與 `trade_goods` 兩張表完全獨立，沒有外鍵或關聯表。BuyerProduct 擁有 BomLine（原料清單）與 PCF 快照，TradeGood 擁有 HS Code、CBAM/EUDR 標記及靜態 `embedded_emissions`。業務上，一個採購產品（BuyerProduct）可以對應多個出口品項（TradeGood），反之亦然（M:N）。

## Goals / Non-Goals

**Goals:**
- 建立 `buyer_product_trade_goods` mapping table，支援 M:N 關聯與 relation_type 語義
- BuyerProductsView 新增「出口商品」Tab，讓採購人員從產品頁管理出口連結
- PCF 快照更新時，`finished_good` 類型的 TradeGood `embedded_emissions` 自動同步
- `inferred_regulations` 含 EUDR 時，自動設定對應 TradeGood 的 `is_eudr_applicable = true`
- Seeder 補充 demo mapping 資料

**Non-Goals:**
- 不修改 TradeGood 的 CBAM 計算邏輯（靜態 `is_cbam_applicable` 維持手動設定）
- 不建立獨立的「產銷對照」管理頁面（從 BuyerProductsView 管理即可）
- 不處理跨組織的產品對應

## Decisions

### D1：使用獨立 mapping table，不在 TradeGood 加 FK

**選擇**：新增 `buyer_product_trade_goods` 中間表。

**理由**：TradeGood 可對應多個 BuyerProduct（同一原料供給多個終端產品），若在 TradeGood 加 `buyer_product_id nullable FK` 只能表達 1:1。M:N 是正確的業務模型。

**替代方案否決**：nullable FK on TradeGood — 只支援 1:1，且方向語義錯誤（TradeGood 不應主動持有 BuyerProduct 的 FK）。

### D2：relation_type 採用三值 enum

```
finished_good  → BuyerProduct 的成品直接以此 TradeGood 身份出口（PCF 同步對象）
component      → BomLine 中的某項原料/半成品單獨出口（可選連結 bom_line_id）
equivalent     → 同一產品在不同市場/國家以不同 HS Code 報關
```

**理由**：三種語義決定下游同步行為不同。`finished_good` 才觸發 `embedded_emissions` 自動覆寫；`component` 的 PCF 不覆寫（原料碳排已在 BomLine 層追蹤）。

### D3：PCF 同步採用事件掛鉤，不用 Celery

**選擇**：在 `PcfCalculationService::snapshot()` 完成後，同步呼叫 `ExportLinkSyncService::syncFromPcf(BuyerProduct)`。

**理由**：PCF 快照本身已透過 Celery 非同步觸發，同步寫入 TradeGood `embedded_emissions` 是輕量操作（UPDATE by FK），不需要再排隊。避免雙層非同步帶來的時序問題。

### D4：bom_line_id 為 nullable，不強制

**理由**：`finished_good` 和 `equivalent` 類型不需要對應到特定 BomLine。`component` 類型建議填但不強制，允許粗粒度的「此 TradeGood 是此產品的原料之一」關聯。

### D5：前端 UI 入口在 BuyerProductsView「出口商品」Tab

**理由**：BuyerProduct 是主體，出口是其下游行為。在產品頁管理比從 TradeGood 反向查詢更自然。Tab 列出已連結 TradeGood，提供「新增連結」下拉選單（從現有 TradeGood 搜尋選取）。

## Risks / Trade-offs

- **embedded_emissions 被覆寫**：當 `finished_good` 連結存在時，PCF 快照會覆寫 TradeGood 的靜態 `embedded_emissions`。若使用者手動填的值比 PCF 更準確，會被覆蓋。→ 緩解：在同步前記錄舊值至欄位 `embedded_emissions_manual_override`，或在 UI 顯示「已由 PCF 自動更新」提示，允許使用者鎖定不自動覆寫。
- **TradeGood seeder 使用 truncate**：重跑 seed 會清空 trade_goods，mapping table 的資料也需要一起清除。→ 緩解：`buyer_product_trade_goods` 加入 `TradeGoodSeeder` 的清除流程，或改用 `firstOrCreate` 策略。

## Migration Plan

1. 執行 migration 建立 `buyer_product_trade_goods` table
2. 部署後端 Model / Service / Controller / Route
3. 部署前端 Tab UI
4. 執行 Seeder 補充 demo mapping 資料（不影響現有資料，firstOrCreate）
5. 驗收：BuyerProduct 詳情頁「出口商品」Tab 可正常 CRUD 連結

Rollback：刪除 `buyer_product_trade_goods` table（`down()` migration），前端 Tab 移除即可，不影響既有 BuyerProduct 或 TradeGood 資料。

## Open Questions

- `embedded_emissions` 自動覆寫是否需要 `locked` flag？（若需要，加入 migration）
- `equivalent` 類型是否需要額外的 `target_market` 欄位？（例如 US_MARKET → HS 6101.30，EU_MARKET → HS 6101.20）
