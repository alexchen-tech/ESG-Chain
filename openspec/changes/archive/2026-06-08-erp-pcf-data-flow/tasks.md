## 1. ERP AVL 供應商清單匯入

- [x] 1.1 新增 `POST /api/v1/suppliers/import-avl` route（RBAC: admin/buyer/sustain）
- [x] 1.2 建立 `AvlImportRequest` Form Request（驗證 file 必填）
- [x] 1.3 建立 `SupplierAvlImportService`：解析 CSV、冪等 upsert Supplier（依 supplier_code）、建立 BomLineSupplier（source=erp_designated）
- [x] 1.4 建立 `SupplierImportController@importAvl`，呼叫 Service，回傳 `{ created_suppliers, updated_suppliers, created_bom_links, warnings }`
- [x] 1.5 新增 AVL 匯入種子資料（1 筆範例供測試）

## 2. ERP BOM 匯入擴充供應商配對

- [x] 2.1 擴充現有 BomLine CSV Parser，新增辨識 `primary_supplier_code` / `alternate_supplier_code` 欄位
- [x] 2.2 匯入邏輯新增：依 supplier_code 查找 Supplier，找到則建立 BomLineSupplier，未找到加入 warnings
- [x] 2.3 回傳摘要新增 `linked_suppliers: N` 欄位
- [x] 2.4 更新 BOM 匯入範例 CSV 模板（加入新欄位說明）

## 3. PCF 請求資料模型（後端）

- [x] 3.1 建立 Migration：`pcf_requests` 表（id UUID、supplier_id、period_start、period_end、due_date、status、saq_round_id nullable、created_by、timestamps）
- [x] 3.2 建立 Migration：`pcf_request_lines` 表（id UUID、pcf_request_id、bom_line_id、material_name、hs_code、submitted_at nullable、status、timestamps）
- [x] 3.3 建立 `PcfRequest` Model（HasUuids、belongsTo Supplier、hasMany PcfRequestLine）
- [x] 3.4 建立 `PcfRequestLine` Model（HasUuids、belongsTo PcfRequest、belongsTo BomLine，快照 material_name/hs_code）

## 4. PCF 請求 API（後端）

- [x] 4.1 建立 `PcfRequestService`：`batchCreate()`（防重複發送邏輯）、`list()`（含 progress 計算）、`updateOverdue()`
- [x] 4.2 建立 `PcfRequestController`：`index()`、`batchCreate()` actions
- [x] 4.3 新增 routes：`GET /api/v1/pcf-requests`、`POST /api/v1/pcf-requests/batch`
- [x] 4.4 建立 Artisan 指令 `pcf:update-overdue`（更新逾期狀態），註冊到 Console Kernel 排程（每日）

## 5. Portal PCF 申報 API（後端）

- [x] 5.1 建立 `GET /api/v1/portal/pcf-requests` endpoint（只回傳當前登入供應商的請求）
- [x] 5.2 建立 `PUT /api/v1/portal/pcf-requests/{id}/lines/{lineId}` endpoint（填入 declared_value、quantity_unit，更新 line status）
- [x] 5.3 建立 `POST /api/v1/portal/pcf-requests/{id}/submit` endpoint（全部 lines submitted 才允許，更新 request status）
- [x] 5.4 Portal endpoint 觸發 esgchain-ai：呼叫 AI 服務 `POST /ai/v1/pcf-records`，帶入 bom_line_id + declared_value + quantity_unit（非同步，Guzzle async 或 Job）

## 6. esgchain-ai PCFRecord 強化

- [x] 6.1 建立 Alembic Migration：`pcf_records` 表新增 `pcf_request_line_id`（String nullable）、`bom_line_id`（String nullable）、`data_quality`（Enum: primary/secondary/estimated，預設 primary）、`quantity_unit`（String nullable）
- [x] 6.2 更新 `PCFRecord` SQLAlchemy Model 加入新欄位
- [x] 6.3 新增 `POST /ai/v1/pcf-records` endpoint（接受業務側呼叫，建立或更新 PCFRecord）
- [x] 6.4 更新 Pydantic Schema：`PcfRecordCreateRequest` 加入新欄位

## 7. 供應商 BOM 需求視圖更新

- [x] 7.1 `SupplierBomRequirementService::getRequirements()` 查詢新增 LEFT JOIN pcf_request_lines，帶出最新 pcf_status
- [x] 7.2 Response 結構加入 `pcf_status` 欄位（none/pending/submitted/verified）
- [x] 7.3 更新 `BomRequirementLine` TypeScript interface（`esgchain-web/src/api/modules/compliance.ts`）加入 `pcf_status`

## 8. 前端：PCF 請求管理頁（採購商）

- [x] 8.1 新增 `PcfRequestsView.vue`（`views/compliance/PcfRequestsView.vue`），RBAC: admin/buyer/sustain
- [x] 8.2 新增 router 路由：`/compliance/pcf-requests`，加入 Sidebar 導覽項目
- [x] 8.3 實作列表頁：篩選（status/supplier/年份）、表格（供應商、週期、截止日、進度條、狀態 badge）
- [x] 8.4 實作批次發送表單：選供應商 → 選 BomLine（多選）→ 設定週期與截止日 → 確認送出
- [x] 8.5 新增 `pcfRequestApi` 模組（`api/modules/pcf.ts`）：`list()`、`batchCreate()` API 呼叫
- [x] 8.6 Docker sync：`docker cp` + `docker exec esgchain-web touch` 觸發 HMR

## 9. 前端：Portal 碳排申報頁（供應商）

- [x] 9.1 新增 `PortalPcfView.vue`（`views/portal/PortalPcfView.vue`），使用 SupplierTopbar 無 Sidebar 佈局
- [x] 9.2 新增 router 路由：`/supplier/portal/pcf`，加入 Portal 導覽
- [x] 9.3 實作請求列表：顯示待申報請求（截止日警示 ≤ 14 天橙色）、已完成請求摺疊區塊
- [x] 9.4 實作逐料填寫展開介面：每條 BomLine 顯示物料名稱/HS Code/輸入欄位（數值+單位），儲存後更新進度條
- [x] 9.5 實作 SAQ 整合入口：有 saq_round_id 時顯示「填寫企業排放問卷」跳轉按鈕
- [x] 9.6 實作整體提交按鈕：所有物料填完後啟用，呼叫 submit endpoint
- [x] 9.7 更新 Portal API 模組，新增 `portalPcfApi`：`list()`、`updateLine()`、`submit()`
- [x] 9.8 Docker sync 前端

## 10. 種子資料與測試驗證

- [x] 10.1 新增 PCF Request 種子資料：對 supplier1@twspinning.com.tw 建立 2 筆 pending 請求，各含 2-3 條 BomLine
- [x] 10.2 驗證採購商流程：GET /api/v1/pcf-requests 回傳 2 筆，進度 0/2、0/1
- [x] 10.3 驗證供應商流程：GET /api/v1/portal/pcf-requests 回傳 2 筆待申報，含 lines 資料
