## ADDED Requirements

### Requirement: 角色權限矩陣查詢
系統 SHALL 提供 admin 角色查詢每個角色目前擁有哪些權限的 API 與 UI，依模組分組呈現。

#### Scenario: Admin 查看角色權限矩陣
- **WHEN** admin 開啟角色管理頁面
- **THEN** 系統 SHALL 顯示七種角色（admin/buyer/sustain/comply/analyst/supplier/sup_esg）分別對應的權限勾選狀態，依模組分組摺疊呈現

### Requirement: 角色權限調整
系統 SHALL 允許 admin 調整非 admin 角色的權限指派（勾選/取消特定模組動作），變更立即寫入 `role_has_permissions`。

#### Scenario: Admin 調整角色權限
- **WHEN** admin 對某角色的某項權限（如 `caps.delete`）勾選或取消
- **THEN** 系統 SHALL 更新該角色的權限指派，下次該角色使用者登入或換發 token 時反映新權限

#### Scenario: 變更不即時踢出現有 session
- **WHEN** 某角色的權限被調整，但該角色底下有使用者目前已登入（持有有效 token）
- **THEN** 系統 SHALL NOT 強制使該使用者現有 token 失效，新權限於下次登入/換發時才生效

### Requirement: Admin 角色權限不可透過 UI 調整
系統 SHALL 禁止透過角色管理頁面調整 admin 角色本身的權限，避免管理員權限被意外削弱。

#### Scenario: 嘗試調整 admin 角色權限
- **WHEN** 使用者嘗試透過角色管理 API 調整 admin 角色的權限指派
- **THEN** 系統 SHALL 拒絕該操作並回傳錯誤，admin 角色權限維持系統固定值

### Requirement: 泛用權限檢查機制
系統 SHALL 提供以權限字串（而非角色字串陣列）為基礎的路由存取控制機制，取代原本寫死在路由上的角色清單。

#### Scenario: 權限不足時拒絕存取
- **WHEN** 使用者的角色未被指派某路由所需的權限
- **THEN** 系統 SHALL 回傳 403，不論其角色名稱為何

#### Scenario: 權限充足時允許存取
- **WHEN** 使用者的角色已被指派某路由所需的權限
- **THEN** 系統 SHALL 允許該請求繼續處理
