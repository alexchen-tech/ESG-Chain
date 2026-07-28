## ADDED Requirements

### Requirement: 供應商角色 API 路由白名單
系統 SHALL 針對 `supplier`/`sup_esg` 角色的已認證請求，限制其只能存取明確列入白名單的路由（依 HTTP method 與路由定義的 URI pattern 精確比對），非白名單路由 SHALL 一律回傳 403，不受前端路由設計影響。非供應商角色不受此白名單限制。

#### Scenario: 供應商呼叫中心廠專用路由
- **WHEN** 供應商角色的已認證使用者呼叫不在白名單內的路由（如 `GET suppliers` 供應商列表、`GET sales-products`）
- **THEN** 系統 SHALL 回傳 403，不得回傳該路由原本的資料

#### Scenario: 供應商呼叫 Portal 允許路由
- **WHEN** 供應商角色呼叫白名單內的路由（如 `GET portal/caps`）
- **THEN** 系統 SHALL 正常處理請求，不受此白名單機制影響既有行為

#### Scenario: 非供應商角色不受白名單限制
- **WHEN** 非 `supplier`/`sup_esg` 角色（如 admin/buyer/sustain/comply/analyst）呼叫任何既有已授權路由
- **THEN** 系統 SHALL 維持原有存取行為，不因新增此機制而被拒絕

### Requirement: 供應商資料範圍限制（Ownership 檢查）
供應商 Portal 允許存取的路由中，凡涉及特定供應商資料的操作（供應商主檔、合規文件、問卷），系統 SHALL 驗證該資料的 `supplier_id` 與請求者 `auth()->user()->supplier_id` 一致，不一致 SHALL 回傳 403，不得因為路由在白名單內就略過資料範圍檢查。

#### Scenario: 供應商嘗試竄改其他供應商主檔
- **WHEN** 供應商角色呼叫 `PUT suppliers/{supplier}/profile`，其中 `{supplier}` 不是自己的 `supplier_id`
- **THEN** 系統 SHALL 回傳 403，不得更新該筆資料

#### Scenario: 供應商嘗試讀取其他供應商合規文件
- **WHEN** 供應商角色呼叫 `GET suppliers/{supplier}/compliance-docs`，其中 `{supplier}` 不是自己的 `supplier_id`
- **THEN** 系統 SHALL 回傳 403，不得回傳文件清單

#### Scenario: 供應商嘗試讀取或竄改其他供應商問卷
- **WHEN** 供應商角色呼叫 `GET/PUT questionnaires/{questionnaire}` 或其 `submit`/`dispute` 動作，該問卷不屬於自己的 `supplier_id`
- **THEN** 系統 SHALL 回傳 403，不得回傳或修改問卷內容

#### Scenario: 問卷清單查詢強制以自身供應商為範圍
- **WHEN** 供應商角色呼叫 `GET questionnaires`，不論請求是否帶入 `supplier_id` 查詢參數
- **THEN** 系統 SHALL 一律以請求者自身的 `supplier_id` 為範圍回傳結果，不得因請求省略或竄改該參數而回傳其他供應商的問卷
