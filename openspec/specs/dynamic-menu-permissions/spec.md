# dynamic-menu-permissions Specification

## Purpose
TBD - created by archiving change role-permission-management. Update Purpose after archive.
## Requirements
### Requirement: 登入回應附帶使用者權限清單
系統 SHALL 在使用者登入與查詢自身資訊時，回傳該使用者目前角色所擁有的完整權限清單。

#### Scenario: 登入回應含權限清單
- **WHEN** 使用者成功登入
- **THEN** 系統 SHALL 在回應中包含該使用者角色對應的權限字串陣列（如 `["suppliers.view", "caps.create", ...]`）

### Requirement: 側邊欄選單依權限動態顯示
系統 SHALL 讓前端側邊欄選單項目依使用者實際擁有的權限決定是否顯示，取代原本寫死在前端的角色陣列。

#### Scenario: 使用者僅看到有權限的選單項目
- **WHEN** 使用者登入後檢視側邊欄
- **THEN** 系統 SHALL 僅顯示該使用者權限清單涵蓋的選單項目與子項目

#### Scenario: 角色權限調整後選單同步反映
- **WHEN** 某角色被角色管理頁面調整了權限，該角色使用者重新登入
- **THEN** 系統 SHALL 依最新權限清單決定側邊欄顯示內容，不需修改前端程式碼

### Requirement: 路由守衛權限與角色並存過渡
系統 SHALL 支援路由設定同時存在權限與角色兩種存取控制設定，優先使用權限設定，未設定權限時才回退使用角色設定。

#### Scenario: 路由已設定權限
- **WHEN** 某路由的 meta 設定包含 `permission` 欄位
- **THEN** 系統 SHALL 僅依權限判斷是否放行，忽略該路由的 `roles` 設定

#### Scenario: 路由尚未設定權限
- **WHEN** 某路由的 meta 設定沒有 `permission` 欄位、僅有 `roles`
- **THEN** 系統 SHALL 依原本的角色陣列判斷是否放行

