## MODIFIED Requirements

### Requirement: ERP 同步欄位歸屬保護
系統 SHALL 將每個可 ERP 同步的 entity 欄位分為「ERP 擁有」與「ESG-Chain 擁有」兩類，upsert 時只更新 ERP 擁有欄位，ESG-Chain 擁有欄位永不被同步覆蓋。所有以 `SalesProduct`/`ProductBomLine` 為目標的同步邏輯 SHALL 對應正確的現行資料模型，不可引用已被移除的 `BuyerProduct`/`buyer_products`。

ERP 擁有欄位（可覆蓋）：
- Supplier：`code`、`name`、`country_code`、`hs_code`、`quantity`、`unit_price`、`supplier_code`
- MaterialItem：`item_code`、`name`、`hs_code`、`unit`
- ProductBomLine：`material_name`、`hs_code`、`quantity`、`unit`、`unit_price`、`currency`

ESG-Chain 擁有欄位（永不覆蓋）：
- Supplier：`onboarding_stage`、`saq_score`、`risk_level`、`emission_factor`、`applicable_regulations`、`notes`
- MaterialItem：`net_weight`、`pcr_percentage`
- ProductBomLine：`notes`、`material_group_source = 'manual'` 的行（`material_group_id` 與 `material_group_source` 皆不可覆蓋）

#### Scenario: ERP 同步時 ESG 標注不被覆蓋
- **WHEN** ERP 同步傳入某供應商更新的 name，但 ESG-Chain 已手動設定 onboarding_stage = certified
- **THEN** 系統 SHALL 更新 name，onboarding_stage 保持 certified 不變

#### Scenario: ERP 同步時 manual 物料群組標注不被覆蓋
- **WHEN** ERP BOM 匯入同一 erp_line_id，但該行的 material_group_source = manual（ESG 團隊手動設定）
- **THEN** 系統 SHALL 更新 quantity / hs_code 等 ERP 欄位，material_group 保持 ESG 手動設定值

#### Scenario: MaterialItem ERP 同步不覆蓋 ESG 擁有欄位
- **WHEN** ERP 同步傳入某物料更新的 name/hs_code/unit，但 ESG-Chain 已設定 net_weight 與 pcr_percentage
- **THEN** 系統 SHALL 只更新 ERP 擁有欄位，net_weight 與 pcr_percentage 保持原值不變

#### Scenario: BOM line 同步正確指向 SalesProduct
- **WHEN** 透過 `POST /api/v1/erp/webhook/bom-lines` 推送 BOM 行資料
- **THEN** 系統 SHALL 依 `product_code` 查找對應的 `SalesProduct`，並對 `ProductBomLine`（`sales_product_id`）執行 upsert，不可查詢或寫入已不存在的 `buyer_products` 表
