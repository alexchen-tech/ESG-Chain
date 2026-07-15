## Context

風險矩陣為 5×5，格分數 = Probability × Impact。目前 Impact 由 `RiskMatrixController::tierToImpact()`（esgchain-api）以 `suppliers.tier` 查表決定。因 `suppliers.tier` 建表預設值為 1，且 `tierToImpact()` 的 default 為 2，導致：多數供應商 Impact=5、Impact=1 整欄無人落點。tier 也只反映供應鏈層級，未反映採購曝險、可替代性與後果嚴重度。

CLAUDE.md 明訂：計分邏輯（SAQ、PCF）不可寫在 esgchain-api，一律 call esgchain-ai；ESG-Chain 擁有的欄位（如 `impact_score`）不可被 ERP sync 覆蓋。本設計在此邊界內，把 Impact 重構為四因子加權計分。

## Goals / Non-Goals

**Goals:**
- Impact 由四因子（tier / spend / 單一來源 / 材料關鍵性）加權計分，落點涵蓋 1–5 全域。
- 計算邏輯集中在 esgchain-ai；esgchain-api 只讀存好的分數。
- `impact_score` 正本存 `suppliers`、快照存 `risk_assessments`，並在 tier/spend/BOM/評估四類事件觸發重算。
- spend 採可調的固定門檻，確保可稽核。

**Non-Goals:**
- 不改 Probability 計算（維持 `ceil((100 − score)/20)`）。
- 不改矩陣格分數 = P × I 與 cell 等級對應。
- 不引入即時分位數（明確排除，改用固定門檻）。
- 不新增材料關鍵性/單一來源的獨立主檔欄位（一律由現有 BOM 與法規欄位推導）。

## Decisions

### D1：計算放 esgchain-ai，正本存 suppliers、快照存 risk_assessments
Impact 四因子全部是「供應商固有屬性」，與單次 SAQ 評估的維度分數無關。因此 `impact_score` 的語意正本屬於供應商層級（`suppliers.impact_score`）。
- **矩陣（顯示當前狀態）** 讀 `suppliers.impact_score`。
- **heatmap `before_days`（歷史回溯）** 需要 point-in-time，故於建立 RiskAssessment 時把當下值快照到 `risk_assessments.impact_score`。
- **替代方案**：只存 risk_assessments。缺點是 tier/spend/BOM 變動（與評估無關）得回頭改「最新那筆評估」，等於竄改歷史 → 否決。

### D2：計算在 esgchain-ai，觸發在 esgchain-api
esgchain-api 於四類事件呼叫 esgchain-ai 的 impact 計分端點，取回 1–5 分數後寫回 `suppliers.impact_score`（並於評估時寫快照）。四因子所需資料（tier、spend、BomLineSupplier、SalesProduct 法規）目前存於 MySQL（esgchain-api），故：
- **選定**：esgchain-api 在觸發點蒐集四因子原始輸入，POST 給 esgchain-ai，ai 只負責「計分」（純函式，符合 CLAUDE.md），回傳分數。
- **替代方案**：ai 直接連 MySQL 讀資料 → 破壞資料庫歸屬（MySQL 屬 api，PostgreSQL 屬 ai），否決。

### D3：spend 固定門檻存 system_settings
沿用 heatmap 既有的 `system_settings` 機制，新增 key `impact_spend_thresholds`。預設 s5≥10,000,000 / s4≥3,000,000 / s3≥1,000,000 / s2≥300,000 / s1<300,000。
- **理由**：固定門檻可稽核、不隨供應商母體變動漂移；即時分位數會讓同一供應商在不同時點得到不同分數。
- **注意**：預設值需上線前依實際採購額分布校準。

### D4：後兩因子由現有資料推導，不新增主檔欄位
- **單一來源依賴**：對供應商供應的每條 BOM line，統計 `BomLineSupplier` 的供應商數；存在任一「僅此供應商」的 line → 5；全部多來源 → 2；無 BOM 關係 → 3。
- **材料關鍵性**：彙整該供應商涉及的 `SalesProduct` 之 `applicable_regulations` / `inferred_regulations`；命中 UFLPA 或 EUDR → 5；命中 CBAM → 4；有產品但僅一般法規 → 2；無資料 → 3。

### D5：缺值一律中性 3
四因子任一缺資料，該子分數取 3（而非 0 或最低）。避免資料不齊的供應商被誤打成高風險或無風險，也讓 `suppliers.impact_score` 為 null 時矩陣能以 Impact=3 落點。

### D6：權重固定 0.30 / 0.30 / 0.20 / 0.20
業務衝擊（tier+spend）0.6 + 後果嚴重度（單一來源+材料關鍵性）0.4，對應雙重重大性的財務/影響雙視角。先以常數實作；若日後需可調，再比照 `dim-weights-settings` 移入設定。

## Risks / Trade-offs

- [權重與門檻為經驗值] → 皆集中於一處常數/`system_settings`，上線後可依實際分布校準；spend 門檻已設計為可調。
- [BOM/法規資料稀疏使多數供應商靠中性 3 撐分] → 屬預期過渡狀態，比現況「tier 預設塌陷成 5」更中性；隨 BOM/法規資料補齊逐步提升鑑別力。
- [重算觸發遺漏造成 impact_score 過期] → 四類觸發點需在對應 Service/Observer 明確掛載；BomLineSupplier 建議用 Model Observer 統一攔截，降低遺漏面。
- [跨服務往返增加延遲] → impact 計分為輕量純函式，且只在四類事件（非畫面即時）觸發；矩陣讀取已存好的分數，不觸發計算。
- [歷史 risk_assessments 無 impact 快照] → 既有資料 `risk_assessments.impact_score` 為 null；矩陣/heatmap 對 null 以 Impact=3 落點，並提供一次性回填腳本（可選）。

## Migration Plan

1. Migration：`suppliers.impact_score`（nullable tinyInteger）、`risk_assessments.impact_score`（nullable）、seed `system_settings.impact_spend_thresholds`。
2. esgchain-ai：新增 impact 計分端點（輸入四因子原始值，輸出 1–5）。
3. esgchain-api：新增 ImpactScoreService（蒐集輸入 → call ai → 寫回）；掛載四類觸發點（評估流程、Supplier tier/spend 變動、BomLineSupplier Observer）。
4. esgchain-api：`RiskMatrixController::matrix()/matrixSuppliers()` 改讀 `impact_score`，移除 `tierToImpact()`；null 以 3 落點。
5. 一次性回填：重算全體供應商 `impact_score`（可選：回填既有最新 RiskAssessment 快照）。
6. esgchain-web：更新矩陣頁 Impact 說明文字（若有）。
7. 依 CLAUDE.md Docker 同步規則：`docker cp` 後 `docker restart esgchain-api`；ai 服務同步後重啟。
- **Rollback**：保留 `tierToImpact()` 一版於註解或 feature flag；matrix 可切回 tier 查表。`impact_score` 欄位保留不刪。

## Open Questions

- spend 固定門檻的實際幣別與量級是否為新台幣年採購額？預設值需與業務確認後校準。
- 是否需要一次性回填既有 `risk_assessments.impact_score`，或僅新評估起算即可（矩陣現況只讀最新評估，回填非必要）。
