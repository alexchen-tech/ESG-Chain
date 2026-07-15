## MODIFIED Requirements

### Requirement: 問卷專案建立 Modal
採購商 SHALL 能在建立問卷專案時選擇「供應商群組」（選填），系統依群組推導的 `compliance_domains` 在後續題目預覽中 highlight 相關題目。

#### Scenario: 建立 Modal 包含供應商群組選擇
- **WHEN** 採購商點擊「建立問卷專案」
- **THEN** Modal SHALL 顯示「供應商群組」下拉選單（選填），選項來自 `GET /api/v1/supplier-groups`

#### Scenario: 選擇群組後顯示推導的合規範疇
- **WHEN** 採購商在 Modal 中選擇一個供應商群組
- **THEN** 系統 SHALL 呼叫推導 API，並在 Modal 下方顯示「此群組涉及合規範疇：CMRT、CE」等提示文字

#### Scenario: 群組無 TradeGoods 時的提示
- **WHEN** 採購商選擇的群組推導結果為空
- **THEN** 系統 SHALL 顯示「此群組尚無物料記錄，無合規推薦」提示，建立流程不受阻

#### Scenario: 問卷範本題目列表的合規 badge
- **WHEN** 採購商在建立 Modal 確認範本後，預覽範本題目清單
- **AND** 已選擇供應商群組且推導出 compliance_domains
- **THEN** `compliance_domains` 與群組推導結果有交集的題目 SHALL 顯示「⚠ 合規相關」badge

#### Scenario: 僅顯示合規相關題目的 toggle
- **WHEN** 採購商點擊「僅顯示合規相關題目」toggle
- **THEN** 題目列表 SHALL 只顯示帶有合規 badge 的題目，其餘隱藏

#### Scenario: 未選擇群組時不顯示推薦
- **WHEN** 採購商建立問卷專案但未選擇供應商群組
- **THEN** 題目列表無合規 badge，建立流程與現有行為完全一致
