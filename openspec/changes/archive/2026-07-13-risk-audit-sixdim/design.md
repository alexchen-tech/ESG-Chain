## Context

### 現有狀態

`risk_assessments` 表同時存在三代資料：
- `assessment_version = 'legacy'`：手動建立，四軸 E/S/G/GP probability × impact，dim_e1–e6 為 null
- `assessment_version = 'v1'`：multi-framework 時代，有 axis1/axis2/axis3 分數
- `assessment_version = 'v2'`：六維度 SAQ 後自動推導，dim_e1–e6 有值，但同時仍填寫四軸（D6 投影公式）

風險稽核模組（風險矩陣、永續風險概覽）全部以四軸為顯示基礎。CAP 觸發邏輯（`RiskAssessmentObserver`）以 `probability × impact ≥ 20` 為閾值。`suppliers.risk_score` 以四軸最高積分正規化。

DEMO 環境目前有 8 筆 `legacy` 版本的 RA，dim_e1–e6 全為 null。

### 設計約束

- esgchain-api（Laravel）：業務流程、狀態機、CRUD
- esgchain-ai（FastAPI + Celery）：計算密集任務一律非同步
- `risk_assessments` 是現有多處程式碼的核心依賴（Observer、Timeline、AI 建議、矩陣）
- `source_saq_id` 目前作為 unique constraint（一個 SAQ 只能對應一筆 RA）

---

## Goals / Non-Goals

**Goals:**
- `risk_assessments` 以 dim_e1–e6 為唯一有效風險維度，四軸欄位降為 nullable 唯讀
- `source_type`/`source_id` 多型化，允許「地緣事件」作為獨立 RA 來源
- CAP 觸發改為 E1–E6 閾值語意規則，閾值可從 `system_settings.cap_thresholds` 設定
- `suppliers.risk_score` 改用 E1–E6 加權反向合成
- 新「六維熱圖」取代舊風險矩陣 UI
- 新「地緣事件複查」模組：建立事件 → 計算範圍 → Celery 排程批次重算 E4
- DEMO 資料：truncate legacy RA，後續由 SAQ scoring 自然產生 v3

**Non-Goals:**
- 不重建供應商資料的歷史 RA（清除後由新 SAQ 產生）
- 不提供四軸 → 六維的資料遷移轉換公式
- 不修改 `saqs` 表結構（dim_e1–e6 已存在）
- 不實作實時地緣事件資料饋送（手動建立事件即可）
- E6（Product-Compliance）在 CAP 觸發邏輯中條件觸發（dim_e6 為 null 時跳過）

---

## Decisions

### D1：四軸欄位降為 nullable，不刪除

**決策**：舊四軸欄位（`e_probability` 等）改為 `NULLABLE`，不從 schema 刪除。

**理由**：
- DEMO 環境的 8 筆 legacy RA 在 truncate 後不再有新資料寫入這些欄位
- 保留欄位避免 migration rollback 風險，也方便未來若需回頭查詢歷史記錄
- `assessment_version` 可作為區分標記：`v3`（新）欄位不填四軸

**替代方案考慮**：直接 DROP COLUMN — 風險高，且 DEMO 環境外若有舊資料會破壞

---

### D2：source_type + source_id 多型（不引入 Polymorphic Relationship ORM）

**決策**：手動維護 `source_type`（enum）+ `source_id`（uuid nullable），不使用 Laravel Polymorphic Relations。

**理由**：
- `source_saq_id` 已有 index，保留並讓 `source_id` 在 `source_type='saq'` 時冗餘（兩者同值）
- Polymorphic 在 RiskAssessment 只有 4 種 source 類型，不需要 ORM 層的完整多型支援
- 查詢時直接 `WHERE source_type='geo_event' AND source_id=?` 即可

**source_saq_id 處理**：migration 中回填 `source_id = source_saq_id WHERE source_saq_id IS NOT NULL`，之後 `source_saq_id` 保留但不再作為 FK 的主要識別。

---

### D3：E4 批次重算走 Celery，不走 Laravel Queue

**決策**：地緣事件觸發的批次 E4 重算，由 Laravel 呼叫 esgchain-ai Celery task `recalculate_e4_batch`。

**理由**：
- E4 計算邏輯（country_defense_score 公式、α mixing）已在 ai 層實作
- 批次重算可能涉及數十家供應商，需非同步避免 HTTP timeout
- 與現有 `DispatchSaqScoringJob` 模式一致（Laravel 發起 → AI 非同步計算 → callback）

**流程**：
```
GeoEvent 建立/觸發重算
    │
    ▼ Laravel
GeoEventService::dispatchRecalculation(geoEventId)
    ├─ 查詢 geo_event_supplier_reviews（status=pending）
    ├─ 每筆 review 更新 status='recalculating'
    └─ POST /ai/v1/geo-event/recalculate-e4（含 supplier_ids + new_country_defense_scores）
           │
           ▼ Celery (esgchain-ai)
    recalculate_e4_batch.delay(...)
           │  for each supplier:
           │  1. 取最新 SAQ dim_e4_questionnaire
           │  2. 以新 country_defense_score 重算 dim_e4
           │  3. 重算 saqs.score（依 series.dim_weights）
           │
           ▼ callback POST /api/v1/risk/geo-events/{id}/review-callback
    Laravel 建立新 RiskAssessment（source_type='geo_event'）
    更新 geo_event_supplier_reviews.status='done'
    觸發 RiskAssessmentObserver（CAP 檢查）
```

