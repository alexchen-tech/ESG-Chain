## 1. 資料庫 Migration

- [x] 1.1 新增 migration：`buyer_product_trade_goods` 加 `erp_product_code VARCHAR(100) NULL COMMENT 'ERP料號，供Webhook匹配用'`
- [x] 1.2 執行 migration 並確認欄位存在

## 2. 後端 Model 與 Controller

- [x] 2.1 `BuyerProductTradeGood` Model `$fillable` 加入 `erp_product_code`
- [x] 2.2 `BuyerProductExportLinkController::store()` 驗證規則加 `erp_product_code: nullable|string|max:100`
- [x] 2.3 `BuyerProductExportLinkController::index()` 確認回傳值含 `erp_product_code`（Eloquent 自動含，確認即可）

## 3. 前端

- [x] 3.1 `api/modules/compliance.ts` `ExportLink` interface 加 `erp_product_code: string | null`
- [x] 3.2 `BuyerProductsView.vue` Modal 在備註欄前新增「ERP 料號（選填）」input，v-model 綁定 `exportLinkForm.erp_product_code`
- [x] 3.3 `exportLinkForm` data 初始值加 `erp_product_code: ''`
- [x] 3.4 `submitExportLink()` payload 加入 `erp_product_code: this.exportLinkForm.erp_product_code || null`
- [x] 3.5 出口連結列表顯示：若 `link.erp_product_code` 有值，在料名下方顯示 `ERP: xxx` 小字

## 4. Docker 同步

- [x] 4.1 同步後端檔案至 Docker 並 `docker restart esgchain-api`
- [x] 4.2 執行 `docker exec esgchain-api php artisan migrate --force`
- [x] 4.3 同步前端 Vue 檔案並 touch 觸發 HMR

## 5. 驗收

- [x] 5.1 新增連結時填入 ERP 料號，確認儲存成功並列表顯示
- [x] 5.2 新增連結時不填 ERP 料號，確認連結正常建立（舊行為不變）
- [x] 5.3 API `GET /api/v1/buyer-products/{id}/export-links` 回傳值含 `erp_product_code` 欄位
