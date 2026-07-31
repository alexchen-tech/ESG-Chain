# user-management Specification

## Purpose
TBD - created by archiving change user-role-management. Update Purpose after archive.
## Requirements
### Requirement: 使用者建立
系統 SHALL 提供 admin 角色建立新的中心廠內部使用者帳號（角色限定 admin/buyer/sustain/comply/analyst）。

#### Scenario: Admin 建立新使用者
- **WHEN** admin 填寫姓名、email、角色並送出建立請求
- **THEN** 系統 SHALL 建立對應 `User` 記錄並指派指定角色，`is_active` 預設為 `true`

#### Scenario: Email 重複
- **WHEN** admin 建立使用者時 email 已被其他帳號使用
- **THEN** 系統 SHALL 拒絕建立並回傳驗證錯誤，不建立重複帳號

### Requirement: 使用者清單查詢
系統 SHALL 提供 admin 角色分頁查詢使用者清單，支援依角色與啟用狀態篩選。

#### Scenario: 查詢使用者清單
- **WHEN** admin 呼叫使用者清單 API
- **THEN** 系統 SHALL 回傳 server-side 分頁結果（每頁 20 筆），含姓名、email、角色、啟用狀態

### Requirement: 角色指派與變更
系統 SHALL 允許 admin 變更既有使用者的角色，並留下稽核歷程。

#### Scenario: 變更使用者角色
- **WHEN** admin 對某使用者送出新的角色清單
- **THEN** 系統 SHALL 更新該使用者的角色指派，並在 `user_role_histories` 記錄變更前後的角色快照、操作者、時間

### Requirement: 帳號停用與啟用
系統 SHALL 允許 admin 停用或重新啟用使用者帳號，停用後該帳號無法登入。

#### Scenario: 停用帳號
- **WHEN** admin 對某使用者執行停用操作
- **THEN** 系統 SHALL 將 `is_active` 設為 `false`，並在 `user_status_histories` 記錄本次變更

#### Scenario: 停用時提示進行中工作
- **WHEN** admin 停用一個名下有進行中 CAP 或 SAQ 覆核工作的使用者
- **THEN** 系統 SHALL 在回應中包含警告訊息列出進行中工作的數量，但仍完成停用動作（不阻擋）

#### Scenario: 停用帳號無法登入
- **WHEN** 已被停用（`is_active=false`）的帳號嘗試登入
- **THEN** 系統 SHALL 拒絕登入，錯誤訊息與帳號密碼錯誤時一致，不揭露帳號是否存在或是否被停用

#### Scenario: 重新啟用帳號
- **WHEN** admin 對已停用的使用者執行啟用操作
- **THEN** 系統 SHALL 將 `is_active` 設為 `true` 並記錄稽核歷程，該帳號恢復可登入

### Requirement: Admin 代重設密碼
系統 SHALL 允許 admin 為指定使用者重設密碼，並回傳新密碼供 admin 轉告使用者。

#### Scenario: Admin 重設使用者密碼
- **WHEN** admin 對某使用者執行密碼重設操作
- **THEN** 系統 SHALL 產生一組隨機密碼、雜湊後存入該使用者帳號，並在 API 回應中一次性回傳明碼新密碼

### Requirement: 使用者管理前端入口
系統 SHALL 在中心廠前端提供使用者管理頁面，僅 admin 角色可存取。

#### Scenario: Admin 存取使用者管理頁面
- **WHEN** admin 角色開啟側邊欄「使用者管理」選單項目
- **THEN** 系統 SHALL 顯示使用者清單頁面，支援建立、角色編輯、停用/啟用、密碼重設等操作入口

#### Scenario: 非 admin 角色無法存取
- **WHEN** 非 admin 角色的使用者嘗試存取使用者管理頁面或對應 API
- **THEN** 系統 SHALL 拒絕存取（前端不顯示選單項目，後端 API 回傳 403）

