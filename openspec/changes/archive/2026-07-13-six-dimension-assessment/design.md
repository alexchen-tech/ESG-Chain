## Context

ESG-Chain 現行評估架構以單一問卷模板（per-series 一個 template）產生單一 `score`（含 score_e/score_s/score_g 三個支柱分），再由 `RiskAutoDerivationService` 推導出三軸（axis1/axis2/axis3）風險評估。此架構的根本限制是：不同框架的問卷分數不可比、三軸推導品質取決於問卷題目是否覆蓋對應面向，且地緣與產品合規兩個外部情境因子完全沒有被納入計分。

手動評估已於 2026-07-11 完全移除，系統強制採問卷驅動立場，為本次架構升級提供了乾淨的起點。

現有可複用資產：
- `country_risk_ratings`（geo_risk / labor_risk / env_risk）— E4 外部資料來源
- `SalesProduct.applicable_regulations` + `inferred_regulations` — E6 外部資料來源
- `saq_questions.tags`（JSON）+ `compliance_domains`（JSON）— 多重標記容器已存在
- `supplier.industry_group`（需確認是否已有此欄位，否則需新增）
- `RiskAutoDerivationService` — 重構基礎

## Goals / Non-Goals

**Goals:**
- 單次問卷作答同時輸出六個維度分數（E1–E6）
- E4/E6 混合問卷回答與外部資料計算
- 依 `industry_group` 自動決定問卷模組覆蓋範圍
- 發問卷前動態篩選 E6 合規題（只問適用題）
- 系統強制 100% 必答；逾期觸發 SLA 標記
- 六維分數自動投影為 E/S/G/GP 四軸風險矩陣（保留現有 risk_assessments 結構）
- 現有 SAQ 歷史資料不破壞（六維欄位為新增，非替換）

**Non-Goals:**
- 不重建現有問卷填寫 UI（供應商 Portal 側不在本次範圍）
- 不修改 ERP sync 邏輯
- 不移除現有 score/score_e/score_s/score_g 欄位（保留向下相容）
- 揭露 KPI 與問卷題目的整合（後續獨立變更）

## Decisions

### D1：六維分數儲存位置

**決定**：擴充 `risk_assessments` 表，新增 `dim_e1`–`dim_e6` 六個 `decimal(5,2) nullable` 欄位。同時將 `axis1_score`/`axis2_score`/`axis3_score` 重新定義為 dim_e1/dim_e4/dim_e1（E環境支柱）的別名視圖（migration 中以 DB comment 標記）。

**理由**：避免新增資料表造成 JOIN 複雜度上升；六維分數與四軸風險評估在語意上屬同一筆評估事件，放同一列最自然。`null` 表示「不適用此維度」，與「0分（有答但差）」語意區隔。

**替代方案考慮**：新建 `saq_dimension_scores` 表 → 查詢需多一次 JOIN，前端 API 需合併兩表資料，增加複雜度，否決。

---

### D2：題庫多重標記結構

**決定**：重新設計 `saq_questions.tags` 欄位（現為 `json`），結構從字串陣列改為物件陣列：

```json
[
  { "framework": "E1", "pillar": "E", "weight": 0.04 },
  { "framework": "E3", "pillar": "labor", "weight": 0.06 },
  { "framework": "E6", "pillar": "cbam", "weight": 0.03 }
]
```

`compliance_domains` 欄位保留作為快速過濾索引（字串陣列，如 `["E1","E3","E6"]`），由應用層在儲存時自動同步維護。

**理由**：JSON schema 演進不需要 migration，可漸進補全。`compliance_domains` 保留是為了讓 `WHERE JSON_CONTAINS(compliance_domains, '"E3"')` 等查詢仍然有效。

---

### D3：E4/E6 混合計分架構

**決定**：在 `esgchain-ai` 新增兩個 Celery task：`compute_e4_score` 與 `compute_e6_score`，由 SAQ 完成事件觸發的主評分 task（`score_saq`）在完成六維計分後串接呼叫。

E4 計算公式：
```
exposure_score = country_risk_ratings.geo_risk（最高風險廠址）
maturity_score = Σ(E4標記題目得分) / E4總分基準
E4 = exposure_score × 0.4 + maturity_score × 0.6
```

E6 計算公式：
```
regulation_pressure = len(applicable_regulations ∪ inferred_regulations) / 最大法規數
readiness_score = Σ(適用E6題目得分) / 該供應商E6題總分基準
E6 = readiness_score / max(regulation_pressure, 0.1)  # 分母防零
```

**理由**：混合計分邏輯涉及跨資料庫（MySQL + PostgreSQL）查詢，放在 `esgchain-ai` 的 Python 層統一處理比在 Laravel 中拼裝更清晰。

---

### D4：產業模組自動加掛

