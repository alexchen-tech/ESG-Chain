## ADDED Requirements

### Requirement: Portal 物料碳排提報清單

供應商 Portal SHALL 提供「物料碳排提報」頁籤，列出該供應商被 BomLine 指定需提報的物料清單。

#### Scenario: 顯示需提報物料清單

- **WHEN** 供應商登入 Portal 並開啟物料碳排提報頁籤
- **THEN** 系統 SHALL 列出所有 BomLineSupplier.supplier_id = 當前供應商 的不重複 MaterialItem，標示已提報 / 待提報 / AI 估算中

#### Scenario: 顯示 AI 估算值（Option C）

- **WHEN** 某物料有 source=ai-estimated 的碳排記錄但供應商尚未自填
- **THEN** UI SHALL 顯示估算值（灰底虛線框 + 🤖 標記），並顯示「建議提報實際數據以提升 PCF 準確性」提示

#### Scenario: 已提報物料顯示歷史值

- **WHEN** 供應商已提報某物料碳排（is_estimated=false）
- **THEN** UI SHALL 顯示最新提報值、reported_period，並提供「更新數值」按鈕

### Requirement: 供應商提報物料碳排表單

供應商 SHALL 能透過 Portal 填報物料的 emissions_value，並選擇 reported_period 與 calculation_method。

#### Scenario: 填報表單欄位

- **WHEN** 供應商點擊「填報碳排」或「更新數值」
- **THEN** 系統 SHALL 顯示 modal，包含：emissions_value（必填，數字，0.001~1000 kgCO₂e/unit）、reported_period（下拉，YYYY-Q1~Q4）、calculation_method（LCA / 聲明值 / 其他）、calculation_note（選填文字）

#### Scenario: 提報後立即計入 PCF

- **WHEN** 供應商送出提報表單且 validation 通過
- **THEN** 系統 SHALL 新增 material_item_emissions 記錄（source=portal-self），並非同步觸發相關 BuyerProduct 的 PCF 重算，無需買方確認

#### Scenario: 異常值自動標記

- **WHEN** 提報的 emissions_value 超出合理範圍（>500 kgCO₂e/unit）
- **THEN** 系統 SHALL 接受提報但自動設 is_flagged=true，並通知採購商審查

### Requirement: 主動提報（無 BomLine 指定）

供應商 SHALL 能主動提報任意物料的碳排值，即使尚未被任何 BomLine 指定為供應商。

#### Scenario: 主動提報入口

- **WHEN** 供應商在提報頁籤點擊「主動填報其他物料」
- **THEN** 系統 SHALL 顯示物料搜尋欄（by 料號或名稱），供選擇後開啟相同提報表單

#### Scenario: 主動提報資料顯示於買方碳排資料庫

- **WHEN** 供應商主動提報某物料碳排
- **THEN** 採購商在物料主檔的碳排資料庫分頁 SHALL 看到該筆記錄，標記「未指定於 BOM」
