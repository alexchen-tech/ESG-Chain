## ADDED Requirements

### Requirement: BomLine 行內供應商管理 UI
每條 BomLine 顯示列 SHALL 提供「供應商 N」badge，點擊展開 sub-row，顯示該 BomLine 的 BomLineSupplier 清單，並支援新增與移除。

#### Scenario: 展開供應商 sub-row
- **WHEN** 使用者點擊 BomLine 列的「供應商 N」badge
- **THEN** 系統 SHALL 在該列下方插入 sub-row，顯示現有 BomLineSupplier 清單（supplier 名稱、role primary/alternate、來源 badge）

#### Scenario: 無供應商時的 sub-row
- **WHEN** 該 BomLine 尚無任何 BomLineSupplier
- **THEN** sub-row SHALL 顯示「尚未指定供應商」空狀態，並提供「+ 新增主要供應商」按鈕

#### Scenario: 新增 BomLineSupplier
- **WHEN** 使用者在 sub-row 選擇供應商（從 MDM 下拉選單）並選擇角色（primary/alternate），點擊確認
- **THEN** 系統 SHALL 呼叫 POST /api/v1/products/{id}/bom-lines/{lineId}/suppliers，成功後重新載入該 BomLine 的供應商清單

#### Scenario: 新增第二個主要供應商
- **WHEN** 使用者嘗試為已有 primary 供應商的 BomLine 再新增 primary
- **THEN** 系統 SHALL 顯示錯誤提示「每條 BomLine 只能有一個主要供應商」，阻止送出

#### Scenario: 移除 BomLineSupplier
- **WHEN** 使用者點擊某供應商記錄的「移除」按鈕
- **THEN** 系統 SHALL 呼叫 DELETE /api/v1/products/{id}/bom-lines/{lineId}/suppliers/{supplierId}，成功後從 sub-row 移除該列

### Requirement: BomLineSupplier 後端 CRUD 端點
系統 SHALL 提供 BomLineSupplier 的 `store` 與 `destroy` 端點，掛在 BomLine nested resource 路由下。

#### Scenario: 成功新增 BomLineSupplier
- **WHEN** POST /api/v1/products/{buyerProduct}/bom-lines/{bomLine}/suppliers 帶有有效的 supplier_id 與 role
- **THEN** 系統 SHALL 回傳 201 及建立的 BomLineSupplier 記錄（含 supplier 關聯）

#### Scenario: 成功移除 BomLineSupplier
- **WHEN** DELETE /api/v1/products/{buyerProduct}/bom-lines/{bomLine}/suppliers/{bomLineSupplier}
- **THEN** 系統 SHALL 刪除該記錄並回傳 200 success
