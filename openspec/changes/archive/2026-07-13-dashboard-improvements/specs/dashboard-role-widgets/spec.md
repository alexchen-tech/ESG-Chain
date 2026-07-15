## MODIFIED Requirements

### Requirement: analyst 角色看到分析部門 Widget

原 spec 無 analyst 角色定義，預設 fall into admin。現修正為：

#### Scenario: analyst 角色進入儀表板
- **WHEN** analyst 角色使用者進入儀表板
- **THEN** 系統顯示：今日行動卡片（SAQ 待審核、高風險供應商）、最近供應商動態、到期預警時間軸

### Requirement: 高風險供應商 KPI 定義

#### Scenario: 高風險供應商數字與三軸等級一致
- **WHEN** 系統計算「高風險供應商」KPI 數字
- **THEN** 計算邏輯為：最新 risk_assessment 中 axis1_level、axis2_level 或 axis3_level 任一為 `high` 或 `extreme` 的供應商數量
- **AND** 舊的 probability × impact ≥ 15 計算方式 MUST NOT 再使用

### Requirement: sustain 角色 ESG 分數 Widget 資料

#### Scenario: sustain 角色進入儀表板時 ESG 分數 Widget 顯示資料
- **WHEN** sustain 角色使用者進入儀表板
- **THEN** ESG 分數分布 Widget MUST 顯示實際三軸平均分數，不得為空白或 null

## ADDED Requirements

### Requirement: 永續風險概覽側欄入口

#### Scenario: 所有風險相關角色可從側欄進入永續風險概覽
- **WHEN** admin、sustain、comply、analyst 角色的使用者查看側欄
- **THEN** 「風險稽核」子選單 MUST 包含「永續風險概覽」連結，導向 `/dashboard/sustainability-risk`
