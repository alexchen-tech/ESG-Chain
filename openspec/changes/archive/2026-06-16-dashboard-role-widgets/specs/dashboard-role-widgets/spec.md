## ADDED Requirements

### Requirement: 角色感知 Widget 配置
系統 SHALL 依登入使用者的角色決定儀表板呈現的 Widget 組合。不同角色 MUST 看到針對其工作職責優化的 Widget 集合。

#### Scenario: sustain 角色看到永續部門 Widget
- **WHEN** sustain 角色使用者進入儀表板
- **THEN** 系統顯示：今日行動卡片（SAQ 待審、CAP 7 天內到期、合規文件 7 天到期）、最近供應商動態、到期預警時間軸、ESG 分數分布

#### Scenario: buyer 角色看到採購部門 Widget
- **WHEN** buyer 角色使用者進入儀表板
- **THEN** 系統顯示：今日行動卡片（文件 7 天到期、高風險供應商、待審核供應商）、最近供應商動態、到期預警時間軸、供應商 Tier 分布

#### Scenario: comply 角色看到出口合規 Widget
- **WHEN** comply 角色使用者進入儀表板
- **THEN** 系統顯示：今日行動卡片（商品合規問題、CBAM 商品數、EUDR 未提交）、最近供應商動態、到期預警時間軸、商品合規風險彙總（含 CBAM 金額）

#### Scenario: admin 角色看到全局視角
- **WHEN** admin 角色使用者進入儀表板
- **THEN** 系統顯示所有行動卡片數字及供應商狀態分布

### Requirement: 今日行動卡片
儀表板 SHALL 在頁面頂部顯示 3 張可點擊的「今日行動」卡片，每張卡片顯示數字與說明文字，點擊後導向對應功能頁面。卡片數字為 0 時仍顯示，不隱藏。

#### Scenario: 卡片數字大於 0 時顯示警示色
- **WHEN** 某個行動卡片的數字大於 0（如逾期 CAP = 3）
- **THEN** 數字以警示色（紅色）顯示，卡片有 hover 效果

#### Scenario: 卡片點擊導向功能頁
- **WHEN** 使用者點擊「CAP 7 天內到期」卡片
- **THEN** 系統導向 `/cap` 頁面（帶有適當篩選參數）

### Requirement: 最近供應商動態 Widget
系統 SHALL 顯示過去 7 天內有任一事件的供應商清單，取最近 20 筆，依事件發生時間倒序排列。事件類型包含：供應商狀態變更、SAQ 提交、CAP 狀態更新、合規文件到期（7 天內）、合規文件新上傳。

#### Scenario: 供應商狀態變更顯示狀態流轉
- **WHEN** 供應商在過去 7 天內有狀態變更（如「審核中 → 已認證」）
- **THEN** 動態列表顯示該供應商、事件標籤（「狀態變更」）、新狀態、發生時間

#### Scenario: 無近期動態時顯示空狀態
- **WHEN** 過去 7 天內沒有任何供應商事件
- **THEN** Widget 顯示「近期無動態」空狀態提示

#### Scenario: 點擊動態項目導向供應商詳情
- **WHEN** 使用者點擊某一動態項目
- **THEN** 系統導向該供應商的詳情頁 `/suppliers/{id}`

### Requirement: 合規文件 7 天到期預警 Widget
系統 SHALL 以時間軸形式顯示未來 7 天內到期的合規文件，依到期日期升序排列（最緊急在前）。每筆顯示：供應商名稱、文件類型、到期日、距今天數。

#### Scenario: 到期文件依緊急度排序
- **WHEN** 存在多筆即將到期文件
- **THEN** 距今天數最少的文件排在最前面

#### Scenario: 無即將到期文件時顯示空狀態
- **WHEN** 未來 7 天內無任何到期文件
- **THEN** Widget 顯示「未來 7 天無到期文件」

### Requirement: 商品合規風險彙總 Widget（comply 角色）
系統 SHALL 彙總所有出口商品的多維合規風險，包含 CBAM、EUDR、UFLPA 等法規，並以申報風險金額（€）呈現 CBAM 曝險。CBAM 風險金額 MUST 使用系統設定的碳價假設計算。

#### Scenario: CBAM 風險金額計算
- **WHEN** 商品有 `embedded_emissions` 且 `is_cbam_applicable = true`
- **THEN** 顯示 `embedded_emissions × carbon_price_eur` 的估算申報金額（€）

#### Scenario: 合規問題商品計數
- **WHEN** 出口商品的 `upstream_compliance_status` 為 `expired` 或 `missing`
- **THEN** 「商品合規問題」卡片顯示該商品數量

#### Scenario: embedded_emissions 為 null 的商品
- **WHEN** CBAM 適用商品的 `embedded_emissions` 為 null
- **THEN** 該商品計入「碳排未填報」數量，不計入金額，UI 顯示提示

### Requirement: ESG 分數分布 Widget（sustain 角色）
系統 SHALL 顯示當前活躍 SAQ 專案中，所有已評分 SAQ 的 E、S、G 三維度平均分，以進度條呈現。

#### Scenario: 顯示三維度均值
- **WHEN** 存在活躍 SAQ 專案且有已評分的 SAQ
- **THEN** 顯示 E 維度均值、S 維度均值、G 維度均值，各附進度條（滿分 100）

#### Scenario: 無評分資料時
- **WHEN** 無活躍 SAQ 專案或無已評分 SAQ
- **THEN** 顯示「尚無評分資料」空狀態
