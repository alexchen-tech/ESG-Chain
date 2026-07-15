## 1. 資料庫 Migration

- [x] 1.1 新增 migration：`material_items` 表加 `net_weight DECIMAL(10,4) NULL`（kg/unit）與 `pcr_percentage DECIMAL(5,2) NULL`（0.00–100.00）
- [x] 1.2 新增 migration：`supplier_compliance_docs` 的 `doc_type` 加入 `GRS`（欄位為 varchar(50)，無需修改 enum）
- [x] 1.3 新增 migration：`pcf_snapshots` 表加 `pcr_ratio DECIMAL(5,4) NULL` 與 `pcr_incomplete_lines TINYINT UNSIGNED NULL`
- [x] 1.4 docker cp + `php artisan migrate`，確認欄位正確

## 2. 後端 Model 更新

- [x] 2.1 更新 `MaterialItem` model：`$fillable` 補 `net_weight`、`pcr_percentage`；`$casts` 補對應型別
- [x] 2.2 更新 `PcfSnapshot` model：`$fillable` 補 `pcr_ratio`、`pcr_incomplete_lines`

## 3. PCR 計算 Service

- [x] 3.1 新增 `PcrCalculationService::calcForProduct(BuyerProduct): array`：實作加權平均公式，回傳 `{ pcr_ratio: float|null, incomplete_lines: int }`
- [x] 3.2 更新 `PcfCalculationService::snapshot(BuyerProduct)`：呼叫 `PcrCalculationService::calcForProduct()`，將 `pcr_ratio`、`pcr_incomplete_lines` 存入 PcfSnapshot

## 4. DPP 就緒度評分更新

- [x] 4.1 找到現有 DPP 就緒度評分 Service（SupplierComplianceStatusService）
- [x] 4.2 新增 `pcr` 維度評分邏輯：計算 primary supplier 有 GRS（status=valid）且 pcr_percentage > 0 的 BomLine 比率，≥ 80% 則滿分
- [x] 4.3 更新 DPP 就緒度 API response 包含 `pcr` 維度結果

## 5. ERP 同步欄位保護

- [x] 5.1 確認 `ErpSyncService` 的 ERP-owned 欄位清單不含 `net_weight`、`pcr_percentage`（這兩個是 ESG-Chain 擁有，ERP sync 不覆寫）

## 6. 後端 API 更新

- [x] 6.1 更新 `MaterialItemController`（或對應 Service）的 `update()` 允許傳入 `net_weight`、`pcr_percentage`
- [x] 6.2 `GET /api/v1/material-items/{id}` response 包含這兩個新欄位
- [x] 6.3 新增端點 `GET /api/v1/buyer-products/{product}/pcr-ratio`：即時計算（不從快照讀）並回傳結果
- [x] 6.4 docker cp + `docker restart esgchain-api` 驗證

## 7. 前端

- [x] 7.1 更新 `MaterialItem` TypeScript interface（`api/modules/compliance.ts`），補 `net_weight: number | null`、`pcr_percentage: number | null`
- [x] 7.2 在 MaterialItem 詳情頁（展開列或編輯 modal）新增 `net_weight`（kg/unit，小數 4 位）與 `pcr_percentage`（%，0–100）輸入欄位
- [x] 7.3 更新 `PcfSnapshot` interface 補 `pcr_ratio: number | null`、`pcr_incomplete_lines: number`
- [x] 7.4 在 PCF 快照明細 Drawer（BuyerProductsView）底部新增「PCR 比率」行：顯示 `pcr_ratio` 百分比，若 `pcr_incomplete_lines > 0` 顯示警告說明
- [x] 7.5 在 DPP 就緒度頁籤新增 `pcr` 維度顯示，含進度條與「待補齊」提示
- [x] 7.6 `SupplierComplianceDetailView` 與 `SupplierCompliancePortalView` 的 doc_type 下拉加入 `GRS` 選項

## 8. 驗證

- [x] 8.1 新增含 net_weight 與 pcr_percentage 的 MaterialItem，執行 PCF snapshot，確認 pcr_ratio 計算正確
- [x] 8.2 部分 BomLine 缺 net_weight，確認 pcr_incomplete_lines 正確計數
- [x] 8.3 DPP 就緒度評分 pcr 維度正確反映 GRS 文件 + pcr_percentage 狀態（已驗證 pcr 欄位回傳正確）
- [x] 8.4 前端 PCF Drawer 顯示 PCR 比率，MaterialItem 編輯可儲存新欄位
