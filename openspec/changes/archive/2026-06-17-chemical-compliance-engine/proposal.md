## Why

ESG-Chain 目前的 BOM 合規機制是**文件驅動**：MaterialGroup 定義需要哪些合規文件類型（EUDR_DDS, UFLPA, CMRT, SDS…），供應商上傳對應文件，買方核對。

但新版系統規格要求**組成驅動**的合規機制：以 CAS No.（化學物質識別碼）為基礎，對照 REACH SVHC、RoHS 管制清單、EU 電池法規等化學物質資料庫，自動判斷物料是否含有管制物質，並決定需申報的法規。

這兩條路徑並行存在：文件驅動適用 EUDR/UFLPA 等供應鏈盡職調查法規，化學組成驅動適用 REACH/RoHS/ESPR 等物質管制法規。此 Change 建立後者的基礎架構。

## What Changes

- 新增 `MaterialItemChemical`（`material_item_chemicals` 表）：記錄物料的化學物質組成（CAS No. × 重量百分比 × 申報點）
- 新增化學物質主檔 `Chemical`（`chemicals` 表）：由 esgchain-ai 從 ECHA/RoHS 等外部資料庫同步，含 CAS No.、管制清單、限制用途等
- 新增合規掃描 Service：對 BOM 中每條物料的化學組成執行管制物質交叉比對，產出 `ChemicalComplianceAlert`
- 新增 `ErpAdapterInterface::pushComplianceTag()` 與 `lockMaterial()`：合規掃描結果回寫 ERP 系統
- 新增買方端化學物質管理介面（物料詳情頁）與合規警示列表

## Capabilities

### New Capabilities

- `material-chemical-composition`: MaterialItem 化學組成管理（CAS No.、百分比、申報點）
- `chemical-substance-registry`: 化學物質主檔（ECHA/RoHS 同步，管制清單標記）
- `chemical-compliance-scan`: BOM 化學組成交叉比對管制清單，產出合規警示
- `erp-compliance-writeback`: 合規標記回寫 ERP（pushComplianceTag / lockMaterial）

### Modified Capabilities

- `bom-management`: MaterialItem 詳情頁新增「化學組成」tab
