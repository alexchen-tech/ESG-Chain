## Context

品牌採購商需要追蹤供應鏈 Scope 3 碳排放。目前 ESG·Chain 已有 BomLine、Supplier、BomLineSupplier 資料模型，以及供應商 Portal 入口，但缺乏：
1. ERP AVL（合格廠商清單）批次匯入並自動配對 BomLineSupplier 的機制
2. 向供應商發送碳排申報請求（PCF Request）的業務流程
3. 供應商在 Portal 填寫逐料碳足跡數值的介面
4. esgchain-ai 側 PCFRecord 與業務側 BomLine 的關聯

三服務架構：esgchain-api（Laravel / MySQL，業務流程）、esgchain-ai（FastAPI / PostgreSQL，計算引擎）、esgchain-web（Vue 3，前端）。

## Goals / Non-Goals

**Goals:**
- AVL CSV 匯入時自動建立 Supplier + BomLineSupplier 關聯
- BOM 匯入欄位擴充，支援 `primary_supplier_code` / `alternate_supplier_code`
- 新增 `pcf_requests` / `pcf_request_lines` 模型，支援批次發送與狀態追蹤
- PCF Request 連結 SAQ 問卷（三範疇揭露）+ Portal 逐料填寫
- esgchain-ai PCFRecord 補強關聯欄位

**Non-Goals:**
- 即時 ERP API 串接（本次只做 CSV 批次匯入）
- 碳排數值的自動計算（供應商直接填 kgCO₂e，不做 activity data → emission factor 換算）
- Scope 3 下游排放（Category 11+）
- PCF 驗證（第三方查核）流程

## Decisions

### D1：PCF Request 資料模型放 MySQL（esgchain-api）
PCF Request 是業務流程（發送、狀態流轉、截止日管理），與 Supplier、SAQ 等業務模型緊密關聯，放 MySQL 與 Laravel 統一管理。PCFRecord（實際計算數值）放 PostgreSQL（esgchain-ai），由 AI 服務負責彙總計算。

兩側透過 `bom_line_id` + `pcf_request_line_id` 關聯，業務側只存申報狀態，計算側只存數值。

### D2：AVL 匯入獨立 endpoint，BOM 匯入擴充欄位
AVL（供應商清單）與 BOM（物料清單）是不同的 ERP 資料來源，有各自的匯入週期。設計為：
- `POST /api/v1/suppliers/import-avl`：新增 AVL 匯入專用 endpoint，建立 Supplier + BomLineSupplier
- 擴充現有 `POST /api/v1/buyer-products/{id}/bom-lines/import`：新增 `primary_supplier_code` / `alternate_supplier_code` 欄位，匯入後查找現有 Supplier 自動配對

### D3：PCF Request 每 2 年週期，由採購商手動發送
自動定時發送複雜度高且需要完整排程機制，第一版設計為採購商在後台手動批次勾選供應商 + BomLine 範圍後發送。系統記錄 `period_start` / `period_end`，防止重複發送（同一 `supplier_id + bom_line_id + period` 只能有一筆 pending/submitted）。

### D4：Portal 碳排申報整合現有 Portal 佈局
不新增獨立 route，而是在現有 `/supplier/portal` 下新增「碳排申報」頁面（`/supplier/portal/pcf`），使用相同的 SupplierTopbar + 無 Sidebar 佈局。

### D5：SAQ 三範疇排放作為 PCF Request 的「企業級揭露」
PCF Request 連結一個 SAQ Round，問卷涵蓋 Scope 1/2/3 整體排放（企業層級）。逐料的 PCF 數值透過 Portal 另行填寫，兩者分開管理但同屬一個 PCF Request。

## Risks / Trade-offs

- **[風險] BomLine 尚未配對供應商**：BOM 匯入時若 `primary_supplier_code` 找不到對應 Supplier，BomLine 照建但 BomLineSupplier 跳過，需在 response warnings 提醒。→ 緩解：匯入結果摘要明確列出未配對的 supplier_code 清單。

- **[風險] PCF Request 與 SAQ 的耦合**：若供應商已提交 SAQ 但未填 PCF 逐料數值（或反之），狀態不一致。→ 緩解：`pcf_requests.status` 由逐料填寫狀態驅動（`bom_line_count` vs `submitted_line_count`），SAQ 連結為非必填（`saq_round_id nullable`）。

- **[Trade-off] 直接填 kgCO₂e vs Activity Data**：直接填數值快速上手但資料品質難以核實；Activity Data 需要排放因子資料庫支援。第一版採直接填值，未來可升級加入 `data_quality` 欄位（`primary/secondary/estimated`）識別可信度。

## Migration Plan

1. Laravel Migration：新增 `pcf_requests` + `pcf_request_lines` 表
2. esgchain-ai Alembic Migration：`pcf_records` 表新增欄位
3. 部署順序：先 DB migration → 後部署 API → 後部署 Web
4. 無破壞性變更，現有 BomLine / Supplier / BomLineSupplier 資料不受影響

## Open Questions

- SAQ 問卷模板是否需要新建一個「PCF 三範疇揭露」專用模板？還是複用現有 SAQ 模板系統？
- PCF Request 逾期後是否自動寄送提醒通知（Notification）？第一版先不做，手動追蹤。
