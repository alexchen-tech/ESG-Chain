## ADDED Requirements

### Requirement: 供應商透過 Portal 填報嵌入碳排

系統 SHALL 允許供應商在 Supplier Portal 查看自己被關聯到的 TradeGood 清單，並針對每個品項填報嵌入碳排值（kgCO2e/unit）與計算說明。

#### Scenario: 供應商查看待填報品項

- **WHEN** 供應商登入 Portal 進入碳排回報頁面
- **THEN** 系統顯示該供應商被關聯的所有 TradeGood，標示已填報 / 待填報狀態

#### Scenario: 供應商填報碳排

- **WHEN** 供應商填寫 emissions_value 與 calculation_note 並送出
- **THEN** 建立 trade_good_supplier_emissions 記錄，reported_at = now()，confirmed_at = null

#### Scenario: 供應商更新碳排數值

- **WHEN** 供應商對同一 (trade_good_id, supplier_id) 再次填報
- **THEN** 建立新的回報記錄（保留歷史），清單顯示最新一筆

#### Scenario: 碳排值為必填數字

- **WHEN** 供應商送出空白或非正數的 emissions_value
- **THEN** 系統回傳 422 驗證錯誤

### Requirement: 中心廠確認採用碳排數值

系統 SHALL 允許中心廠在 TradeGood 詳情中查看所有上游供應商的回報碳排值，並選擇「確認採用」將數值更新至 trade_goods.embedded_emissions。

#### Scenario: 查看供應商回報清單

- **WHEN** 中心廠開啟某個 TradeGood 詳情的碳排面板
- **THEN** 列出所有有回報記錄的供應商、最新 emissions_value、reported_at、confirmed_at

#### Scenario: 確認採用某供應商數值

- **WHEN** 中心廠點選「確認採用」某筆回報
- **THEN** 更新 trade_good_supplier_emissions.confirmed_at = now()，同步更新 trade_goods.embedded_emissions 為該值，寫入 AuditLog

#### Scenario: 同一 TradeGood 多個供應商皆有回報

- **WHEN** TradeGood 有多個上游供應商各自填報碳排
- **THEN** 中心廠只能確認其中一筆，確認後 embedded_emissions 採用該筆數值
