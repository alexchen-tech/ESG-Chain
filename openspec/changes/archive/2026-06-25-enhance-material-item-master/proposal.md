## Why

物料主檔（MaterialItem）目前承擔三條業務鏈的基礎資料角色（BOM → PCF、化學合規、ESPR/DPP），但有三個既有缺口讓使用者在實際操作中需要跨多個頁面才能拼出完整的物料圖像：

**缺口 A — 供應商維度不可見**
物料碳排以 `(material_item_id, supplier_id)` 儲存，代表同一物料可有多個供應商各自的碳排記錄。但物料主檔展開列目前只呈現碳排值列表，看不到「這個物料透過哪些 BOM 被使用、各 BOM 指定的 primary supplier 是誰、對應的碳排記錄有沒有填」。使用者必須去銷售產品 BOM 頁逐一核查，無法在物料主檔直接判斷缺口在哪個供應商。（無直接的 material ↔ supplier pivot，需從 BomLineSupplier JOIN ProductBomLine WHERE material_item_id 彙整）

**缺口 B — 化學合規掃描結果不顯示**
`ChemicalComplianceScanJob` 掃描後會寫入 `ChemicalComplianceAlert`（alert_level: info/warning/critical，regulated_list: reach_svhc/rohs），但這些結果在物料主檔 UI 完全不可見。使用者加完 CAS No. 後必須去另一個頁面查看是否觸發警告，違反「在哪裡輸入就在哪裡看結果」的操作直覺。

**缺口 C — 可回收材料僅有單一欄位**
目前只有 `pcr_percentage`（循環材料比例），ESPR/DPP 就緒計算依賴此值，但：
- 無法區分 PCR（消費後回收）、PIR（製程廢料回收）、Bio-based（生物基）三種可回收來源——這三種在 ESPR 和 GRS 認證有不同要求
- 無可回收性評級（這個物料本身易回收嗎？）
- 物料主檔行列沒有 PCR 狀態指示，必須進 DPP 視圖才能判斷是否符合 80% 門檻

## What Changes

### A. 物料來源供應商面板

**後端**
- 新增 `GET /api/v1/material-items/{id}/bom-suppliers`，彙整從 `ProductBomLine.material_item_id` 關聯的所有 `BomLineSupplier`（role = primary），回傳供應商清單 + 對應 BOM 數量 + 最新碳排記錄（若存在）

**前端**
- `MaterialItemsView.vue` 碳排展開列上方，新增「來源供應商」小節（折疊列表），呈現：供應商名稱、涉及 BOM 數、最新碳排值（有→綠點 + 數值；無→橘點 + 「待填報」）

### B. 化學合規掃描結果內嵌

**前端**
- 化學成分展開列底部，加入掃描結果彙總區：若有 `ChemicalComplianceAlert` 則以 info/warning/critical 色階顯示（critical = 紅、warning = 橘、info = 藍），點擊展開可見受管制清單名稱與限制說明
- 掃描中狀態（Job dispatched 後）顯示 loading 指示；無警告時顯示「✓ 未偵測到受管制物質」

**後端**
- 新增 `GET /api/v1/material-items/{id}/chemical-alerts`，回傳該物料當前所有 `ChemicalComplianceAlert`（含 Chemical.substance_name、restriction_notes）

### C. 可回收材料細項

**後端（Migration）**
在 `material_items` 表新增欄位：
- `pir_percentage` decimal(5,2) nullable — 製程廢料回收比例（Pre-consumer / Industrial）
- `bio_based_percentage` decimal(5,2) nullable — 生物基材料比例
- `recyclability_rating` enum('high','medium','low','not_rated') nullable — 本材料自身的可回收性評級，由物料管理員手動設定

`pcr_percentage` 保留不重命名（ESPR/DPP 計算邏輯已依賴此欄位）。

**前端**
- `MaterialItemsView.vue` 行列新增 PCR 狀態徽章（`pcr_percentage > 0` → 顯示百分比綠底 badge；`= 0 / null` → 不顯示）
- 新增第三個展開 section「可回收材料」，呈現：
  - PCR / PIR / Bio-based 各自百分比（輸入 + 顯示）
  - 總回收成分合計（PCR + PIR，自動計算）
  - 可回收性評級下拉選單（high / medium / low / not_rated）
  - 關聯 DPP 狀態提示（此物料在哪些銷售產品的 DPP 中被計入循環材料）
- 編輯 modal 移除 `pcr_percentage` / `net_weight` 兩個孤立的欄位，改為引導使用者透過「可回收材料」展開 section 填寫（modal 保留 net_weight 因為計算需要）

## Capabilities

### New Capabilities
- `material-bom-supplier-view`（新）：物料 → BOM → 供應商的反向彙整視圖與 API
- `material-chemical-alert-inline`（新）：化學合規掃描結果內嵌於物料主檔 UI

### Modified Capabilities
- `material-item-master`：新增可回收材料三欄位（pir_percentage、bio_based_percentage、recyclability_rating）、展開 section、行列 PCR badge
- `espr-dpp-readiness`：可回收成分計算可選擇性擴展納入 PIR（目前只用 PCR），留 open question

## Impact

- **後端**：一個 migration（三欄位）、兩個新 API endpoint（bom-suppliers、chemical-alerts）、對應 Controller/Service
- **前端**：`MaterialItemsView.vue` 新增兩個 API 呼叫、一個新展開 section、行列 badge、化學成分區合規結果顯示
- **無需異動**：現有 ESPR/DPP 計算邏輯（pcr_percentage 欄位名稱不變）、ChemicalComplianceScanJob（只是把它的輸出暴露出來）、BomLineSupplier 結構
