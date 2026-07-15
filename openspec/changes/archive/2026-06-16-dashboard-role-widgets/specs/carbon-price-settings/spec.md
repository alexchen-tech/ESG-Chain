## ADDED Requirements

### Requirement: 系統碳價假設管理
系統 SHALL 提供 admin 專屬設定頁面，允許設定內部碳成本定價（€/tCO₂e）。此設定 MUST 作為全系統 CBAM 風險金額計算的基準值，修改後立即反映於儀表板及所有相關計算。

#### Scenario: admin 修改碳價設定
- **WHEN** admin 使用者在「系統碳價假設」頁面輸入新碳價並儲存
- **THEN** 系統更新 `system_settings` 中的 `carbon_price_eur` 值，並記錄修改時間與修改者

#### Scenario: 非 admin 無法存取設定頁
- **WHEN** 非 admin 角色使用者嘗試存取 `/settings/carbon-price`
- **THEN** 系統回傳 403 或重新導向，不允許存取

#### Scenario: 碳價設定值有效範圍
- **WHEN** 使用者輸入負數或零值的碳價
- **THEN** 系統顯示驗證錯誤「碳價必須大於 0」，拒絕儲存

### Requirement: 碳價顯示與說明
設定頁 SHALL 顯示目前碳價、最後更新時間、更新者，以及碳成本內部定價法的說明文字（幫助使用者理解此設定的用途）。

#### Scenario: 顯示現有碳價資訊
- **WHEN** admin 進入「系統碳價假設」頁面
- **THEN** 顯示：目前碳價（€/tCO₂e）、最後更新時間、更新者姓名、應用說明

#### Scenario: 首次設定（無預設值）
- **WHEN** 系統尚未設定碳價（首次安裝）
- **THEN** 頁面顯示預設值 65.00（€/tCO₂e，參考 2024 EU ETS 均價），並提示「此為系統預設值，建議依貴司內部碳定價政策調整」
