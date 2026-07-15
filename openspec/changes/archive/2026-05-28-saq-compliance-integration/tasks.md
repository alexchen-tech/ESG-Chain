## 1. 資料庫 Migration

- [x] 1.1 建立 migration：`saq_questions` 加 `compliance_domains` JSON nullable 欄位
- [x] 1.2 部署 migration 至容器（docker cp + migrate）

## 2. 後端 — SAQQuestion

- [x] 2.1 `SAQQuestion` model `$fillable` 加入 `compliance_domains`
- [x] 2.2 `SAQQuestion` model `$casts` 加入 `'compliance_domains' => 'array'`
- [x] 2.3 題庫 API（`GET /api/v1/question-bank`）加入 `?compliance_domain=` 篩選支援（JSON contains 查詢）
- [x] 2.4 題庫建立 / 更新 Request 加入 `compliance_domains` 驗證（`array`、每個元素 `in:UFLPA,EUDR,CMRT,SDS,CE`）
- [x] 2.5 從題庫複製題目至範本的邏輯確認 `compliance_domains` 已在 fillable，無需額外處理
- [x] 2.6 複製更新後的 model / controller / request 至容器

## 3. 後端 — SupplierGroup 推導

- [x] 3.1 `SupplierGroup` model 加 `inferredMaterialGroups()` 方法（查詢 suppliers → tradeGoods → materialGroup，unique by id）
- [x] 3.2 方法內加入 Laravel Cache（key: `supplier_group_{id}_inferred`，TTL: 600 秒）
- [x] 3.3 `TradeGoodObserver`（或新建）在 `saved` / `deleted` 時清除對應供應商群組快取
- [x] 3.4 `SupplierGroupController` 加 `inferredMaterialGroups(SupplierGroup $group)` action
- [x] 3.5 `routes/api.php` 加路由 `GET /supplier-groups/{supplierGroup}/inferred-material-groups`
- [x] 3.6 複製相關檔案至容器

## 4. 前端 — 題目庫管理頁

- [x] 4.1 `QuestionBankView.vue` 的新增 / 編輯題目 Modal 加入「合規範疇」chip 多選欄位（UFLPA / EUDR / CMRT / SDS / CE）
- [x] 4.2 題目列表行加入 `compliance_domains` chip 顯示（有值才顯示）
- [x] 4.3 篩選列加入「合規範疇」下拉篩選，呼叫 `?compliance_domain=` API
- [x] 4.4 `compliance.ts` API module（或 `settings.ts`）加入 compliance_domain 篩選參數傳遞
- [x] 4.5 複製更新後的 Vue 檔案至容器

## 5. 前端 — BankImportModal

- [x] 5.1 `TemplateDetailView.vue` 或 `BankImportModal` 元件：題目列加入 `compliance_domains` chip 顯示
- [x] 5.2 加入「僅顯示合規相關題目」toggle（依現有篩選模式實作）
- [x] 5.3 複製更新後的 Vue 檔案至容器

## 6. 前端 — 問卷專案建立 Modal

- [x] 6.1 `SaqProjectsView.vue` 建立 Modal 加入「供應商群組」下拉（選填，選項來自 `GET /api/v1/supplier-groups`）
- [x] 6.2 選擇群組後呼叫 `GET /api/v1/supplier-groups/{id}/inferred-material-groups`，取得 `compliance_domains`
- [x] 6.3 推導結果不為空時，Modal 顯示「此群組涉及合規範疇：X、Y」提示文字
- [x] 6.4 推導結果為空時，顯示「此群組尚無物料記錄，無合規推薦」
- [x] 6.5 選定範本後的題目預覽列表，對 `compliance_domains` 有交集的題目加上「⚠ 合規相關」badge
- [x] 6.6 題目預覽列表加入「僅顯示合規相關」toggle
- [x] 6.7 複製更新後的 Vue 檔案至容器

## 7. 驗收測試

- [x] 7.1 在題目庫管理頁新增一道題，設定 `compliance_domains: ["CMRT"]`，確認儲存與顯示正確
- [x] 7.2 呼叫 `GET /api/v1/question-bank?compliance_domain=CMRT`，確認只回傳已標記的題目
- [x] 7.3 呼叫電子元件供應商群組的推導 API，確認回傳 `compliance_domains: ["CMRT"]`
- [x] 7.4 建立問卷專案時選擇含電子五金 TradeGoods 的群組，確認 CMRT badge 出現在對應題目上
- [x] 7.5 確認未選擇群組時，問卷建立流程與原有行為一致（無 badge）
