## ADDED Requirements

### Requirement: PCF 自動計算引擎

系統 SHALL 在觸發事件發生時自動重算 BuyerProduct 的 PCF，並將結果以快照形式寫入 `pcf_snapshots`。

#### Scenario: 供應商提報觸發重算

- **WHEN** 新增一筆 material_item_emissions（任何 source）
- **THEN** 系統 SHALL 非同步找出所有使用該 (material_item, supplier) 組合且 role=primary 的 BomLine，對其所屬 BuyerProduct 執行 PCF 重算並寫入新快照

#### Scenario: BomLine 加入觸發重算

- **WHEN** ProductBomLine 新增且已指定 primary BomLineSupplier
- **THEN** 系統 SHALL 觸發該 BuyerProduct 的 PCF 重算

#### Scenario: 主供應商切換觸發重算

- **WHEN** BomLineSupplier.role 從 alternate 改為 primary（或原 primary 被移除）
- **THEN** 系統 SHALL 立即觸發該 BomLine 所屬 BuyerProduct 的 PCF 重算

### Requirement: PCF 快照結構

每筆 pcf_snapshots SHALL 包含 total_pcf、functional_unit、iso14067_ready、snapshot_at，以及完整 lines JSON 明細。

#### Scenario: 快照 lines 完整記錄

- **WHEN** PCF 重算完成
- **THEN** 每筆 pcf_snapshots.lines SHALL 包含每條 BomLine 的 material_name、hs_code、qty、unit、supplier_name、emission_per_unit、emission_source、reported_period、subtotal、data_quality、is_estimated

#### Scenario: iso14067_ready 判斷

- **WHEN** 所有 BomLine 的取值 is_estimated=false 且 is_flagged=false
- **THEN** pcf_snapshots.iso14067_ready SHALL 為 true；否則 SHALL 為 false

### Requirement: BuyerProduct PCF 欄位顯示

BuyerProduct 清單 SHALL 顯示最新 pcf_snapshot 的 total_pcf 值及 iso14067_ready 狀態。

#### Scenario: 顯示 PCF 數值

- **WHEN** BuyerProduct 已有至少一筆 pcf_snapshots
- **THEN** 產品清單列 SHALL 顯示 total_pcf 值（單位：kgCO₂e/functional_unit）

#### Scenario: iso14067_ready 視覺標記

- **WHEN** iso14067_ready=true
- **THEN** PCF 數值 SHALL 以深綠色顯示；iso14067_ready=false 時以橘色顯示並標記「X/N 待實測」（X 為估算 BomLine 數）

#### Scenario: 無 PCF 資料

- **WHEN** BuyerProduct 尚無任何 pcf_snapshots
- **THEN** PCF 欄位 SHALL 顯示「— kgCO₂e」並以灰色呈現

### Requirement: PCF 快照明細 Drawer

採購商 SHALL 能查看單一 BuyerProduct 的 PCF 快照明細，包含每條 BomLine 的碳排貢獻。

#### Scenario: 開啟明細 Drawer

- **WHEN** 採購商點擊 BuyerProduct 列的 PCF 數值或「查看明細」按鈕
- **THEN** 系統 SHALL 展開 drawer 顯示 pcf_snapshots.lines 明細表：物料名稱、選用供應商、kgCO₂e/unit、數量、小計、資料來源

#### Scenario: 異常標記顯示

- **WHEN** 某 BomLine 的取值 is_flagged=true
- **THEN** 該行 SHALL 顯示 ⚠ 圖示，hover 顯示 flag_reason
