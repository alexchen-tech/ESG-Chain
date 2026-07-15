## Context

這是第三個被同一根因影響的模組，模式與前兩個 change 完全一致：2026-06-17 的 SalesProduct 重構改了資料表結構（`product_bom_lines.buyer_product_id` 重新命名為 `sales_product_id`）與 Eloquent 關聯（`ProductBomLine` 只剩 `salesProduct()`），但呼叫端程式碼沒有跟上。差異在於本次的破壞方式更隱蔽：

- `BomLineImportService`（已修復）與 Shipment 模組（已修復）的問題是透過**同步 HTTP request** 暴露的，使用者一操作就會看到 500
- `PcfEmissionGapScanService::scan()` 是透過 `PcfEmissionGapScanJob`（`ShouldQueue`）**非同步佇列**呼叫，第 35 行 `->with(['bomLine.materialItem', 'bomLine.buyerProduct'])` 是查詢建構的第一步、無條件執行（不在任何 `if` 分支內），代表這個方法**每一次**被呼叫都會在取得資料的當下就拋出 `RelationNotFoundException`。佇列任務失敗預設只會寫入 `failed_jobs` 表或 log，不會讓觸發它的原始請求（BOM 匯入、supplier 變更、手動觸發填報）失敗或讓使用者看到任何錯誤——這正是這個 bug 能夠存活到現在都沒被發現的原因。

`scan()` 還有第二層問題（第 42 行 `where('buyer_product_id', $buyerProductId)`），但因為第 35 行的關聯錯誤會先發生，第 42 行的問題目前還沒有機會浮現；修好第 35 行後才會輪到它報錯，所以兩處要一次修好，否則修復後再測一次又會在第 42 行卡住。

`BomLineSupplierObserver::dispatchScan()` 第 32 行 `$bomLineSupplier->bomLine?->buyer_product_id` 是另一種破壞模式：Eloquent 對不存在的屬性存取不會拋例外，只會靜默回傳 `null`。這代表即使 `scan()` 本身修好了，透過 Observer 觸發的掃描（每次 primary supplier 建立/變更時）目前永遠把 `null` 當作 `salesProductId` 傳入，等同於「全域掃描」而非「只掃這個產品」——範圍不對但不會報錯，是本次連帶要修的邏輯正確性問題，不只是改名。

## Goals / Non-Goals

**Goals:**
- 讓 `PcfEmissionGapScanService::scan()` 三個觸發來源（BOM 匯入後、supplier 變更後、採購商手動觸發）都能真正執行成功，不再無條件拋例外
- 修正 `BomLineSupplierObserver::dispatchScan()` 的範圍邏輯：變更某一筆 BomLineSupplier 後，掃描範圍應正確限定在該 BomLine 所屬的 SalesProduct，而非靜默退化為全域掃描
- 統一方法簽章命名（`$buyerProductId` → `$salesProductId`），含 `PcfEmissionGapScanJob` 建構子參數與 log 欄位
- 更新 `pcf-emission-gap-scan` spec 中過時的範例端點路徑

**Non-Goals:**
- 不重新跑全專案掃描——`fix-shipment-sales-product-migration` 的 design.md 已完整掃描並分類完畢（TradeGood 別名生態系確認正常運作、舊版 buyer-products 路由群組確認為已知死代碼）。本次只需在 tasks 最後核對那份已知清單裡的項目狀態沒有變化，不需要重新執行 `grep -rn` 全量掃描
- 不處理已知的 `buyer-products` 路由群組死代碼（沿用前兩次 change 的決定，留給另一個專門的死代碼清理 change）
- 不改變 `PcfRequest`/`PcfRequestLine` 的資料結構（已確認這兩個 model 從未有過 `buyer_product_id` 欄位，本來就只有 `supplier_id`/`material_item_id`，不受這次欄位改名影響）

## Decisions

1. **`scan()` 第 35 行與第 42 行一次修完，不分階段**。先前在 BOM 模組與 Shipment 模組都遇過「修一處又冒出下一處」的情況，這次既然已經透過調查同時找到兩處關聯到同一個問題的程式碼，直接一次修正，避免又要重新部署驗證一輪。
   - 替代方案：先只修第 35 行，部署驗證後再修第 42 行——放棄，因為兩處都已確認問題所在，分階段沒有額外資訊增益，純粹浪費一次部署/驗證循環。

