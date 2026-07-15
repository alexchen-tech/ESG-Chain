## ADDED Requirements

### Requirement: 生產批號資料模型

**What**: 建立 `production_batches` 與 `raw_material_origins` 兩張資料表，記錄工廠生產批次的事實資料與原料溯源明細。

**Behavior**:
- `erp_batch_no` 為 UNIQUE，重複推送時執行 upsert（更新不新增）
- `buyer_product_trade_good_id` 允許 null（未匹配狀態）
- `lot_pcf_source` 記錄碳排數值來源（reported / estimated），尚不支援 calculated

#### Scenario: 批號重複推送
- **WHEN** Webhook 或 CSV 帶入已存在的 `erp_batch_no`
- **THEN** 執行 upsert，更新 quantity / production_date 等欄位，不新增重複記錄

---

### Requirement: ERP Webhook 接入

**What**: `POST /api/v1/erp/webhook/production-batches` 接收 ERP 推送的生產批號資料，驗證後建立或更新 ProductionBatch 記錄。

**Behavior**:
- 驗證方式由 `.env` 的 `ERP_AUTH_MODE` 決定：`hmac`（預設）或 `api_key`
- `hmac` 模式：驗證 `X-ERP-Signature: sha256=<HMAC-SHA256(ERP_WEBHOOK_SECRET, raw_body)>`
- `api_key` 模式：驗證 `Authorization: Bearer <ERP_API_KEY>`
- 驗證失敗回傳 401
- 依 `erp_product_code` 匹配 `buyer_product_trade_goods.erp_product_code`，找到則寫入 `buyer_product_trade_good_id`
- 依 `supplier_code` 匹配 `suppliers.code`，找不到則 422

#### Scenario: Webhook 驗證失敗
- **WHEN** `X-ERP-Signature` 不符或 Bearer token 無效
- **THEN** 回傳 401，不建立任何記錄

#### Scenario: Webhook 料號未匹配
- **WHEN** payload 的 `erp_product_code` 在 `buyer_product_trade_goods` 找不到對應
- **THEN** 批號仍建立，`buyer_product_trade_good_id = null`，`source = webhook`

#### Scenario: Webhook 供應商不存在
- **WHEN** payload 的 `supplier_code` 在 `suppliers` 找不到
- **THEN** 回傳 422，不建立記錄

---

### Requirement: CSV Import 備援

**What**: `POST /api/v1/erp/import/production-batches` 接受 multipart CSV 檔案，解析後批次建立生產批號。

**Behavior**:
- CSV 必填欄：`erp_batch_no, supplier_code, quantity, unit`
- 選填欄：`erp_order_no, erp_product_code, production_date, lot_pcf`
- 每列驗證，失敗列收集為 errors 回傳，不中斷整批匯入
- 回傳：`{ imported: N, errors: [{ row: N, message: "..." }] }`

#### Scenario: CSV 部分列錯誤
- **WHEN** CSV 中第 3 列 `supplier_code` 找不到
- **THEN** 第 3 列不匯入，其他列正常匯入，回傳含該列錯誤訊息

---

### Requirement: 生產批號管理 UI

**What**: `ProductionBatchesView.vue` 頁面，列出所有生產批號，支援右側 Drawer 編輯原料溯源。

**Behavior**:
- 列表欄位：批號、工廠、採購品連結（matched badge / 待匹配 badge）、數量 + 單位、生產日期、批次 PCF（有值顯示，無值顯示 `—`）
- 篩選：依匹配狀態（全部 / 已匹配 / 待匹配）、依工廠
- 點擊列展開右側 Drawer（420px），顯示批號詳情 + 原料溯源清單
- Drawer 支援新增 / 編輯 / 刪除 RawMaterialOrigin

#### Scenario: 未匹配批號
- **WHEN** `buyer_product_trade_good_id = null`
- **THEN** 列表顯示「待匹配」橙色 badge，Drawer 顯示手動選擇採購品連結的下拉

#### Scenario: 新增原料溯源
- **WHEN** 使用者在 Drawer 點擊「新增溯源」並填入 material_name、origin_country
- **THEN** 建立 `raw_material_origins` 記錄，Drawer 清單即時更新

#### Scenario: GPS 座標顯示
- **WHEN** `gps_lat` 和 `gps_lng` 有值
- **THEN** 顯示格式化座標（如 `23.7°N 90.4°E`）與「在地圖查看」外部連結
