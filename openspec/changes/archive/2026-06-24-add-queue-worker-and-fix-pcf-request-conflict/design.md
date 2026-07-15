## Context

這個 change 修兩個彼此獨立、但在同一輪驗證中發現的問題：

**問題 A（部署架構）**：`docker-compose.yml` 目前只有 `esgchain-api` 一個容器執行 `php artisan serve`，沒有任何容器執行 `php artisan queue:work`。已確認 `QUEUE_CONNECTION=redis` 設定本身正確（host `.env` 與容器內 `config('queue.default')` 解析結果一致），先前懷疑的「config cache 不一致」是誤判：容器內 `/app/.env` 是 Dockerfile build 階段透過 `composer create-project laravel/laravel .` 殘留的舊檔案內容，docker-compose 的 `env_file: ./esgchain-api/.env` 才是實際注入容器環境變數的來源，兩個檔案內容不同但不影響執行結果（Laravel 的 `env()` 優先讀取真實環境變數，`.env` 檔案只在環境變數未設定時補位）。已確認 Redis 佇列目前是空的（`LLEN queues:default` = 0），沒有歷史堆積的 job 需要擔心。

**問題 B（業務邏輯）**：`pcf-emission-gap-scan` spec 寫著重複觸發填報請求應回 409，但 `ProductBomLineController::requestEmission()` 從未實作這個檢查。`PcfRequestLine.status` 欄位（enum: pending/submitted/verified，預設 pending）已經存在，`PcfRequestLine::pcfRequest()` 關聯可以拿到 `supplier_id`，加這個檢查不需要任何資料庫遷移。

## Goals / Non-Goals

**Goals:**
- 新增一個 queue worker 服務，讓現有所有 `ShouldQueue` job 真正被執行，而不只是 dispatch
- `requestEmission()` 在已有 pending 請求時正確回 409，符合 spec 既有文字

**Non-Goals:**
- 不調整任何個別 job class 的內部邏輯（先前三次 change 已確認業務邏輯本身是對的，問題只在於從未被執行）
- 不處理 host/容器內 `.env` 檔案內容不同步的問題（已確認不影響執行結果，純粹建置流程副產物）
- 不引入 Laravel Horizon 或其他佇列監控工具（超出本次範圍，若未來需要監控介面可另開 change）
- 不處理 job 失敗重試策略以外的佇列調優（如 priority queue、rate limiting）

## Decisions

1. **Worker 服務沿用 `esgchain-api` 的 image，只覆寫 command，不另建 Dockerfile**。在 `docker-compose.yml` 新增一個 `esgchain-queue-worker` 服務，`build` 區塊與 `esgchain-api` 相同（或用 `image: esg-chain-esgchain-api` 直接引用已建好的映像），command 改為 `php artisan queue:work redis --tries=3 --max-time=3600`，沿用同一個 `env_file`。
   - 替代方案：把 queue worker 邏輯塞進 `esgchain-api` 容器內用 supervisor 同時跑 `php artisan serve` 和 `php artisan queue:work`——放棄，因為這樣 HTTP server 和 queue worker 共用同一個容器生命週期，worker 卡住或記憶體洩漏會直接影響 API 服務的健康狀態，分離成獨立容器更符合一般 Laravel 佇列部署慣例，重啟/擴展也更容易獨立操作。

2. **`--max-time=3600` 讓 worker 定期重啟，避免長時間執行的記憶體洩漏**。這是 Laravel 官方建議的佇列 worker 運維做法，搭配 docker-compose 的 `restart: unless-stopped`（或專案既有慣例）讓容器自動重啟接續處理。

