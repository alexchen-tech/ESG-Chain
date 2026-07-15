## ADDED Requirements

### Requirement: User 掛載 organization_unit_id
User model SHALL 新增 `organization_unit_id` 欄位（nullable UUID FK → organization_units），現有帳號預設 null，不影響登入與 RBAC。

#### Scenario: 現有帳號遷移
- **WHEN** 執行 migration `add_organization_unit_id_to_users_table`
- **THEN** 所有現有 users 的 organization_unit_id 為 null，登入正常

#### Scenario: JWT payload 包含 ou_id
- **WHEN** 使用者登入取得 JWT
- **THEN** token payload 包含 `ou_id`（null 或 UUID 字串）
