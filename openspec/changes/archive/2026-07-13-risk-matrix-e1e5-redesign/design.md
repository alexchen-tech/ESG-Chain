## Context

風險矩陣使用 5×5 P×I 模型：Probability 由維度分數推導（`max(1,min(5,ceil((100−score)/20)))`），Impact 由供應商 Tier 決定（Tier1=5, Tier2=4, Tier3=3, else=2）。原本 4 個 Tab（E/S/G/GP）每個對應單一 dim_e 欄位，但對應關係有錯誤且 dim_e5 未被使用。

## Goals / Non-Goals

**Goals:**
- Tab 直接對應 E1–E5 五個維度，消除語意錯誤
- 新增「綜合最差」Tab 展示每個供應商最薄弱的維度
- 右側面板動態高亮 active Tab 對應的維度 chip
- 綜合 Tab 高亮各供應商的 worst_dim（per-supplier 動態）
- 修正 SixDimHeatmapView 的標籤

**Non-Goals:**
- 不改動 P×I 計算公式與格子顏色閾值
- 不改動 Impact（Tier）邏輯
- 不新增 migration 或 DB 欄位
- dim_e6 全為 null，本次不納入 Tab

## Decisions

### 後端：COMPOSITE 計算

`buildMatrix()` 與 `matrixSuppliers()` 在 `dim = 'composite'` 時，以 `LEAST(dim_e1, dim_e2, dim_e3, dim_e4, dim_e5)` 取代單一欄位作為 `dim_score`：

```php
private const DIM_SCORE_FIELD = [
    'e1'        => 'dim_e1',
    'e2'        => 'dim_e2',
    'e3'        => 'dim_e3',
    'e4'        => 'dim_e4',
    'e5'        => 'dim_e5',
    'composite' => null,  // 特殊處理：LEAST(e1..e5)
];
```

`buildMatrix()` 對 composite 改用：
```sql
LEAST(dim_e1, dim_e2, dim_e3, dim_e4, dim_e5) AS dim_score
```

### 後端：worst_dim_key 回傳

`matrixSuppliers()` 在 composite 維度時，額外回傳每個供應商的 `worst_dim_key`（`dim_e1`–`dim_e5` 中值最低者的欄位名稱）：

```php
'worst_dim_key' => $ra ? $this->getWorstDimKey($ra) : null,
```

```php
private function getWorstDimKey(RiskAssessment $ra): string {
    $dims = ['dim_e1'=>$ra->dim_e1,'dim_e2'=>$ra->dim_e2,'dim_e3'=>$ra->dim_e3,'dim_e4'=>$ra->dim_e4,'dim_e5'=>$ra->dim_e5];
    $dims = array_filter($dims, fn($v) => $v !== null);
    return array_key_first(array_filter($dims, fn($v) => $v === min($dims)));
}
```

其他維度的 `worst_dim_key` 固定回傳該維度欄位名稱（`dim_e1`…`dim_e5`），讓前端統一使用同一個欄位決定高亮。

### 前端：RiskDimension type

```typescript
export type RiskDimension = 'E1' | 'E2' | 'E3' | 'E4' | 'E5' | 'COMPOSITE'
```

### 前端：DIMENSIONS array（RiskMatrix5x5.vue）

```javascript
const DIMENSIONS = [
  { value: 'COMPOSITE', label: '綜合最差' },
  { value: 'E1',        label: 'E1 環境管理' },
  { value: 'E2',        label: 'E2 氣候與碳排' },
  { value: 'E3',        label: 'E3 社會責任' },
  { value: 'E4',        label: 'E4 地緣風險' },
  { value: 'E5',        label: 'E5 公司治理' },
]
```

預設 Tab 改為 `'COMPOSITE'`。

### 前端：動態高亮邏輯

`SIX_DIMS` chip 的高亮由 `worst_dim_key`（後端回傳）決定：

```javascript
// template
:class="{ 'sc-dim-active': s.worst_dim_key === d.key }"

// data 中 worst_dim_key 由 API 回傳
```

`SIX_DIMS` 常數的 key 值（`dim_e1`–`dim_e5`）與 `worst_dim_key` 一致，直接比對即可。

### SixDimHeatmapView DIMS 標籤修正

```javascript
const DIMS = [
  { key: 'E1', field: 'dim_e1', label: '環境管理' },
  { key: 'E2', field: 'dim_e2', label: '氣候與碳排' },
  { key: 'E3', field: 'dim_e3', label: '社會責任' },
  { key: 'E4', field: 'dim_e4', label: '地緣風險' },
  { key: 'E5', field: 'dim_e5', label: '公司治理' },
  { key: 'E6', field: 'dim_e6', label: '供應鏈透明度' },
]
```

## Risks / Trade-offs

- **LEAST() null 行為**：MySQL `LEAST()` 在任一參數為 null 時回傳 null。現有資料 dim_e1–e5 均有值，dim_e6 為 null 已排除在外，風險低。若未來有部分欄位 null，composite Tab 對應供應商 P 會 fallback 為 3（現有 `scoreToProb(null)` 行為）。
- **backward compatibility**：舊的 `E/S/G/GP` 維度字串不再接受，任何書籤或外部連結會 422。可接受，無外部整合依賴。
