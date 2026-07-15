## ADDED Requirements

### Requirement: 建立組織單位
系統 SHALL 允許 admin 建立組織單位，需提供名稱、代碼、類型、上層單位（L2+ 必填）、國家碼，depth 由系統根據 parent 自動計算。

#### Scenario: 建立 L1 根節點（公司）
- **WHEN** admin 提交 `type=headquarters`、`parent_id=null`
- **THEN** 系統建立 depth=1 的根節點並回傳 201

#### Scenario: 建立子節點
- **WHEN** admin 提交有效 `parent_id` 且 parent.depth < 4
- **THEN** 系統建立 depth=parent.depth+1 的節點並回傳 201

#### Scenario: 超過 4 層限制
- **WHEN** admin 提交 `parent_id` 指向 depth=4 的節點
- **THEN** 系統回傳 422，錯誤訊息「組織層級最多 4 層」

### Requirement: 讀取樹狀結構
系統 SHALL 提供 `GET /api/v1/settings/org-units/tree` 端點，回傳完整巢狀 JSON 樹，每個節點包含 `children` 陣列。

#### Scenario: 取得完整樹狀
- **WHEN** 已認證使用者呼叫 `GET /api/v1/settings/org-units/tree`
- **THEN** 回傳 root 節點及其所有子孫，格式為遞迴 children 陣列

#### Scenario: 空組織
- **WHEN** 尚未建立任何組織單位
- **THEN** 回傳空陣列 `[]`

### Requirement: 更新組織單位
系統 SHALL 允許 admin 更新名稱、代碼、國家碼、is_active；parent_id 與 type 建立後不可更改。

#### Scenario: 更新名稱
- **WHEN** admin 提交有效更新資料（不含 parent_id / type）
- **THEN** 系統更新欄位並回傳 200

#### Scenario: 嘗試更改 parent_id
- **WHEN** admin 提交包含 parent_id 的更新
- **THEN** 系統忽略 parent_id 欄位，僅更新其他允許欄位

### Requirement: 刪除組織單位
系統 SHALL 禁止刪除有子節點的組織單位；無子節點時可刪除。

#### Scenario: 刪除葉節點
- **WHEN** admin 刪除無子節點的組織單位
- **THEN** 系統刪除該節點並回傳 200

#### Scenario: 刪除有子節點的節點
- **WHEN** admin 刪除仍有子節點的組織單位
- **THEN** 系統回傳 422，錯誤訊息「請先移除所有子單位」

### Requirement: 防止循環關聯
系統 SHALL 在建立時驗證 parent_id 不在待建節點的子樹內（本次建立後不可移動，此規則主要防禦未來擴充）。

#### Scenario: 正常建立（無循環）
- **WHEN** parent_id 不在自身子樹中
- **THEN** 建立成功
