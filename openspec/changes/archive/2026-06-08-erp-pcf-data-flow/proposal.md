## Why

品牌採購商需要追蹤供應鏈 Scope 3 碳排放，但目前缺乏從 ERP/PLM/MES 匯入供應商與 BOM 資料、向供應商發送 PCF 碳排請求、以及在 Portal 收集逐料碳足跡數據的完整流程。現有 BomLine 與 Supplier 資料各自獨立，缺少 AVL（合格廠商清單）自動配對機制與碳排申報週期管理。

## What Changes

- **ERP 匯入強化**：AVL 供應商清單 CSV 匯入時，自動建立 `BomLineSupplier` 關聯；BOM 清單 CSV 匯入時，依 `primary_supplier_code` 自動配對已存在的供應商
- **PCF 請求管理**：新增 `pcf_requests` / `pcf_request_lines` 資料模型，支援批次對供應商發送 PCF 碳排請求（每條 BomLine 每兩年一次），並連結 SAQ 問卷（三範疇排放揭露）
- **供應商 Portal 碳排申報**：Portal 新增「碳排申報」頁面，供應商可瀏覽待填寫的 PCF 請求，逐條 BomLine 填入 kgCO₂e/unit 數值後提交
- **計算後端強化**：esgchain-ai PCFRecord 補強 `bom_line_id` / `pcf_request_line_id` 欄位，支援 Scope 3 貢獻度彙總計算

## Capabilities

### New Capabilities

- `erp-avl-import`: ERP 合格廠商清單（AVL）CSV 匯入，自動建立 Supplier + BomLineSupplier 關聯
- `pcf-request-management`: PCF 碳排請求管理，後台批次發送、追蹤各供應商 BomLine 的申報狀態與週期
- `portal-pcf-submission`: 供應商 Portal 碳排申報介面，逐料填入 PCF 數值並與 SAQ 三範疇問卷整合

### Modified Capabilities

- `erp-bom-import`: BOM 匯入時新增 `primary_supplier_code` / `alternate_supplier_code` 欄位，匯入後自動配對 BomLineSupplier
- `supplier-bom-requirement-view`: 供應商頁面的供應材料清單，補充顯示 PCF 申報狀態（待申報 / 已提交 / 已驗證）

## Impact

- **esgchain-api**：新增 Migration（`pcf_requests`, `pcf_request_lines`）、Model、Service、Controller、Route
- **esgchain-api**：擴充 `erp-bom-import` 的 CSV Parser（新增供應商配對欄位）
- **esgchain-ai**：補強 `pcf_records` 表（新增 `pcf_request_line_id`, `bom_line_id`, `data_quality`, `quantity_unit` 欄位）
- **esgchain-web**：新增 PCF 請求管理頁（buyer 側）+ Portal 碳排申報頁（supplier 側）
- **依賴**：`pcf-request-management` 依賴現有 `bom-line-supplier-avl`；`portal-pcf-submission` 依賴現有 SAQ 問卷框架
