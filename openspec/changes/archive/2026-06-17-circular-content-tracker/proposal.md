## Why

ESPR（歐盟永續產品法規）與 EU 電池法規要求產品申報**再生材料含量（PCR, Post-Consumer Recycled Content）**，並在數位產品護照（DPP）中揭露。現有系統的 `MaterialItem` 缺少 `net_weight` 與 `pcr_percentage` 欄位，`espr-dpp-readiness` 已定義 DPP 就緒度評分維度，但 PCR 比率計算所需的資料層尚未建立。

此 Change 補齊循環材料追蹤的資料基礎：
1. `MaterialItem` 新增 `net_weight`（每單位淨重 kg）與 `pcr_percentage`（再生料含量 %）
2. 新增 GRS（全球再生標準）認證類型支援
3. 實作 PCR 比率計算 Service（加權平均公式）
4. 將 PCR 維度接入現有 DPP 就緒度評分

## What Changes

- `MaterialItem` 新增 `net_weight DECIMAL(10,4)` 與 `pcr_percentage DECIMAL(5,2)` 欄位
- `SupplierComplianceDoc.doc_type` enum 新增 `GRS`（全球再生標準認證）
- 新增 `PcrCalculationService`：依 BOM 計算產品層級加權 PCR 比率（$R_{PCR} = \sum(W_i \times P_i) / W_{Total}$）
- DPP 就緒度評分新增 PCR 維度：有 GRS 認證 + pcr_percentage 填寫 → 計分
- 前端 MaterialItem 詳情頁新增 PCR 欄位輸入；DPP 頁籤顯示產品 PCR 比率

## Capabilities

### New Capabilities

- `pcr-ratio-calculation`: 產品層級 PCR 比率計算（加權平均公式）

### Modified Capabilities

- `bom-management`: MaterialItem 新增 net_weight / pcr_percentage 欄位
- `espr-dpp-readiness`: DPP 就緒度評分新增 PCR 維度
