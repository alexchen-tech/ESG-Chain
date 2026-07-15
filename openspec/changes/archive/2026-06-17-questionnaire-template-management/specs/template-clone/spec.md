## ADDED Requirements

### Requirement: Clone API
`POST /api/v1/settings/questionnaire-templates/:id/clone` SHALL 複製指定範本的所有欄位（name 加「 (複製)」、version 加 `.copy`、description、sasb_industry_id）及所有 SAQQuestion（template_id 換成新範本，其他欄位完整複製），回傳新範本的完整資料（201）。

#### Scenario: 複製有 5 道題的範本
- **WHEN** POST clone 一個有 5 道題的範本
- **THEN** 建立新範本（name 加「 (複製)」、is_active=false）及 5 道對應題目，回傳 201

#### Scenario: 複製後名稱格式
- **WHEN** 原範本名稱為「2025 T1 ESG 評估」
- **THEN** 新範本名稱為「2025 T1 ESG 評估 (複製)」，version 為「1.0.0.copy」

### Requirement: 前端複製按鈕
範本列表每列 SHALL 有「複製」按鈕，點擊後呼叫 clone API，成功後在列表中新增顯示複製出的範本，並顯示「已複製」toast 提示。

#### Scenario: 複製中禁止重複點擊
- **WHEN** 複製進行中
- **THEN** 按鈕 disabled + loading 狀態

#### Scenario: 複製成功
- **WHEN** clone API 回傳 201
- **THEN** 切換到「停用」Tab（複製的範本 is_active=false），高亮顯示新範本列
