## MODIFIED Requirements

### Requirement: 權限目錄粒度為模組加動作
系統 SHALL 以 `模組.動作` 格式定義權限字串，動作 SHALL 限定為 `view`（查看）、`create`（新增）、`update`（修改）、`delete`（刪除）四種之一（或在同一動作下因角色白名單不同而需要的更細分類，如 `saqs.view` 與 `saqs.view-detail`），不再使用單一 `module.manage` 統包整個模組全部動作。

#### Scenario: 同模組同動作合併為一個權限字串
- **WHEN** 同一模組下多條路由屬於同一 CRUD 動作、且角色白名單完全相同
- **THEN** 系統 SHALL 將這些路由合併為一個權限字串（如多條 CAP 查詢類路由合併為 `caps.view`）

#### Scenario: 同模組同動作但角色白名單不同時不強行合併
- **WHEN** 同一模組下的路由屬於同一 CRUD 動作，但不同路由的角色白名單不同
- **THEN** 系統 SHALL 保留為不同的權限字串，不因動作相同而掩蓋角色白名單的差異

### Requirement: 權限拆分行為需與拆分前現況一致
系統 SHALL 確保權限目錄從模組級拆分為 CRUD 動作級後，每一條原有路由的實際可存取角色範圍與拆分前完全相同，不因拆分而放寬或收緊任何路由的存取權限。

#### Scenario: 拆分後路由存取行為不變
- **WHEN** 权限目錄由 `module.manage` 拆分為 CRUD 四動作並重新 seed 完成
- **THEN** 系統 SHALL 保證每條路由對各角色的允許/拒絕結果與拆分前完全一致