**決定**：在 `suppliers` 表新增 `industry_group` enum 欄位（製造業/勞動密集製造/農林漁業/科技電子/物流倉儲/原物料化工/服務業）。問卷發送時（`SaqProjectController::send()`），系統查 `supplier.industry_group` 決定 SAQ 題集：核心題（E1/E4標記）必選，加掛模組依 mapping table 決定。

加掛 mapping（儲存為 `config/industry_module_map.php`）：
```
製造業         → E2, E5
勞動密集製造   → E2, E3, E5
農林漁業       → E3, E6
科技電子       → E2, E5, E6
物流倉儲       → E5
原物料化工     → E3, E6
服務業         → E2, E3
```

**理由**：mapping 放 config 而非資料庫，讓調整不需要 migration，且可隨產業分類演進快速修改。

---

### D5：動態篩題機制（E6）

**決定**：問卷發送前，`SaqService::buildQuestionSet()` 新方法查詢：
1. `SalesProduct` 中與此供應商相關的 `applicable_regulations ∪ inferred_regulations`
2. 與這些法規對應的 E6 題目（透過題目 tag 的 `pillar` 欄位比對法規代碼）

篩出的 E6 題目加入問卷，若供應商無任何適用法規則 E6 維度為 null（不納入計分）。

---

### D6：六維 → 四軸推導規則

```
E 軸：f(E1.環境支柱分, E6)
  probability = ceil((100 - (E1_env × 0.6 + E6 × 0.4)) / 20)
  impact      = country_risk_ratings.env_risk + tier_weight

S 軸：f(E1.社會支柱分, E3)
  probability = ceil((100 - (E1_soc × 0.7 + E3 × 0.3)) / 20)
  impact      = country_risk_ratings.labor_risk + tier_weight

G 軸：f(E1.治理支柱分, E2, E5)
  probability = ceil((100 - (E1_gov × 0.5 + E2 × 0.3 + E5 × 0.2)) / 20)
  impact      = tier_weight + 2

GP 軸：f(E4)
  probability = ceil((100 - E4) / 20)
  impact      = country_risk_ratings.geo_risk
```

所有 probability/impact 夾限於 1–5。若某維度為 null，回退使用現有 `RiskAutoDerivationService` 的預設值（probability=3）。

## Risks / Trade-offs

**[題庫標記工程量大]** → 現有 ~105 題需全部補標 framework tag + per-framework weight。此工作若草率完成會直接影響六維分數品質。緩解：先以 `E1` 為主力標記（所有題目至少帶 E1 tag），E2–E6 的標記分批補全，確保 E1 分數第一批上線可用。

**[E6 動態篩題的題集一致性]** → 同一個 series 不同時期的 project，若 applicable_regulations 有變化，供應商看到的題目不同，跨期比較失真。緩解：`project_questions` snapshot 機制已有，發送時固化題集，後續法規變化不影響已發出的問卷。

**[E4 外部資料時效]** → `country_risk_ratings` 是靜態資料，不即時更新，地緣事件發生後分數滯後。緩解：E4 暴露分只佔 40%，成熟度分（問卷回答）佔 60%，滯後影響有限；另可定期更新 country_risk_ratings。

**[industry_group 分類準確性]** → 自動加掛的品質取決於 industry_group 是否正確，錯誤分類會導致漏問關鍵模組。緩解：onboarding 時人工確認 + UI 提供覆蓋機制（可手動指定加掛模組）。

**[現有 SAQ 歷史資料]** → 舊問卷沒有多重標記，dim_e1–dim_e6 欄位為 null。歷史風險評估的四軸仍從舊邏輯計算，兩套計算結果並存於不同時期的記錄，需在 UI 層標示「舊版評估（三軸）」vs「新版評估（六維）」。

## Migration Plan

1. **DB migration**：`suppliers` 新增 `industry_group`；`risk_assessments` 新增 `dim_e1–dim_e6`
2. **題庫標記**：補全現有 105 題的 E1 tag（最低要求），確保計分不斷線
3. **esgchain-ai 計分引擎**：部署新版多維度計分 task，feature flag 控制啟用時機
4. **Laravel 問卷發送**：加入動態篩題 + 產業模組加掛邏輯
5. **前端**：UI 更新支援六維顯示，舊版（三軸）記錄用 badge 標示版本
6. **Rollback**：feature flag 關閉 → 回退到舊版單一計分；dim_e1–dim_e6 欄位保留但前端不顯示

## Open Questions

- `supplier.industry_group` 的分類值是由系統管理員設定還是從 ERP 的行業代碼自動映射？（影響 onboarding 流程設計）
- E2（ISO 20400）的加掛觸發條件是「供應商自身有採購行為」，這個判斷依據是什麼欄位？
- 六維分數在供應商 Portal（供應商自己的視角）是否顯示？還是只有採購方看到？
