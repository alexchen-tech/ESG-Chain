# bom-line-supplier-management

## MODIFIED Requirements

### Requirement: BomLineSupplier 後端 AVL 約束

系統手動新增 BomLineSupplier 時，SHALL 驗證 `supplier_id` 對應的供應商 `onboarding_stage` 必須為 `certified`；`onboarding_stage` 為其他值（`potential`、`invited`、`reviewing`）的供應商不得手動指派至 BomLine。ERP BOM 匯入流程（`source=erp_designated`）SHALL 繼續繞過此約束，允許匯入任何供應商。

#### Scenario: 手動新增供應商且已認證

- **WHEN** POST `/api/v1/buyer-products/{buyerProduct}/bom-lines/{bomLine}/suppliers` 且 supplier.onboarding_stage=certified
- **THEN** 系統 SHALL 建立 BomLineSupplier 記錄並回傳 201

#### Scenario: 手動新增供應商但未認證

- **WHEN** POST `/api/v1/buyer-products/{buyerProduct}/bom-lines/{bomLine}/suppliers` 且 supplier.onboarding_stage 非 certified（如 reviewing、invited）
- **THEN** 系統 SHALL 回傳 422，訊息說明「此供應商尚未通過認證（onboarding_stage: {stage}），無法指派至 BOM 明細」

#### Scenario: ERP 匯入繞過狀態約束

- **WHEN** BOM 由 ERP 匯入流程（`source=erp_designated`）帶入 onboarding_stage 非 certified 的供應商
- **THEN** 系統 SHALL 正常建立 BomLineSupplier 記錄，不執行 onboarding_stage 驗證

### Requirement: BomLine 行內供應商管理 UI

每條 BomLine 顯示列 SHALL 提供「供應商 N」badge，點擊展開 sub-row，顯示該 BomLine 的 BomLineSupplier 清單，並支援新增與移除。

#### Scenario: 展開供應商 sub-row

- **WHEN** 使用者點擊 BomLine 列的「供應商 N」badge
- **THEN** 系統 SHALL 在該列下方插入 sub-row，顯示現有 BomLineSupplier 清單（supplier 名稱、role primary/alternate、來源 badge）

#### Scenario: 無供應商時的 sub-row

- **WHEN** 該 BomLine 尚無任何 BomLineSupplier
- **THEN** sub-row SHALL 顯示「尚未指定供應商」空狀態，並提供「+ 新增主要供應商」按鈕

#### Scenario: 新增 BomLineSupplier（開放選取）

- **WHEN** 使用者在 sub-row 透過 Combobox 搜尋並選取 certified 供應商，選擇角色（primary/alternate），點擊確認
- **THEN** 系統 SHALL 呼叫 POST `/api/v1/buyer-products/{id}/bom-lines/{lineId}/suppliers`，成功後重新載入該 BomLine 的供應商清單

#### Scenario: 新增第二個主要供應商

- **WHEN** 使用者嘗試為已有 primary 供應商的 BomLine 再新增 primary
- **THEN** 系統 SHALL 顯示錯誤提示「每條 BomLine 只能有一個主要供應商」，阻止送出

#### Scenario: 移除 BomLineSupplier

- **WHEN** 使用者點擊某供應商記錄的「移除」按鈕
- **THEN** 系統 SHALL 呼叫 DELETE `/api/v1/buyer-products/{id}/bom-lines/{lineId}/suppliers/{supplierId}`，成功後從 sub-row 移除該列

### Requirement: BomLineSupplier hs_code / material_name 讀取順序

系統在讀取 BomLine 的 `hs_code` 與 `material_name` 供合規申報、CBAM 計算等用途時，SHALL 採用「主檔優先（effective）」順序：`materialItem?.hs_code ?? bomLine->hs_code`，`materialItem?.name ?? bomLine->material_name`。

快照欄位（`bomLine->hs_code`、`bomLine->material_name`）SHALL 僅在 `material_item_id` 為 null 時作為 fallback 使用。

#### Scenario: BomLine 有 material_item_id 時讀取 hs_code

- **WHEN** 系統讀取某 BomLine 的 hs_code（如用於合規申報）
- **THEN** SHALL 優先使用 `bomLine->materialItem->hs_code`，忽略快照欄位

#### Scenario: BomLine 無 material_item_id 時讀取 hs_code

- **WHEN** BomLine 的 `material_item_id` 為 null
- **THEN** SHALL fallback 使用 `bomLine->hs_code` 快照欄位

#### Scenario: MaterialItem hs_code 更新後 BomLineSupplier 讀取值反映更新

- **WHEN** 物料主檔的 `hs_code` 更新，且 BomLine 已關聯該 MaterialItem
- **THEN** 後續所有讀取（含 BomLineSupplier 合規輸出）SHALL 反映最新主檔值，不使用舊快照
