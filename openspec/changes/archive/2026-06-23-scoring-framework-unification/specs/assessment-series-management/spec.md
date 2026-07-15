## REMOVED Requirements

### Requirement: Series.domain 寫入停止

`AssessmentSeriesService::create()` 建立 Series 時不再寫入 `domain` 欄位（欄位保留 nullable，不 DROP）。

#### Scenario: 建立 Series 後 domain 為 NULL
- **WHEN** POST `/api/v1/assessment-series` 建立新 Series
- **THEN** `assessment_series.domain` 為 NULL（不從 template.scoring_framework 複製）
- **AND** API 回傳的框架資訊讀自 `series.template.scoring_framework`

## MODIFIED Requirements

### Requirement: SeriesListView 以 template.scoring_framework 為唯一框架來源

Series 列表頁移除 `?? s.domain` fallback，改以 `template.scoring_framework` 為唯一來源。

#### Scenario: Series 色條與 badge 顯示
- **WHEN** SeriesListView 渲染 Series 卡片的框架色條與 badge
- **THEN** 讀 `s.template?.scoring_framework`（移除 `?? s.domain` fallback）
- **AND** 若 template.scoring_framework 為 null，顯示「通用」（實務上不會發生）

#### Scenario: SeriesDetailView 框架顯示
- **WHEN** SeriesDetailView 顯示 Series 的框架 chip
- **THEN** 讀 `series.template?.scoring_framework`（非 `series.domain`）

#### Scenario: 自動命名產生
- **WHEN** SeriesDetailView 自動產生報告名稱（例：「Q3 ESG 評核」）
- **THEN** 讀 `series.template?.scoring_framework`（非 `series.domain`）

### Requirement: AssessmentSeriesService::getScoringConfig 框架來源不變

`getScoringConfig()` 已讀 `series.template?.scoring_framework`（而非 `series.domain`），此行為維持不變，確認不受本次修改影響。
