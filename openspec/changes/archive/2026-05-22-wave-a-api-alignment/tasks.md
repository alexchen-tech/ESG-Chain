# Tasks: Wave A — API Contract Alignment

## A1 Supplier 對齊

- [x] A1.1 Migration：suppliers 表新增 onboarding_stage 欄位，status 改為 active/inactive/suspended，遷移現有資料
- [x] A1.2 Supplier Model：更新 status enum、新增 onboarding_stage 欄位
- [x] A1.3 SupplierController@index：支援 q（模糊搜尋）、supplier_group_id、sasb_industry_id 篩選參數
- [x] A1.4 SupplierController：新增 GET /api/v1/suppliers/{id}/risk-summary（Wave B 前回傳空結構）
- [x] A1.5 更新 Seeder：supplier 測試資料使用新狀態值

## A2 Questionnaire 路徑 + 狀態機重構

- [x] A2.1 Migration：saqs 表 status enum 改為七個新值（not_started/in_progress/submitted/under_review/review_returned/completed/reviewed），新增 is_editable computed 欄位、review_started_at、reviewed_by_id
- [x] A2.2 SAQ Model 重命名為 Questionnaire（或 SAQ Model 新增別名），更新 appends 加入 is_editable
- [x] A2.3 建立 QuestionnaireController 取代 SAQController，實作完整 10 個端點
- [x] A2.4 實作 under_review 供應商 403 鎖（中間件或 Controller 前置檢查）
- [x] A2.5 QuestionnaireService：實作完整七狀態轉換邏輯（含合法性驗證）
- [x] A2.6 新增 GET /api/v1/questionnaires/counts 端點（just_submitted_count + submitted_count）
- [x] A2.7 更新 routes/api.php：新路徑，移除舊 /saqs* 路由（或保留 deprecated 轉發）
- [x] A2.8 更新 Seeder：問卷測試資料使用新狀態值

## A3 Auth Refresh Token

- [x] A3.1 更新 AuthService@login：回應加入 refresh_token
- [x] A3.2 實作 Redis refresh token 儲存（jti → user_id，TTL 7 天）
- [x] A3.3 更新 AuthService@refresh：接受 request body 的 refresh_token，驗證 Redis jti，實作 Rotation
- [x] A3.4 更新 AuthService@logout：撤銷 Redis 中的 refresh token jti
