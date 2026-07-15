## Context

主儀表板（`DashboardView.vue` + `DashboardService.php`）在不同角色間有四個已確認問題，需要同時修正前後端。三軸風險模型（axis1/axis2/axis3）已成為系統主要風險評估方式，舊的 probability×impact 計算應退場。

## Goals / Non-Goals

**Goals**
- sustain 角色可以看到 ESG 分數 widget 資料
- analyst 角色有適合的 KPI 卡
- 「高風險供應商」數字與供應商詳頁三軸風險等級一致
- 永續風險概覽可從側欄進入

**Non-Goals**
- 不重新設計儀表板 widget 佈局
- 不新增資料表或 migration
- 不改動 EsgScores widget 的 UI

## Technical Design

### 1. sustain EsgScores 資料載入（前端）

`DashboardView.loadData()` 在 `role === 'sustain'` 時補呼叫 `dashboardApi.esgScores()`，將結果存入 `this.esgScores`。

確認 `dashboardApi.esgScores()` 是否存在：若無需在 `dashboard.ts` API module 補上。

EsgScores 資料來源：後端 `DashboardController` 應已有對應 endpoint（待確認），若無則新增 `GET /api/v1/dashboard/esg-scores`，由 `DashboardService::getEsgScores()` 計算三軸平均分布。

### 2. 側欄加入永續風險概覽入口（前端）

`AppSidebar.vue` 的 `risk-group` 子選單加一項：
```js
{ name: 'sustainability-risk', path: '/dashboard/sustainability-risk', label: '永續風險概覽', roles: ['admin','sustain','comply','analyst'] }
```

### 3. 高風險定義改為三軸（後端）

`DashboardService::getSummary()` 中 `$highRiskIds` 的 query 改為：

```php
$highRiskIds = DB::table('risk_assessments')
    ->select('supplier_id')
    ->where(function($q) {
        $q->whereIn('axis1_level', ['high', 'extreme'])
          ->orWhereIn('axis2_level', ['high', 'extreme'])
          ->orWhereIn('axis3_level', ['high', 'extreme']);
    })
    ->distinct()
    ->pluck('supplier_id');
```

注意：`risk_assessments` 表需確認有 `axis1_level`、`axis2_level`、`axis3_level` 欄位（由 SAQ→RA 推導時寫入）。

### 4. analyst KPI 卡（後端）

`DashboardService::getSummary()` 的 `match` 補上 analyst case：

```php
'analyst' => [
    'cards' => [
        ['key' => 'saq_pending', 'label' => 'SAQ 待審核', 'value' => $saqPending, 'link' => '/questionnaires', 'urgent' => $saqPending > 0],
        ['key' => 'high_risk',   'label' => '高風險供應商', 'value' => $highRisk, 'link' => '/risk', 'urgent' => $highRisk > 0],
    ],
],
```

## Key Decisions

- **高風險定義切換**：axis level 而非舊的乘積，與供應商詳頁三軸顯示語意一致。舊定義完全廢棄，不保留相容路徑。
- **esgScores endpoint**：若後端尚無此 endpoint，需新增；否則補前端 call 即可。實作時先確認再決定範圍。
