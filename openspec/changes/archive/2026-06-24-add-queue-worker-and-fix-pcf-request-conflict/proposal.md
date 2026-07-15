## Why

驗證 `fix-pcf-gap-scan-sales-product-migration` 時發現兩個與 SalesProduct 遷移完全無關的既有問題：

1. **碳排填報請求重複建立沒有擋下來**：`pcf-emission-gap-scan` spec 寫著「已有 pending 請求時不重複建立 → 回傳 409」，但 `ProductBomLineController::requestEmission()` 從未實作這個檢查，重複觸發永遠回 200（`{"created":0,"skipped":1}`），buyer 端無法區分「成功建立」與「其實已經有一筆在等」。
2. **Laravel 佇列沒有任何 worker 在跑**：docker-compose.yml 沒有任何容器執行 `php artisan queue:work`（`esgchain-celery` 是 Python/FastAPI 側 esgchain-ai 的 Celery worker，與 Laravel 佇列無關）。`QUEUE_CONNECTION=redis` 本身設定正確（已確認 host `.env` 與容器內實際解析的 `config('queue.default')` 一致，先前懷疑的「config cache 不一致」是誤判——容器內 `/app/.env` 是建置時期 COPY 進去的舊檔案殘留，docker-compose 的 `env_file:` 才是實際生效的環境變數來源，兩者內容不同但不影響執行結果）。代表所有 `ShouldQueue` job（`PcfEmissionGapScanJob`、`ChemicalComplianceScanJob`、`Scope3PushJob`、`SyncPcfRecordToAi`、`RecalcPcfForAffectedProductsJob`、`DispatchSaqScoringJob`、`DispatchMaterialEmissionEstimate`、`ComplianceDocExpiryJob`、`ErpScheduledSyncJob`）目前 dispatch 後都只是堆進 Redis，永遠不會被消費執行。

兩者彼此獨立（一個是 Controller 業務邏輯缺失，一個是部署架構缺失），但都是小範圍修復，且是同一輪驗證中一起發現的，合併在一個 change 處理，不需要拆成兩個。

## What Changes

- `ProductBomLineController::requestEmission()` 新增檢查：若該 BOM 行對應的 (material_item_id × supplier_id) 已存在 `status = 'pending'` 的 `PcfRequestLine`，回傳 409 並說明已有待填報請求，不再呼叫 `scan()`
- 同一方法順手修正另一個同類落差：spec 寫「手動觸發成功 → 回傳 201」，但現有程式碼沒有指定狀態碼（預設 200）；成功建立時改為明確回傳 201
- `docker-compose.yml` 新增一個 Laravel queue worker 服務（沿用 `esgchain-api` image，覆寫 command 為 `php artisan queue:work redis --tries=3`），讓所有既有 `ShouldQueue` job 真正開始被執行
- 不變更任何 job class 本身的程式碼（先前各次 change 已確認它們的業務邏輯是對的，只是從未被執行過）

## Capabilities

### New Capabilities
（無）

### Modified Capabilities
- `pcf-emission-gap-scan`：「採購商手動觸發填報請求」需求新增「已有 pending 請求時回 409」這個行為的明確驗收（spec 文字本身已經這樣寫，這次是補上對應的 scenario 細節與實作，讓 spec 與程式碼第一次真正一致）

## Impact

- 後端：`ProductBomLineController.php`（或 `PcfEmissionGapScanService.php`，依 design 決定檢查放哪一層）
- 部署：`docker-compose.yml` 新增一個 queue worker 服務
- 受影響功能：新增 worker 後，所有現存的非同步 job（BOM 匯入後掃描、supplier 變更後掃描、化學合規掃描、Scope3 推送、PCF 同步至 AI、SAQ 評分派發等）會從「dispatch 後永遠不執行」變成「真正開始執行」——這是預期的修復效果，但也代表系統行為會有實質變化，需要在 design.md 評估風險
- 不在本次範圍：不調整任何個別 job 的內部邏輯；不處理 host `esgchain-api/.env` 與容器內殘留 `.env` 檔案內容不同步的問題（已確認不影響實際執行結果，純粹是建置流程的副產物，留待之後有需要時再整理）
