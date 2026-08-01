## Why

集團旗下多個子公司/分公司/部門各自負責管理其對應供應商的永續風險調查，但目前 `Supplier` 完全沒有跟組織單位（`OrganizationUnit`）掛勾，任何內部使用者（buyer/sustain/comply/analyst）都能看到全公司所有供應商，無法依組織界線分權。既有 `OrganizationUnit` 已是完整的樹狀結構（`parent_id`/`depth`），`User` 也已有 `organization_unit_id` 並塞進 JWT 的 `ouId` claim，但這個欄位目前完全沒有任何程式碼讀取、是死欄位。這次要把這條已經半成品的地基接上，讓供應商資料的可視範圍能依組織單位分權，而不是像現在只有角色一個維度。

## What Changes

- `Supplier` 新增 `organization_unit_id`（nullable FK → `organization_units`），一個供應商只歸屬單一子公司/部門（1 對多，非多對多）
- 此欄位為 ESG-Chain 自有營運欄位，由永續團隊人工指派/後續補歸戶，**不**隨 ERP 同步覆蓋，供應商建立當下也不要求必填
- 新增組織單位可視子樹查詢機制：以 `User.organization_unit_id` 為根，用 `WITH RECURSIVE` 撈出自己＋所有子孫單位 id，作為與角色權限正交的第二層資料範圍過濾
- `SupplierController@index` 查詢加上組織單位範圍過濾：一般使用者只看得到自己單位＋子孫單位歸屬的供應商；`organization_unit_id` 為 null 的使用者（如 admin）跳過此過濾、看全部
- 未指派 `organization_unit_id` 的供應商（含既有全部舊資料）對所有人可見，不受範圍過濾排除，並在畫面上明顯標示「未指派單位」
- 供應商清單頁與詳情頁新增組織單位的顯示/篩選/指派功能，指派動作留稽核紀錄（比照既有 `SupplierStatusHistory` 模式）
- **BREAKING（資料可見性）**：一旦供應商被指派組織單位，非該單位（含子孫）使用者將看不到該供應商，這是刻意的行為變更，不是相容性問題

## Capabilities

### New Capabilities
- `supplier-organization-unit-scoping`：供應商依組織單位樹狀範圍的資料可視性授權機制，涵蓋欄位、查詢範圍計算、清單過濾、未歸戶供應商的預設可見策略、指派與稽核

## Impact

- 後端：`Supplier` model 與 migration、新 `OrganizationUnitScopeService`（可視子樹查詢）、`SupplierController@index`、`SupplierController`/`SupplierService` 新增指派組織單位的 action 與稽核寫入、新 `SupplierOrganizationUnitHistory` model + migration
- 前端：`SuppliersView.vue`（清單頁欄位顯示、篩選、未指派單位標示）、`SupplierDetailView.vue`（指派組織單位的操作介面）
- 不涉及 `EnsureSupplierPortalScope`、不涉及商品合規管理相關實體（TradeGoods/SalesProduct/BOM/生產批號/出口審查）、不涉及 SAQ/CAP 的範圍過濾——這些明確排除於本次範圍
