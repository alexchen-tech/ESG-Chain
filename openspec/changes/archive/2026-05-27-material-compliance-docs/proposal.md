## Why

EUDR（歐盟零毀林法規）已於 2025 年對大企業生效，UFLPA（美國新疆強迫勞動預防法）持續擴大執法範圍。採購商面臨「清關扣貨」的實質法律風險，但目前 ESG·Chain 僅有 HS Code 欄位，無法管理對應的合規文件、追蹤效期，或提供可作為海關佐證的合規狀態摘要。此外，平台缺乏採購商自身產品清單的概念，使得合規狀態只能停留在「供應商層級」，無法回答「我的哪些產品面臨 EUDR 風險」這個對決策者真正有價值的問題。

## What Changes

- 新增**採購商產品清單 (Buyer Products)**：手動建立或 CSV 匯入，關聯供應商與物料群組，建立產品視角的合規狀態
- 新增**物料群組 (Material Groups)** 主檔，定義各類別所需合規文件類型（UFLPA聲明、EUDR地理資料、CMRT、SDS、CE DoC）
- `trade_goods` 綁定物料群組，HS Code 自動推導所需合規文件清單
- 新增**合規文件庫 (Compliance Document Hub)**：供應商透過 Portal 上傳文件，採購商管理效期與審核狀態
- 採購商看板同時呈現**產品層級**與供應商層級的合規健康度
- 供應商 Portal 新增「合規文件」頁面供上傳與查閱

## Capabilities

### New Capabilities

- `buyer-product-registry`: 採購商產品清單管理（手動建立 + CSV 匯入），含產品↔供應商+物料群組關聯
- `material-group-registry`: 物料群組主檔管理，定義 HS Code 對應規則與所需合規文件類型
- `compliance-document-hub`: 合規文件的上傳、儲存、效期追蹤、審核狀態管理
- `supplier-compliance-status`: 供應商層級與產品層級的合規健康度彙總

### Modified Capabilities

- `saq-project-ui`: 供應商 Portal 新增合規文件上傳頁面（現有 Portal 佈局需擴充）

## Impact

**後端（esgchain-api / Laravel）**
- 新增 migrations：`buyer_products`, `buyer_product_suppliers`, `material_groups`, `supplier_compliance_docs`
- `trade_goods` 加 `material_group_id` FK
- 新增 Models：`BuyerProduct`, `MaterialGroup`, `SupplierComplianceDoc`
- 新增 Controllers：`BuyerProductController`, `MaterialGroupController`, `SupplierComplianceDocController`
- 供應商 Portal API 擴充合規文件端點

**前端（esgchain-web / Vue 3）**
- 新增頁面：`BuyerProductsView.vue`（產品清單管理）
- 新增頁面：`MaterialComplianceView.vue`（合規看板，產品+供應商雙視角）
- 新增頁面：`SupplierCompliancePortalView.vue`（供應商 Portal 文件上傳）
- 側邊欄新增「物料合規」群組

**無影響**
- esgchain-ai（FastAPI）不涉及此功能
- 現有 SAQ / CAP / Report 模組不受影響
