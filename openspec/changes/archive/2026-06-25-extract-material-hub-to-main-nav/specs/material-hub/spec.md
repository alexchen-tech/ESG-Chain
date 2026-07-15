## NEW Capability: material-hub

### Capability: 物料管理主功能入口

系統 SHALL 在主導覽提供「物料管理」頂層分組，整合物料主檔與物料群組兩個子功能。

#### Scenario: 路由存取

- **WHEN** 使用者以 admin / buyer / sustain / comply / analyst 角色造訪 `/materials/items`
- **THEN** 系統 SHALL 顯示物料主檔管理頁面（MaterialItemsView）

- **WHEN** 使用者以 admin / buyer / sustain / comply 角色造訪 `/materials/groups`
- **THEN** 系統 SHALL 顯示物料群組頁面（MaterialGroupsView）

- **WHEN** 使用者造訪 `/materials` 或 `/settings/material-settings`
- **THEN** 系統 SHALL redirect 至 `/materials/items`

#### Scenario: 側邊欄顯示

- **WHEN** 登入角色為 admin / buyer / sustain / comply / analyst 之一
- **THEN** 側邊欄 SHALL 顯示「物料管理」分組，位於「供應商管理」之後
- **AND** 分組展開後顯示子項目：物料主檔（`/materials/items`）、物料群組（`/materials/groups`）
- **AND** analyst 角色 SHALL 看到「物料主檔」子項，但 NOT 看到「物料群組」子項

#### Scenario: 麵包屑

- **WHEN** 顯示物料主檔管理頁面
- **THEN** 麵包屑 SHALL 顯示「物料管理 › 物料主檔」，「物料管理」為可點選連結導向 `/materials/items`

- **WHEN** 顯示物料群組頁面
- **THEN** 麵包屑 SHALL 顯示「物料管理 › 物料群組」

#### Scenario: 系統設定不再顯示物料入口

- **WHEN** 使用者進入「系統設定」分組
- **THEN** 子項目清單 SHALL NOT 包含「採購物料設定」
