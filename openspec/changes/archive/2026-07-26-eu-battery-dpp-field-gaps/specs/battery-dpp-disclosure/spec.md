## ADDED Requirements

### Requirement: 電池類別與規格資料模型
系統 SHALL 提供產品層級（一對一）的電池規格資料模型，記錄電池類別、化學系統、額定容量、額定電壓與重量。

#### Scenario: 填寫電池規格
- **WHEN** 使用者為一個 `dpp_category = battery` 的產品填寫電池類別、化學系統、額定容量、額定電壓、重量
- **THEN** 系統 SHALL 建立或更新該產品唯一一筆 `product_battery_specs` 紀錄

#### Scenario: 非電池產品不顯示電池規格入口
- **WHEN** 產品的 `dpp_category` 不是 `battery`
- **THEN** 系統 SHALL NOT 在該產品詳情頁顯示電池規格填寫入口

### Requirement: 關鍵原料回收含量
電池規格資料模型 SHALL 包含鋰、鈷、鎳、鉛四種法規指定金屬的回收料含量比例欄位，且 SHALL NOT 與既有紡織品泛用再生料比例（`product_circularity_snapshots`）共用同一組欄位。

#### Scenario: 填寫關鍵原料回收比例
- **WHEN** 使用者填寫電池規格中鋰／鈷／鎳／鉛任一金屬的回收料含量百分比
- **THEN** 系統 SHALL 分別記錄於對應欄位，未填寫的金屬欄位 SHALL 為 `null` 而非預設 0

### Requirement: 效能與耐久性揭露
電池規格資料模型 SHALL 包含循環壽命、預期使用年限、放電效率、初始容量/健康狀態說明、操作溫度範圍欄位，皆為人工填報。

#### Scenario: 效能欄位缺漏時的出口審查提示
- **WHEN** 某電池類別產品的出口市場審查（EU）執行時，`product_battery_specs` 尚未填寫或循環壽命等關鍵欄位為空
- **THEN** 審查結果 SHALL 產生對應的獨立 finding 標示缺漏項目，不得因缺漏而中斷整體審查流程

### Requirement: 批次護照電池區塊
批次護照 SHALL 在產品屬於電池類別時輸出電池規格區塊，非電池類別產品該區塊 SHALL 為 `null`。

#### Scenario: 電池產品的批次護照
- **WHEN** 查詢一個 `dpp_category = battery` 且已填寫電池規格的批號的批次護照
- **THEN** 回傳的 JSON SHALL 包含 `battery_spec` 區塊，內容對應該產品的電池類別、化學系統、容量、電壓、重量、關鍵原料回收比例、效能耐久性欄位

#### Scenario: 非電池產品的批次護照
- **WHEN** 查詢一個非電池類別產品的批號的批次護照
- **THEN** 回傳的 JSON 中 `battery_spec` 欄位 SHALL 為 `null`
