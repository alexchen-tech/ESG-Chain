## ADDED Requirements

### Requirement: 使用者個人權限覆寫（多授予）
系統 SHALL 允許 admin 在使用者角色權限之外，對單一使用者額外授予特定權限，該使用者的有效權限 SHALL 為「角色權限」與「個人直接授予權限」的聯集。

#### Scenario: 個人覆寫授予角色沒有的權限
- **WHEN** admin 對某位 buyer 角色使用者額外授予 `caps.delete`（buyer 角色本身沒有此權限）
- **THEN** 該使用者呼叫需要 `caps.delete` 的 API SHALL 被允許，即使其他 buyer 角色使用者仍被拒絕

#### Scenario: 個人覆寫不支援收回角色已有的權限
- **WHEN** admin 嘗試針對某使用者「取消」其角色本身就擁有的某個權限
- **THEN** 系統 SHALL NOT 提供此操作；該使用者的角色權限維持完整，如需變更需調整角色本身的權限指派或更換角色

### Requirement: admin 使用者不可被個人覆寫
系統 SHALL 禁止對持有 admin 角色的使用者進行任何個人權限覆寫操作，因其已固定擁有全部權限。

#### Scenario: 嘗試對 admin 使用者新增個人覆寫
- **WHEN** 對某位 admin 角色使用者呼叫個人權限覆寫 API
- **THEN** 系統 SHALL 拒絕該操作並回傳錯誤

### Requirement: 個人權限覆寫稽核歷程
系統 SHALL 記錄每一筆個人權限覆寫的授予者、對象使用者、權限字串與時間，供後續稽核查詢。

#### Scenario: 授予個人權限後留下稽核紀錄
- **WHEN** admin 對某使用者授予一項個人權限覆寫
- **THEN** 系統 SHALL 建立一筆稽核紀錄，包含操作者、對象使用者、被授予的權限字串、時間戳

### Requirement: 個人權限覆寫管理 API 與 UI
系統 SHALL 提供查詢與新增/移除單一使用者個人權限覆寫的 API 與對應 UI 頁籤。

#### Scenario: 查詢使用者的有效權限來源
- **WHEN** admin 開啟某使用者的「個人權限覆寫」頁籤
- **THEN** 系統 SHALL 分別標示該使用者「透過角色取得」與「個人額外授予」的權限，兩者顯示方式需可區分

#### Scenario: 移除先前授予的個人覆寫
- **WHEN** admin 對某使用者先前授予的個人覆寫權限取消勾選
- **THEN** 系統 SHALL 移除該筆個人覆寫，該使用者之後的有效權限恢復為僅依角色權限判斷
