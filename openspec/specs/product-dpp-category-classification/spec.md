### Requirement: 產品 DPP 類別自動判定
系統 SHALL 依產品的 HS Code 前綴自動判定其所屬 DPP 類別（`dpp_category`），並 SHALL 允許人工覆寫判定結果。

#### Scenario: HS Code 落於電池前綴範圍
- **WHEN** 產品的 HS Code 前四碼落於 8507 系列（鉛酸/鎳氫鎳鎘/鋰離子等蓄電池）
- **THEN** 系統 SHALL 自動將該產品的 `dpp_category` 判定為 `battery`

#### Scenario: HS Code 未對應任何已知 DPP 類別
- **WHEN** 產品的 HS Code 不在任何已知類別對照表內
- **THEN** 系統 SHALL 將 `dpp_category` 留空（`null`），不得猜測或報錯

#### Scenario: 人工覆寫判定結果
- **WHEN** 使用者在銷售產品詳情頁手動指定或修正 `dpp_category`
- **THEN** 系統 SHALL 採用人工指定的值，不因後續 HS Code 未變更而被自動判定覆蓋回原值