2. **`BomLineSupplierObserver` 的修正不只是改名，是修正一個邏輯正確性問題**。`bomLine?->sales_product_id` 取代 `bomLine?->buyer_product_id` 後，dispatch 給 Job 的範圍才會是正確的單一產品 ID，而不是因為屬性不存在而靜默變成 `null`（=全域掃描）。需要在 design 裡明確記錄這點，因為它不像其他改名一樣是「单純命名跟著欄位走」，而是「修好後行為會跟現在不一樣」（範圍從『全域』收斂到『單一產品』）——這是預期內、正確的行為修正，不是意外的破壞性變更。

3. **方法參數命名比照欄位名稱統一改掉，不保留相容別名**。`scan(?string $buyerProductId)` → `scan(?string $salesProductId)`，呼叫端（`PcfEmissionGapScanJob` 建構子參數、`ProductBomLineController::requestEmission()` 的具名引數）一併修正。由於這是內部方法簽章（不是對外 HTTP API），沒有外部相容性顧慮，直接統一命名即可。
   - 替代方案：保留 `$buyerProductId` 參數名但內部接 SalesProduct 值——放棄，這只是把命名混亂往後拖，且這次調查已經發現命名不一致本身就是過去出錯的根源之一（容易讓人誤以為還在操作 BuyerProduct）。

## Risks / Trade-offs

- [風險] `scan()` 從未成功執行過，代表現有資料庫裡可能完全沒有任何由這個流程建立的 `PcfRequest`/`PcfRequestLine`——修復後第一次大規模執行（例如手動觸發全域掃描）可能一次性建立大量 PcfRequest，需要先以小範圍（單一 supplier_id 或單一 sales_product_id）驗證再考慮是否要觸發全域掃描 → 緩解：本次驗證以小範圍（指定 supplierId/salesProductId）測試為主，不在這個 change 裡主動觸發全域掃描
- [風險] `BomLineSupplierObserver` 修正後行為改變（範圍從『全域』收斂為『單一產品』），理論上可能讓某些原本（因為 bug）會被全域掃描覆蓋到的情境改為只掃描單一產品 → 可接受，因為「全域」本來就是非預期的副作用（bug 造成的），收斂回正確範圍是修復本身的目的，不是新引入的風險
- [Trade-off] 不重新跑全專案掃描，依賴前一次 change 的掃描結果——若期間有其他人新增了引用 BuyerProduct/TradeGood 的程式碼，可能會被遺漏 → 可接受，因為這是同一個工作階段內連續三次修復，時間窗口很短，重新掃描的邊際價值低於重複執行的成本

## Migration Plan

1. 修正 `PcfEmissionGapScanService::scan()`（第 35、42 行的關聯與欄位名稱，方法簽章參數改名）
2. 修正 `BomLineSupplierObserver::dispatchScan()`
3. 修正 `PcfEmissionGapScanJob`（建構子參數、log 欄位名稱）與 `ProductBomLineController::requestEmission()`（具名引數）
4. `docker cp` 同步、`docker restart`，以 admin 帳號針對「採購商手動觸發填報請求」這條同步可測的路徑（`requestEmission()`）直接驗證 `scan()` 真的能跑完並建立 PcfRequest/PcfRequestLine（這條路徑同步執行 `scan()`，不像 Job 那樣要等佇列，最適合用來驗證核心邏輯）
5. 若有合適的測試資料，額外驗證 `BomLineSupplierObserver` 觸發的非同步路徑（建立/變更一筆 primary BomLineSupplier，確認佇列 Job 不再失敗）
6. 更新 `pcf-emission-gap-scan` spec 範例端點路徑
7. 核對 `fix-shipment-sales-product-migration` design.md 留下的已知清單（TradeGood 生態系、buyer-products 死代碼）狀態無變化，不重新全量掃描
8. 無需資料庫遷移，無需 rollback 腳本

## Open Questions

- 修復後是否要立即手動觸發一次全域 `scan()`（回補過去因 bug 而從未被建立的 PcfRequest）？建議留給使用者在驗證完小範圍正確後自行決定時機，本次 change 不主動執行
