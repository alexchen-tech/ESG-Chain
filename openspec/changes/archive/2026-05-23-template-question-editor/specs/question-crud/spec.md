## ADDED Requirements

### Requirement: 新增題目 Modal
詳情頁 SHALL 提供「+ 新增題目」按鈕，點擊開啟 Modal，欄位包含：題文（必填）、分類（E/S/G select）、題型（select）、選項（single_choice/multiple_choice 時顯示動態選項輸入）、權重（0–1 number）、是否必填（checkbox）。

#### Scenario: 新增 single_choice 題目
- **WHEN** admin 選擇題型 single_choice，填入題文、至少 2 個選項，點擊「新增」
- **THEN** API 成功，題目出現在列表末尾，序號自動遞增

#### Scenario: 新增 boolean 題目
- **WHEN** admin 選擇題型 boolean（是/否）
- **THEN** 選項輸入區隱藏，送出後題目正常新增

#### Scenario: 題文為空時不允許送出
- **WHEN** admin 未填題文點擊新增
- **THEN** 新增按鈕 disabled 或顯示驗證提示

### Requirement: 編輯題目 Modal
每題操作欄 SHALL 提供「編輯」按鈕，點擊後開啟預填現有值的 Modal，可修改題文、分類、選項、權重、必填；題型建立後不可更改。

#### Scenario: 編輯題文與權重
- **WHEN** admin 修改題文後儲存
- **THEN** API PUT 成功，列表即時更新

#### Scenario: 嘗試修改題型
- **WHEN** 編輯 Modal 開啟
- **THEN** 題型欄位顯示但 disabled，不可更改

### Requirement: 刪除題目
每題操作欄 SHALL 提供「刪除」按鈕，點擊後顯示確認 Modal；確認後呼叫 DELETE API，並重新排序剩餘題目序號。

#### Scenario: 刪除確認流程
- **WHEN** admin 點擊刪除並確認
- **THEN** 題目從列表移除，剩餘題目 order 重新整理（前端本地重排）

### Requirement: 題目上下移動排序
每題操作欄 SHALL 提供 ↑（第一題停用）和 ↓（最後一題停用）按鈕，點擊後交換相鄰題目的 order，即時更新前端，並呼叫 PUT API 更新被交換兩題的 order。

#### Scenario: 移動中間題目往上
- **WHEN** admin 點擊第 3 題的 ↑
- **THEN** 前端立即顯示第 2/3 題交換，API 更新兩題的 order 值

#### Scenario: 第一題的 ↑ 按鈕
- **WHEN** 題目列表中第一題
- **THEN** ↑ 按鈕 disabled

### Requirement: 選項動態輸入
題型為 single_choice 或 multiple_choice 時，Modal 中 SHALL 顯示「選項」區塊，可動態新增/刪除選項 input，最少 2 個選項，最多 10 個。

#### Scenario: 新增第 3 個選項
- **WHEN** 使用者點擊「＋ 新增選項」
- **THEN** 新增一個空白 input，焦點移至新 input

#### Scenario: 刪除選項時少於 2 個
- **WHEN** 目前只剩 2 個選項
- **THEN** 刪除按鈕 disabled
