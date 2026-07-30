## ADDED Requirements

### Requirement: 範疇三揭露欄位
系統 SHALL 提供 `ghg.scope3_mt_co2e` 揭露欄位，供供應商於永續 KPI 填報頁填寫，欄位定義與既有 `ghg.scope1_mt_co2e`／`ghg.scope2_mt_co2e` 一致（numeric，單位噸 CO2e，年度填報）。

#### Scenario: 供應商填報範疇三數值
- **WHEN** 供應商於 Portal 永續 KPI 填報頁填寫範疇三排放量並儲存
- **THEN** 系統 SHALL 建立對應 `SupplierDisclosure` 紀錄，`field_slug='ghg.scope3_mt_co2e'`

### Requirement: 供應商碳盤查覆蓋度稽核視圖
系統 SHALL 提供中心廠端視圖，依供應商列出範疇一/二/三揭露欄位的填報狀態，並依填報狀況分級為「未盤查」／「僅範疇一二」／「含範疇三」。

#### Scenario: 供應商完全未填報
- **WHEN** 某供應商在指定期間三個 scope 欄位皆無 `SupplierDisclosure` 紀錄
- **THEN** 系統 SHALL 標示該供應商為「未盤查」

#### Scenario: 供應商僅填範疇一二
- **WHEN** 某供應商填了 `ghg.scope1_mt_co2e` 與 `ghg.scope2_mt_co2e`，但 `ghg.scope3_mt_co2e` 無紀錄
- **THEN** 系統 SHALL 標示該供應商為「僅範疇一二」

#### Scenario: 供應商三範疇皆填
- **WHEN** 某供應商三個 scope 欄位皆有 `SupplierDisclosure` 紀錄
- **THEN** 系統 SHALL 標示該供應商為「含範疇三」
