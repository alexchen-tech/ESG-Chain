## ADDED Requirements

### Requirement: 出口品目錄管理

系統 SHALL 允許中心廠 / 貿易商（buyer / comply 角色）建立、編輯、刪除自有出口品項。每個品項須包含名稱、HS Code、單位、單價、幣別，不再強制關聯單一供應商。

#### Scenario: 建立出口品項

- **WHEN** 使用者填寫名稱與 HS Code 並送出
- **THEN** 系統建立 TradeGood，並自動判定 is_cbam_applicable / cbam_category / material_group_id

#### Scenario: CBAM 自動判定

- **WHEN** 使用者輸入 HS Code（如 7208.10）
- **THEN** 系統根據前2碼自動設定 is_cbam_applicable=true, cbam_category='steel'

#### Scenario: 刪除有上游供應商關聯的品項

- **WHEN** 使用者刪除已有 trade_good_suppliers 記錄的品項
- **THEN** 系統 cascade 刪除關聯記錄，並回傳 200

### Requirement: 上游供應商 BOM 關聯管理

系統 SHALL 允許為每個 TradeGood 新增多個上游供應商及其提供的物料群組，形成該出口品的 BOM 結構。

#### Scenario: 新增上游供應商

- **WHEN** 使用者對某個 TradeGood 新增上游供應商並指定物料群組
- **THEN** 建立 trade_good_suppliers 記錄，可選填 notes

#### Scenario: 同一供應商提供多個物料群組

- **WHEN** 使用者對同一 (trade_good_id, supplier_id) 組合新增第二筆不同 material_group_id
- **THEN** 允許建立（不限制唯一性），兩筆記錄並存

#### Scenario: 移除上游供應商

- **WHEN** 使用者移除某筆 trade_good_suppliers 記錄
- **THEN** 刪除該記錄，不影響 trade_goods 本身

### Requirement: 法規暴露視圖

系統 SHALL 在 TradeGood 清單提供每個品項的 CBAM 與 EUDR 法規狀態，讓中心廠一眼看出合規風險。

#### Scenario: CBAM 狀態顯示

- **WHEN** TradeGood 的 is_cbam_applicable = true
- **THEN** 清單顯示 CBAM 類別標籤（steel / aluminium / cement 等）

#### Scenario: EUDR 暴露判定

- **WHEN** API 回傳 TradeGood 清單
- **THEN** 每個品項含 is_eudr_applicable 欄位：若任一 trade_good_suppliers 的 material_group.required_doc_types 含 'EUDR_DDS' 則為 true

#### Scenario: 上游合規摘要

- **WHEN** API 回傳 TradeGood 清單
- **THEN** 每個品項含 upstream_compliance_status（valid / expiring_soon / expired / missing / unconfigured），取所有上游供應商中最差狀態

### Requirement: 上游供應商合規展開面板

系統 SHALL 在前端 TradeGood 詳情提供展開面板，列出所有上游供應商及其每份合規文件的狀態。

#### Scenario: 展開面板顯示

- **WHEN** 使用者展開某個 TradeGood 的上游供應商面板
- **THEN** 列出所有 trade_good_suppliers，每筆顯示供應商名稱、物料群組、各 required doc_type 的文件狀態與到期日
