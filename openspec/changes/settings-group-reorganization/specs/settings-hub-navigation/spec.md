## ADDED Requirements

### Requirement: 系統設定分組依內容收斂為 5 個 hub 頁
系統 SHALL 將側邊欄「系統設定」群組下的功能，依內部 tab 內容重新分組為 5 個子項目：一般設定、存取控制、分類與計分管理、市場與合規規則、客戶主檔，取代原本 6 個各自獨立、內容分散的側邊欄項目。

#### Scenario: 存取控制 hub 頁整併使用者與角色管理
- **WHEN** admin 點擊側邊欄「存取控制」
- **THEN** 系統 SHALL 顯示一個 hub 頁，內含「使用者管理」「角色管理」兩個 tab，功能與原本各自獨立頁面完全相同

#### Scenario: 市場與合規規則 hub 頁整併三個相關設定
- **WHEN** admin 點擊側邊欄「市場與合規規則」
- **THEN** 系統 SHALL 顯示一個 hub 頁，內含「目標市場」「市場合規規則」「國家風險評等」三個 tab，「國家風險評等」SHALL 為頁內真正的 tab 切換，不再是連結跳轉至獨立路由

#### Scenario: 分類與計分管理 hub 頁新增 SASB 產業分類與碳價設定
- **WHEN** admin 點擊側邊欄「分類與計分管理」
- **THEN** 系統 SHALL 顯示 6 個 tab：維度預設加權、設定框架加權、問卷題目標籤庫、SASB 必調題目、SASB 產業分類、碳價設定

#### Scenario: 碳價設定首次可從 UI 存取
- **WHEN** admin 切換到「分類與計分管理」的「碳價設定」tab
- **THEN** 系統 SHALL 顯示碳價設定的完整功能，此前該功能沒有任何路由或選單可以到達

### Requirement: 舊網址相容導向
系統 SHALL 保留使用者管理、角色管理、國家風險評等、市場合規規則的舊網址，訪問時自動導向對應 hub 頁並選中正確的 tab。

#### Scenario: 舊書籤網址自動導向
- **WHEN** 使用者訪問 `/settings/users`、`/settings/roles`、`/settings/country-risk` 或 `/settings/market-rules`
- **THEN** 系統 SHALL 自動導向對應的新 hub 頁路由，並選中該功能原本對應的 tab，不需使用者手動再切換

### Requirement: hub 頁 tab 切換不影響既有元件的權限檢查
系統 SHALL 確保被整併進 hub 頁的既有元件（使用者管理、角色管理、市場合規規則、國家風險評等、碳價設定、SASB 產業分類），其原本依賴的權限檢查邏輯在新外殼下仍正確生效。

#### Scenario: 非 admin 角色無法存取 hub 頁
- **WHEN** 非 admin 角色的使用者嘗試訪問任一系統設定 hub 頁路由
- **THEN** 系統 SHALL 依既有路由守衛邏輯拒絕存取，行為與整併前一致
