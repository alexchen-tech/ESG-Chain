## 1. 新增 Laravel Queue Worker 服務

- [x] 1.1 `docker-compose.yml` 新增 `esgchain-queue-worker` 服務：沿用 `esgchain-api` 的 build context/Dockerfile，`env_file` 與 `depends_on` 比照 `esgchain-api`，command 覆寫為 `php artisan queue:work redis --tries=3 --max-time=3600`
- [x] 1.2 `docker compose up -d` 啟動新服務，確認容器啟動後穩定運行（不是啟動即 crash），檢查 log 顯示 worker 正常進入監聽狀態
- [x] 1.3 實測 worker 真的會消費佇列：手動 dispatch 一個 job（例如透過既有的「採購商手動觸發填報請求」流程間接觸發 `PcfEmissionGapScanJob`，或直接 tinker dispatch），確認 worker log 顯示有執行該 job，且 Redis 佇列（`LLEN queues:default`）執行後回到 0

## 2. 修復 requestEmission() 的 409 與狀態碼

- [x] 2.1 `ProductBomLineController::requestEmission()` 在呼叫 `scan()` 之前，新增檢查：查詢該 (material_item_id × supplier_id) 是否已有 `status = 'pending'` 的 `PcfRequestLine`（透過 `PcfRequestLine::whereHas('pcfRequest', fn($q) => $q->where('supplier_id', $primarySupplier->supplier_id))->where('material_item_id', $bomLine->material_item_id)->where('status', 'pending')->exists()`），若存在回傳 409 並說明已有待填報請求
- [x] 2.2 成功建立時的回應改為明確 `201`（目前沒有指定狀態碼，預設 200，與 spec「回傳 201」不符）
- [x] 2.3 實測 409 路徑：對同一筆 BOM 行重複觸發「發送填報請求」，第二次確認回 409
- [x] 2.4 實測成功路徑狀態碼：對一筆全新缺口觸發請求，確認回 201（沿用先前 change 驗證時建立測試 fixture 的方式，測試後清理）

## 3. 收尾

- [x] 3.1 `openspec/specs/pcf-emission-gap-scan/spec.md` 的 MODIFIED 區塊已準備好（補上 409 scenario 細節），待 archive 時套用
- [x] 3.2 確認本次新增 worker 後，至少 `PcfEmissionGapScanJob` 這條路徑被驗證過真的能執行；其餘 job（`ChemicalComplianceScanJob`、`Scope3PushJob`、`SyncPcfRecordToAi` 等）先前從未被驗證過實際執行路徑，記錄下來但不在本次逐一驗證，留待各自需要時再個別確認
