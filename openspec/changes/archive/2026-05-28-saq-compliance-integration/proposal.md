## Why

問卷題目與物料合規文件目前完全解耦：採購商發問卷時無法知道哪些題目與 CMRT / EUDR / UFLPA 等合規要求相關，供應商群組的物料屬性也未被利用。結果是問卷內容與供應商實際的合規風險脫節，採購商需要人工比對才能決定發哪些題目。

本次變更建立三條整合連線：
1. **題目 → 合規範疇**（admin 在題庫打標 `compliance_domains`）
2. **供應商群組 → 物料類型**（系統自動從 TradeGoods 推導）
3. **問卷建立 → 合規推薦**（建立問卷時，依群組物料類型推薦對應的合規題目）

## What Changes

- **`SAQQuestion` 加 `compliance_domains` 欄位**（JSON array，值域：`UFLPA / EUDR / CMRT / SDS / CE`），在題目庫編輯 UI 由 admin 手動打標
- **`SupplierGroup` 新增 `inferredMaterialGroups()` 方法**，從群組內供應商的 TradeGoods 自動推導關聯的 MaterialGroup（讀取時計算，不存 DB）
- **問卷專案建立 Modal 加「供應商群組」選擇**，選定群組後系統推薦含對應 `compliance_domains` 的題目，高亮顯示於題目選擇介面
- **題目庫管理頁**加入 `compliance_domains` 多選標籤欄位（UFLPA / EUDR / CMRT / SDS / CE chip 選擇器）
- **題目庫篩選**支援依 `compliance_domains` 篩選，方便採購商快速找出合規相關題目

## Capabilities

### New Capabilities

- `supplier-group-material-inference`: SupplierGroup 自動從成員供應商的 TradeGoods 推導相關 MaterialGroup，提供 `inferred_material_groups` 與對應 `required_doc_types`
- `question-compliance-domain-tagging`: SAQQuestion 在題庫管理 UI 可打標 `compliance_domains`，並作為問卷建立時的推薦過濾依據

### Modified Capabilities

- `saq-project-ui`: 問卷專案建立 Modal 加入供應商群組選擇與合規題目推薦功能
- `questionnaire-template-management`: 題目庫管理頁加入 `compliance_domains` 欄位的顯示與編輯

## Impact

- `esgchain-api`：`saq_questions` 資料表 migration（加 `compliance_domains` JSON 欄位）、`SAQQuestion` model、`SupplierGroup` model 加推導方法、`SupplierGroupController` 加 `inferredMaterialGroups` API endpoint
- `esgchain-web`：題目庫管理頁（`QuestionBankView.vue`）、問卷專案建立 Modal（`SaqProjectsView.vue`）、問卷範本題目編輯頁（`TemplateDetailView.vue`）
- 無 breaking change，`compliance_domains` 預設為空陣列，現有題目不受影響
