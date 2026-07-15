# Design: Wave B — New Modules

## B1 Risk 模型重設計

### 新 risk_assessments 表結構

```sql
CREATE TABLE risk_assessments (
    id           CHAR(36)   PRIMARY KEY,
    supplier_id  CHAR(36)   NOT NULL,
    e_probability TINYINT UNSIGNED NOT NULL,  -- 1-5
    e_impact      TINYINT UNSIGNED NOT NULL,  -- 1-5
    s_probability TINYINT UNSIGNED NOT NULL,
    s_impact      TINYINT UNSIGNED NOT NULL,
    g_probability TINYINT UNSIGNED NOT NULL,
    g_impact      TINYINT UNSIGNED NOT NULL,
    gp_probability TINYINT UNSIGNED NOT NULL, -- 地緣政治
    gp_impact      TINYINT UNSIGNED NOT NULL,
    assessed_at   DATETIME NOT NULL,
    assessed_by   CHAR(36) NULL,
    notes         TEXT NULL,
    created_at    DATETIME,
    updated_at    DATETIME,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
);

-- 衍生分數（computed 或 application layer）
-- score = probability × impact（1–25）
-- level = very_low / low / medium / high / extreme
```

### Score → Level 映射

```
score =  1– 4  → very_low
score =  5– 9  → low
score = 10–14  → medium
score = 15–19  → high
score = 20–25  → extreme
```

### 5×5 矩陣資料計算

```php
// GET /risk/matrix?dimension=E
$dimension = 'e'; // e, s, g, gp

$matrix = [];
for ($p = 1; $p <= 5; $p++) {
    for ($i = 1; $i <= 5; $i++) {
        $count = RiskAssessment::where("{$dimension}_probability", $p)
            ->where("{$dimension}_impact", $i)
            ->count();
        $score = $p * $i;
        $matrix[] = [
            'probability' => $p,
            'impact'      => $i,
            'cell_score'  => $score,
            'risk_level'  => $this->scoreToLevel($score),
            'supplier_count' => $count,
        ];
    }
}
```

### risk-summary 邏輯

```php
// 取每個供應商最新一筆評估
$latest = RiskAssessment::where('supplier_id', $supplierId)
    ->orderBy('assessed_at', 'desc')
    ->first();

// 回傳格式（符合 spec RiskSummary schema）
return [
    'supplier_id' => $supplierId,
    'e'  => $latest ? $this->buildDimension($latest, 'e')  : null,
    's'  => $latest ? $this->buildDimension($latest, 's')  : null,
    'g'  => $latest ? $this->buildDimension($latest, 'g')  : null,
    'gp' => $latest ? $this->buildDimension($latest, 'gp') : null,
    'assessed_at' => $latest?->assessed_at,
];

// buildDimension('e') 回傳 { probability, impact, score, level }
```

---

## B2 Settings 設計

### QuestionnaireTemplate 已存在表，補充欄位

現有 `saq_templates` 表需要新增：
- `created_by_id` CHAR(36)（建立者 user_id）

### SASB Industries Seeder 資料來源

使用 SASB 官方分類（簡版，11 sectors × 平均 7 industries）：

主要 Sectors：
- Technology & Communications
- Extractives & Minerals Processing
- Financials
- Food & Beverage
- Health Care
- Infrastructure
- Renewable Resources & Alternative Energy
- Resource Transformation
- Services
- Transportation
- Consumer Goods

### Settings RBAC

| 端點 | 允許角色 |
|------|---------|
| GET（讀取） | 全部已登入角色 |
| POST/PUT/DELETE | admin 限定 |

---

## FastAPI 更新

Wave B 後，`esgchain-ai` 的 `/ai/v1/risk/assess` 端點：
- 保留但標記為 internal（不對前端暴露）
- 改為接受 Laravel 呼叫，做批量風險趨勢分析用
- 不再是 source of truth（Laravel risk_assessments 才是）
