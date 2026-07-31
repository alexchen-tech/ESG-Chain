## 1. 角色範圍 Middleware

- [x] 1.1 新增 `EnsureSupplierPortalScope` middleware，供應商角色比對白名單（method + route URI pattern），非供應商角色放行
- [x] 1.2 `bootstrap/app.php` 註冊 middleware alias `supplier.scope`
- [x] 1.3 `routes/api.php` 掛載到 `auth:api` route group，對所有業務路由生效

## 2. IDOR 修復

- [x] 2.1 `SupplierProfileController::update()` 補上 ownership 檢查
- [x] 2.2 `SupplierComplianceDocController::index()` 補上 ownership 檢查
- [x] 2.3 `QuestionnaireController::show/update/submit/dispute` 補上 ownership 檢查
- [x] 2.4 `QuestionnaireService::list()` 供應商角色強制以自身 `supplier_id` 覆寫查詢條件

## 3. 部署與驗證

- [x] 3.1 部署至 `esgchain-api`/`esgchain-queue-worker`，`config:clear`/`config:cache`、`route:cache`
- [x] 3.2 供應商帳號（`esg@vietgarment.vn`）驗證：`GET suppliers`/`GET sales-products` 回 403
- [x] 3.3 供應商帳號驗證：白名單內路由（`portal/caps`、`portal/pcf-requests`、`portal/facilities`、`auth/me`）正常回 200
- [x] 3.4 供應商帳號驗證：`GET questionnaires` 僅回傳自身 `supplier_id` 的問卷
- [x] 3.5 供應商帳號驗證：`PUT suppliers/{other}/profile`、`GET suppliers/{other}/compliance-docs` 對其他供應商回 403
- [x] 3.6 供應商帳號驗證：明確排除的中心廠問卷動作（`questionnaires/send`、`questionnaires/counts`）回 403
- [x] 3.7 管理員帳號（`admin@esgchain.com`）回歸測試：`suppliers`/`sales-products`/`export-reviews`/`suppliers/{other}/compliance-docs` 皆正常 200，未被誤傷