---

### D4：CAP 閾值存 system_settings，格式固定

**決策**：`system_settings` 插入 key=`cap_thresholds`，值為 E1–E6 各維度的最低安全分數。

```json
{
  "E1": 40, "E2": 40, "E3": 35,
  "E4": 35, "E5": 40, "E6": 40
}
```

**觸發邏輯**（`RiskAssessmentObserver`）：
```
for each dim in [E1..E6]:
    if dim_score < threshold[dim]:
        → create CAPFinding（category = dimKey, finding = 描述）
if any finding: → create CAP（source_type='risk_assessment'）
E6 若 dim_e6 IS NULL → 跳過（不強制要求 E6 有分數）
```

**CAPFinding category** 沿用 dim key：`E1`、`E2`、`E3`、`E4`、`E5`、`E6`。

---

### D5：六維熱圖 API 設計

**決策**：新端點 `GET /api/v1/risk/six-dim-heatmap`，回傳所有有 RA 的供應商 × dim_e1–e6 分數。

回應結構：
```json
{
  "data": [
    {
      "supplier_id": "...",
      "supplier_name": "越南成衣",
      "supplier_code": "GMN-001",
      "country_code": "VN",
      "assessed_at": "2025-07-01T...",
      "source_type": "saq",
      "dim_e1": 72.5, "dim_e2": 68.0, "dim_e3": 38.0,
      "dim_e4": 42.0, "dim_e5": 55.0, "dim_e6": null,
      "open_cap_count": 2,
      "risk_score": 0.47
    }
  ],
  "thresholds": { "E1":40,"E2":40,"E3":35,"E4":35,"E5":40,"E6":40 },
  "summary": {
    "total": 30,
    "any_dim_critical": 8
  }
}
```

每個供應商取最新 RA（`MAX(assessed_at)`）。

---

### D6：suppliers.risk_score 新公式

```
risk_score = 1 - (Σ(weight[N] × dim_eN) for N with dim_eN NOT NULL) / 100
weights 從 system_settings.dim_weight_defaults 讀
若 dim_e6 為 null，將 E6 的 weight 分攤到其他五維
```

`syncSupplierRiskScore()` 在 `RiskAssessmentObserver.created/updated` 時執行。

---

## Risks / Trade-offs

**[R1] Celery callback 失敗導致 review 卡在 recalculating**
→ 緩解：geo_event_supplier_reviews 加 `recalculation_started_at` timestamp；Laravel 定期 job 檢查超過 10 分鐘的 `recalculating` 狀態，自動重試或標記 `failed`

**[R2] DEMO 環境 truncate risk_assessments 後，風險稽核頁面暫時空白**
→ 緩解：在 SixDimDemoRebuildSeeder 之後補跑一個 mini seeder，為已 submitted 的 SAQ 手動插入基本的 v3 RA（直接填 dim_e1–e6，跳過 Celery）

**[R3] cap_thresholds 閾值設定過嚴或過鬆導致 CAP 爆量或空白**
→ 緩解：先用較保守的預設值（E3=35 最嚴），並在 UI 提供閾值說明，admin 可調整

**[R4] 地緣事件 source_type='geo_event' 的 RA 與 SAQ-driven RA 共存，Timeline 邏輯要能區分**
→ 緩解：SupplierTimelineService 按 `source_type` 分類：saq 型顯示問卷連結，geo_event 型顯示事件名稱

---

## Migration Plan

1. **Migration M1**（risk_assessments schema）：四軸欄位改 nullable，加 source_type/source_id，assessment_version default 改 'v3'；回填 source_id = source_saq_id
2. **Migration M2**（geo_events + geo_event_supplier_reviews）：建立新表
3. **Seeder 清理**：truncate risk_assessments（DEMO）
4. **system_settings Seeder**：插入 cap_thresholds
5. **後端程式碼**：Observer、Service、Controller 依序更新（功能開關：可先 deploy migration，再上程式碼）
6. **前端**：SixDimHeatmapView 上線後，路由從 `/risk` 改指向新 view；舊 RiskMatrixView 保留但移出側欄

**Rollback**：migration M1 可 rollback（欄位改回 NOT NULL 需先確保資料乾淨）；若 rollback，前端路由切回舊 view。

---

## Open Questions

- 地緣事件「觸發範圍」計算：目前設計為 `affected_scope.country_codes` 比對 `suppliers.country_code`。是否還需要比對廠址（`supplier_facilities.country`）？建議是，但要確認 `supplier_facilities` 是否有足夠資料。
- `geo_event_supplier_reviews` 的 E4 重算結果若未達 CAP 閾值，是否仍建立新 RA？建議是（留完整稽核軌跡），即使分數沒惡化。
