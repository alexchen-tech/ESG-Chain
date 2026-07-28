## ADDED Requirements

### Requirement: 出口市場審查逐份文件結構化 finding
執行批次出口市場審查（`BatchExportReviewService::review()`）時，`checkMarketDocs()` 產生的 findings SHALL 針對每一份未達合規狀態（`missing`/`expiring_soon`/`expired`）的必備文件各自輸出一筆 finding，每筆 SHALL 包含 `doc_type`、`status`、`expires_at`、`supplier_id`、`supplier_name` 欄位；當該市場全部必備文件皆為 `valid` 狀態時，SHALL 輸出單一摘要 finding（`market_docs`，內容為「必備文件齊備」），不逐筆列出已合規的文件。

#### Scenario: 市場審查發現多份文件缺失或過期
- **WHEN** 使用者對某批次執行 EU 市場審查，該市場必備文件中有 ORIGIN_CERT 缺失、CMRT 已過期
- **THEN** findings 陣列 SHALL 包含至少兩筆獨立 finding，一筆 `doc_type=ORIGIN_CERT` 且 `status=missing`，另一筆 `doc_type=CMRT` 且 `status=expired`，兩筆皆帶各自對應的 `supplier_id`

#### Scenario: 市場審查全部必備文件齊備
- **WHEN** 使用者對某批次執行審查，該市場全部必備文件狀態皆為 `valid`
- **THEN** findings 陣列 SHALL 只包含一筆 `check=market_docs` 的摘要 finding，內容為「必備文件齊備」，不產生逐筆的文件 finding

### Requirement: 缺件 finding 提供補件導引連結
生產批號詳情頁「出口市場審查」分頁 SHALL 針對每筆帶 `doc_type` 與 `supplier_id` 的非齊備 finding 顯示可點擊連結，連結 SHALL 導向 `/compliance/suppliers/{supplier_id}` 對應供應商的合規管理頁面；當 finding 缺少 `supplier_id`（無法定位對應供應商）時，該筆 SHALL 只顯示純文字說明，不顯示連結。

#### Scenario: 使用者點擊缺件項目的補件連結
- **WHEN** 使用者在出口市場審查結果中看到一筆 `doc_type=ORIGIN_CERT` 且帶 `supplier_id` 的缺件 finding，並點擊該筆的補件連結
- **THEN** 瀏覽器 SHALL 導航至 `/compliance/suppliers/{該 finding 的 supplier_id}`

#### Scenario: 缺件 finding 無法定位供應商
- **WHEN** 某筆缺件 finding 的 `supplier_id` 為 null（例如從未有任何供應商回報過該文件）
- **THEN** 該筆 finding SHALL 只顯示文件名稱與狀態文字，不顯示可點擊連結

### Requirement: 文件問題狀態的視覺區分
出口市場審查結果卡片 SHALL 依 finding 的 `status`（`missing`/`expiring_soon`/`expired`）分別使用不同的狀態指示樣式，不得將三種狀態統一顯示為同一種樣式。

#### Scenario: 同一次審查同時出現缺失與即將到期的文件
- **WHEN** 審查結果包含一筆 `status=missing` 與一筆 `status=expiring_soon` 的 finding
- **THEN** 這兩筆 finding 在畫面上 SHALL 使用不同顏色/樣式的狀態指示（不可共用同一顆狀態指示點的顏色）
