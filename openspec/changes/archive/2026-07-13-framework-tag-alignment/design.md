## Context

ESG-Chain 的六維評核架構（E1–E6）橫跨三個資料層：

| 層 | 資料表 / 欄位 | 舊命名 | 問題 |
|---|---|---|---|
| 加權層 | `framework_default_weights.scoring_framework` | E1, E2, E3, E4, E5, E6 | E-code，無法對應標籤庫 |
| 標籤層 | `question_tags.l1_domain` | ESG, ISO20400, ISO26000, Geo-Risk, ISO28000, Product-Compliance | ISO 標準名稱 |
| 題庫層 | `saq_questions.tags[].framework` | "E1"（舊）或字串陣列 | 兩種混合格式，無法計算 |

計分引擎（`score_saq_v2`）需要：`saq_questions.tags[].framework` JOIN `framework_default_weights.scoring_framework`，以及 `tags[].pillar` JOIN `framework_default_weights.pillar_slug`。舊命名系統讓這兩個 JOIN 無法成立。

**標籤庫是唯一既有完整 L1/L2/L3 分類體系的層**，因此以其命名為 canonical key。

## Goals / Non-Goals

**Goals:**
- 讓 `scoring_framework` = `l1_domain` = `tags[].framework`（同一字串）
- 讓 `pillar_slug` = `l2_pillar` prefix = `tags[].pillar`（可直接 JOIN）
- 清除加權表中 E1–E6 重複行，補齊 ISO28000 / Product-Compliance
- 遷移 180 題的 tags 格式

**Non-Goals:**
- 不重構計分引擎本身（`score_saq_v2` task 邏輯不在本次範圍）
- 不修改 `question_tags` 的 L2/L3 分類（標籤庫內容不動）
- 不影響 `saqs.active_modules`（仍使用 E1–E6 作為問卷模組代碼）

## Decisions

### D1：標籤庫 L1 domain 為 canonical framework key

六維對應關係鎖定如下，不再使用 E-code 作為資料 key：

| E-code | 資料 key | 中文定義 |
|---|---|---|
| E1 | `ESG` | ESG 整體 |
| E2 | `ISO20400` | 採購永續 |
| E3 | `ISO26000` | 社會責任 |
| E4 | `Geo-Risk` | 地緣風險 |
| E5 | `ISO28000` | 供應鏈安全 |
| E6 | `Product-Compliance` | 產品合規 |

E-code 保留為 UI 顯示標籤（tab label "E5 · ISO 28000"），不作為資料 key。

### D2：tags[].pillar 指向 L2（不是 L3）

計分引擎的加權粒度是 pillar（L2），不是個別題目主題（L3）。L3 slug 用於題庫瀏覽分類，不進入計分路徑。

```
加權表 pillar_slug (L2)
  ├── iso28k.physical   ← 包含 L3: iso28k.physical.access / .background / .facility
  ├── iso28k.cert
  ├── iso28k.cargo
  └── iso28k.infosec
```

### D3：舊格式題目依關鍵字規則推斷 framework + pillar

120 筆舊字串格式題目透過 23 條關鍵字正則規則分配，無法比對者 fallback 至 `ESG | esg.env`。此為一次性遷移，後續新增題目須直接填寫正確格式。

### D4：saqs.active_modules 繼續使用 E-code

問卷的動態模組加掛（E1–E6）是業務邏輯層概念，與資料 key 分離。`active_modules: ["E1","E4","E5"]` 的語意是「本次問卷啟用哪些維度」，透過 `config/industry_module_map.php` 的 E-code → `l1_domain` 對照表在計分時轉換。

## Risks / Trade-offs

| 風險 | 影響 | 緩解 |
|---|---|---|
| 關鍵字規則誤分類 | 少數題目可能落在錯誤 framework | migration 後可在題庫管理頁手動修正；`compliance_domains` 欄位提供可見性 |
| AI 計分任務未更新 | `score_saq_v2` 若以 E-code 比對 framework 會失敗 | 計分任務更新列入後續 task；`SIX_DIM_SCORING=false` 確保上線前不觸發 v2 路徑 |
| 加權表舊 E1–E4 行刪除 | 若有其他查詢依賴 E1–E4 key 會 404 | 搜尋 codebase 確認無其他引用（ESG/ISO20400/ISO26000/Geo-Risk 原行保留，僅刪除重複的 E-code 行） |
