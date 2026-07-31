## MODIFIED Requirements

### Requirement: 角色權限矩陣查詢
系統 SHALL 提供 admin 角色查詢每個角色目前擁有哪些權限的 API 與 UI，依模組分組、模組內再依 CRUD 動作（查看/新增/修改/刪除）呈現。

#### Scenario: Admin 查看角色權限矩陣
- **WHEN** admin 開啟角色管理頁面
- **THEN** 系統 SHALL 顯示各角色（buyer/sustain/comply/analyst 可調整，admin 唯讀固定）分別對應各模組 CRUD 動作的權限勾選狀態，依模組分組摺疊呈現

### Requirement: 角色權限調整
系統 SHALL 允許 admin 調整非 admin 角色的權限指派，指派單位為單一模組的單一 CRUD 動作（如 `caps.delete`），變更立即寫入角色的權限指派。

#### Scenario: Admin 調整角色的單一 CRUD 動作權限
- **WHEN** admin 對某角色的某項模組動作權限（如 `caps.delete`）勾選或取消
- **THEN** 系統 SHALL 僅更新該一動作的權限指派，不影響同模組其他動作（如 `caps.view`）的權限狀態
