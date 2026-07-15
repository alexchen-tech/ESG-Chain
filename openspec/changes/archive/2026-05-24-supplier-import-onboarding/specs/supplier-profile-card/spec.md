## ADDED Requirements

### Requirement: Portal 主檔守衛
`PortalView.vue` mounted 時 SHALL 檢查 `authStore.supplier.profile_completed`；若為 false，立即 router.push('/supplier/profile')，禁止進入問卷列表。

#### Scenario: 首次登入 profile_completed=false
- **WHEN** 供應商首次登入 Portal
- **THEN** 自動跳轉至 /supplier/profile，問卷列表不可見

#### Scenario: 已完成補齊 profile_completed=true
- **WHEN** 供應商已完成主檔覆核
- **THEN** 正常顯示 Portal 問卷列表，/supplier/profile 路由導回 Portal

### Requirement: 主檔覆核卡頁面
`/supplier/profile` 路由（SupplierProfileView.vue）SHALL 顯示系統預填的 ERP 舊資料（名稱/VAT/國家碼），並要求供應商強制補充：1. 永續/安衛主管 Email（取代 ERP 業務信箱）、2. 實體製造廠區地址（文字）。送出後呼叫 PUT `/api/v1/suppliers/{id}/profile`。

#### Scenario: 送出主檔覆核卡
- **WHEN** 供應商填入主管 Email 與廠區地址後送出
- **THEN** API 更新 supplier_contacts（primary email）+ suppliers.address，設 profile_completed=true + onboarding_stage=invited，回傳 200

#### Scenario: 主管 Email 與 ERP Email 相同時警告
- **WHEN** 供應商輸入與現有 primary_email 相同的信箱
- **THEN** 顯示黃色提示「請確認此為永續/安衛主管的專屬信箱，而非採購業務信箱」，不阻擋送出

### Requirement: 補齊後自動觸發 ESG 問卷
API `PUT /api/v1/suppliers/{id}/profile` 成功後 SHALL 觸發問卷發送邏輯（若系統已有對應的未完成問卷則跳過，僅在無問卷時建立）。

#### Scenario: 無問卷時自動建立
- **WHEN** 供應商完成主檔補齊且無任何 not_started 問卷
- **THEN** 系統自動建立 SAQ（status=not_started），等待採購員後續指派範本
