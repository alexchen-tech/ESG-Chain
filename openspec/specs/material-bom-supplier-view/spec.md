## Capability: 物料來源供應商視圖（material-bom-supplier-view）

從 BOM 明細反推特定料號的供應商清單，提供「料號 → 供應商」的反向彙整視圖。

### Background

MaterialItem 與 Supplier 之間無直接 pivot 表，供應商關係需從 `ProductBomLine.material_item_id` JOIN `BomLineSupplier.role='primary'` 推算。同一供應商可能在多個 BOM 中使用同一料號，需去重彙整並附上最新碳排記錄狀態。

### API

**`GET /api/v1/material-items/{materialItem}/bom-suppliers`**

回傳格式：
```json
[
  {
    "supplier_id": "uuid",
    "supplier_name": "string",
    "bom_count": 2,
    "latest_emission": {
      "emissions_value": 0.0110,
      "source": "portal-self",
      "is_flagged": false,
      "reported_period": "2024-Q3"
    } | null
  }
]
```

### Requirement: 來源供應商查詢

#### Scenario: 料號被多個 BOM 使用且同一 primary supplier

- **GIVEN** 料號 A 被 3 個 BomLine 使用，且三個 BomLine 都指定同一 primary supplier S1
- **WHEN** 呼叫 `GET /api/v1/material-items/{id}/bom-suppliers`
- **THEN** 回傳陣列只有一筆，supplier_id=S1，bom_count=3，latest_emission 為最新一筆碳排記錄

#### Scenario: 料號有碳排記錄的供應商

- **GIVEN** 供應商 S1 已為料號 A 填報碳排（MaterialItemEmission）
- **WHEN** 呼叫 bom-suppliers API
- **THEN** 對應 entry 的 latest_emission 不為 null，包含 emissions_value、source、is_flagged、reported_period

#### Scenario: 料號無碳排記錄的供應商

- **GIVEN** 供應商 S2 有 BOM 使用料號 A，但無對應的 MaterialItemEmission
- **WHEN** 呼叫 bom-suppliers API
- **THEN** 對應 entry 的 latest_emission 為 null

#### Scenario: 料號未被任何 BOM 使用

- **WHEN** 料號 A 不存在於任何 ProductBomLine.material_item_id
- **THEN** 回傳空陣列 `[]`

### Requirement: 詳情頁「來源供應商」Tab

#### Scenario: 顯示供應商清單

- **WHEN** 使用者在詳情頁切換到「來源供應商」Tab
- **THEN** lazy load 呼叫 bom-suppliers API；顯示欄位：供應商名稱、BOM 數量（monospace）、最新碳排值、來源 badge、提報期間

#### Scenario: 有碳排 vs 無碳排的視覺區分

- **WHEN** 供應商有最新碳排記錄
- **THEN** 顯示碳排數值（4 位小數）+ 單位（kgCO₂e/[unit]）+ 來源 badge
- **WHEN** 供應商無碳排記錄
- **THEN** 顯示橘色「● 待填報」

#### Scenario: 空狀態

- **WHEN** 料號未被任何 BOM 使用（bom-suppliers 回傳空陣列）
- **THEN** 顯示「此料號尚未被指定於任何 BOM」空狀態文字