3. **409 檢查放在 `PcfEmissionGapScanService::scan()` 內部，不放在 Controller**。理由：`scan()` 本身就是「對 (material_item × supplier) 做缺口檢查」的核心邏輯所在地，且未來如果有其他呼叫端（目前已知三個：BOM 匯入後、supplier 變更後、買家手動觸發）也應該套用同一個防重複規則。但 `scan()` 目前的設計是批次處理多筆 BomLineSupplier、回傳彙總計數（created/skipped），不是設計成回傳「單筆衝突」的語意；因此實際做法是：
   - 在 `scan()` 內，對於遇到「已存在 PcfRequestLine 但 status != pending」的情況維持現有 `skipped++`（這不是衝突，是已完成/已提交）
   - 對於「已存在 status = pending 的 PcfRequestLine」這個情況，`scan()` 本身仍然視為 `skipped++`（批次掃描情境下這是正確行為——不應該因為一筆已有 pending 請求就讓整批掃描失敗）
   - 409 的判斷只在 `ProductBomLineController::requestEmission()`（單筆、使用者主動觸發的情境）這一層做：在呼叫 `scan()` 之前，先檢查這筆 BOM 行對應的 (material_item_id × supplier_id) 是否已有 pending 的 PcfRequestLine，若有則直接回 409，不呼叫 `scan()`
   - 這跟最初評估的「放 Service 層」不同，修正後決定放 Controller 層，因為「409 衝突」是這個單一 HTTP 端點的語意，不是 `scan()` 批次掃描邏輯該管的事，避免把單筆 API 語意污染到共用的批次掃描方法
   - 替代方案：讓 `scan()` 回傳值新增一個 `conflicts` 陣列，Controller 依此決定要不要回 409——放棄，這會改變 `scan()` 的回傳結構，影響到其他兩個呼叫端（Job/Observer）原本不需要關心這個概念，徒增複雜度

## Risks / Trade-offs

- [風險] 啟用 worker 後，所有先前「形同沒在運作」的非同步功能會第一次真正執行（BOM 匯入後掃描、supplier 變更後掃描、化學合規掃描、Scope3 推送、PCF 同步至 AI、SAQ 評分派發）——這是預期的修復效果，但範圍比這次 change 表面上看起來大，需要在驗證階段確認這些 job 個別執行起來沒有各自的潛在問題（例如 `ChemicalComplianceScanJob`、`Scope3PushJob` 等先前完全沒被驗證過實際執行路徑）→ 緩解：驗證階段以小範圍 dispatch 測試為主，不在這個 change 裡逐一驗證每個 job 的完整業務邏輯（那已經超出「加 worker」這個基礎設施修復的範圍），只驗證 worker 本身能正常消費佇列、`PcfEmissionGapScanJob` 確實被執行；若發現其他 job 有問題，記錄下來但不在本次修
- [風險] 409 判斷邏輯如果漏了某個邊界條件（例如同一 material 但不同 BOM 行、同一 supplier）可能誤擋或漏擋 → 緩解：嚴格依 `(material_item_id × supplier_id)` 兩個欄位的組合判斷，與 `scan()` 既有去重邏輯使用同一組鍵值，保持一致性
- [Trade-off] 新增一個常駐容器會增加少量資源消耗 → 可接受，這是讓非同步功能真正運作的必要成本，是這次修復的目的而非副作用

## Migration Plan

1. `docker-compose.yml` 新增 queue worker 服務
2. `docker compose up -d` 啟動新服務，確認容器穩定運行（不是立刻 crash）
3. 修正 `ProductBomLineController::requestEmission()` 加上 409 檢查
4. 部署後實測：
   - 409 路徑：對已有 pending PcfRequestLine 的 BOM 行呼叫 `requestEmission()`，確認回 409
   - worker 路徑：dispatch 一個 `PcfEmissionGapScanJob`（或透過 BOM 匯入間接觸發），確認 worker log 顯示有消費並執行，Redis 佇列回到空（不再堆積）
5. 無需資料庫遷移，無需 rollback 腳本（`docker-compose.yml` 變更可直接 `docker compose down` 該服務回滾；Controller 變更可直接 revert 程式碼）

## Open Questions

（無）已確認 `docker-compose.yml` 目前所有既有服務都沒有設定任何 `restart` policy，本次新增的 worker 服務比照不設定，保持風格一致；`--max-time=3600` 本身已足以讓 worker 定期重啟接續處理，不依賴 docker 層級的 restart policy
