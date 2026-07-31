## MODIFIED Requirements

### Requirement: 權限目錄定義
系統 SHALL 維護一份完整的權限目錄，每筆權限以「模組.動作」格式命名，動作限定為 `view`（查看）、`create`（新增）、`update`（修改）、`delete`（刪除）四種之一（或因角色白名單不同而需要的更細分類，如 `saqs.view` 與 `saqs.view-detail`），涵蓋現有路由實際檢查過的範圍，不再使用單一 `module.manage` 統包整個模組全部動作。

#### Scenario: 權限目錄涵蓋現有路由
- **WHEN** 系統初始化權限目錄
- **THEN** 目錄 SHALL 包含既有路由所需的每一個模組與 CRUD 動作組合

#### Scenario: 同模組同動作合併為一個權限字串
- **WHEN** 同一模組下多條路由屬於同一 CRUD 動作、且角色白名單完全相同
- **THEN** 系統 SHALL 將這些路由合併為一個權限字串

#### Scenario: 同模組同動作但角色白名單不同時不強行合併
- **WHEN** 同一模組下的路由屬於同一 CRUD 動作，但不同路由的角色白名單不同
- **THEN** 系統 SHALL 保留為不同的權限字串，不因動作相同而掩蓋角色白名單的差異

### Requirement: 角色權限初始化與現況一致
系統 SHALL 在部署此功能當下，依現有路由的角色檢查行為（而非簡化版設計文件）初始化每個角色的權限指派，確保切換前後行為一致；權限目錄粒度由模組級拆分為 CRUD 動作級後，亦須保證每條路由對各角色的允許/拒絕結果與拆分前完全一致。

#### Scenario: Seed 後行為不變
- **WHEN** 權限機制部署完成、`role_has_permissions` seed 完成
- **THEN** 每個角色能存取的功能範圍 SHALL 與部署前完全相同，不因這次切換而擴大或縮小任何角色的既有存取範圍

#### Scenario: 拆分後路由存取行為不變
- **WHEN** 權限目錄由 `module.manage` 拆分為 CRUD 四動作並重新 seed 完成
- **THEN** 系統 SHALL 保證每條路由對各角色的允許/拒絕結果與拆分前完全一致
