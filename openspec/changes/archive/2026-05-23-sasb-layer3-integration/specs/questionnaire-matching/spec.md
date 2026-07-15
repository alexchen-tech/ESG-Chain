## ADDED Requirements

### Requirement: 問卷發送配對模式切換
`QuestionnaireView.vue` 的發送問卷 Modal SHALL 提供「配對模式」切換：**自動**（依供應商 SASB Industry 推薦範本）和**手動**（採購商從所有 active 範本中選）。

#### Scenario: 選擇自動配對模式
- **WHEN** 使用者勾選「自動配對」並選擇供應商
- **THEN** 系統呼叫推薦 API，依相容性排序顯示建議範本清單

#### Scenario: 選擇手動配對模式
- **WHEN** 使用者選擇「手動選擇」
- **THEN** 顯示所有 active 範本的下拉或列表，每個範本旁顯示相容性標籤

### Requirement: 範本相容性標籤
在手動選擇模式下，每個範本 SHALL 顯示相容性標籤：
- ✓ 完全匹配（範本 Industry = 供應商 Industry）
- ⚠ Sector 匹配（同 Sector，不同 Industry）
- ○ 通用（範本無 Industry 限制）
- ✗ 不相容（範本有 Industry 但與供應商不匹配）

#### Scenario: 相容性標籤顯示
- **WHEN** 供應商是 Iron & Steel Producers（EM-IS），範本 A 綁定 EM-IS，範本 B 綁定 TC-ES
- **THEN** 範本 A 顯示「✓ 完全匹配」，範本 B 顯示「✗ 不相容」

### Requirement: 推薦範本 API
`POST /api/v1/questionnaires/recommend-templates` SHALL 接受 `supplier_ids: string[]`，回傳每位供應商對應的推薦範本列表（依相容性排序），附帶 match_type 欄位。

#### Scenario: 單一供應商推薦
- **WHEN** 供應商有 sasb_industry_id，且有範本關聯到同 Industry
- **THEN** 回傳 match_type="exact" 的範本排在首位

#### Scenario: 多供應商不同 Industry 推薦
- **WHEN** 選取多個不同 Industry 的供應商
- **THEN** 各自回傳各自的推薦清單，或找出「公約數」（共同適用的通用範本）

### Requirement: 發送問卷 Modal 改版
發送問卷 Modal SHALL 改為顯示：
1. 配對模式 radio（自動 / 手動）
2. 供應商多選（現有）
3. 範本選擇區域（自動模式顯示推薦列表；手動模式顯示所有範本 + 相容性標籤）
4. 截止日期（現有）

「範本 UUID 直接輸入」SHALL 移除，改為正常的選擇 UI。

#### Scenario: 自動模式無推薦結果
- **WHEN** 所選供應商的 Industry 找不到任何匹配範本
- **THEN** 顯示「找不到完全匹配範本，以下為通用範本」並列出通用範本
