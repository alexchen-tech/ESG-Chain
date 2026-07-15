## Context

目前系統：Laravel 12 + MySQL 8.4，User model 無組織歸屬，SettingsView.vue 有三個 Tab（問卷範本、供應商分組、SASB）。組織架構從未實作，CLAUDE.md 資料模型中 `OrganizationUnit` 已列為 MySQL 側表但無 migration。

## Goals / Non-Goals

**Goals:**
- `organization_units` 表：4 層樹狀自我關聯，type enum 5 種
- User 掛 `organization_unit_id`（nullable，不影響現有資料）
- REST API：CRUD + 樹狀回傳（含子節點遞迴）
- 系統設定頁新增「組織架構」Tab，置於第一位
- Seeder 建立預設根節點

**Non-Goals:**
- 資料可見性隔離（層級 2/3）—— 本次只做層級 1 純標記
- 供應商 / SAQ Project 掛 OU
- 角色權限依 OU 細分

## Decisions

**D1：樹狀結構用 Adjacency List（parent_id）而非 Nested Set**
- Adjacency List 寫入簡單、直覺，Laravel Eloquent 關聯直接支援
- 4 層固定深度，不需要 Nested Set 的複雜遞迴查詢優化
- 讀取樹狀時後端一次撈全部再 PHP 端組裝，資料量小（OU 通常 < 100 筆）

**D2：深度用 `depth` 欄位明確記錄（1–4），不靠 parent_id 計算**
- 前端建立時直接驗證 depth ≤ 4
- 後端建立時計算 `parent.depth + 1`，root 為 depth=1

**D3：type enum 固定 5 種**
```
headquarters   公司（L1，唯一 root）
subsidiary     子公司（L2）
business_unit  事業部（L2）
department     部門（L3）
branch         分支/辦公室（L4）
```
不做自由文字，保持 UI 一致性與未來報告分類用途。

**D4：前端樹狀渲染用遞迴 component（OrgUnitNode.vue）**
- 後端 `/api/v1/settings/org-units/tree` 回傳巢狀 JSON
- 前端單一遞迴 component，不用第三方 tree library

**D5：User.organization_unit_id nullable，不強制**
- 現有帳號不受影響
- Admin 可在使用者管理中指派 OU（本次不做 UI，欄位先備）

## Risks / Trade-offs

- **循環關聯風險**（parent_id 指向自己的子孫）→ 後端 Service 建立/更新時驗證新 parent 不在自己的子樹內
- **刪除時子節點孤立**→ 有子節點時禁止刪除，前端顯示「請先移除子單位」
- **depth 欄位與實際層級不一致**→ 移動節點時重新計算整棵子樹 depth（本次暫不支援移動，節點建立後 parent 不可更改）

## Migration Plan

1. 執行 `create_organization_units_table` migration
2. 執行 `add_organization_unit_id_to_users_table` migration
3. 執行 Seeder 建立預設根節點（公司名稱可在設定頁修改）
4. 無資料遷移需求，users.organization_unit_id 預設 null

## Open Questions

- 預設根節點公司名稱是否要從 `.env` 讀取？（目前先 hardcode 為 `ESGChain`，之後設定頁可編輯）
