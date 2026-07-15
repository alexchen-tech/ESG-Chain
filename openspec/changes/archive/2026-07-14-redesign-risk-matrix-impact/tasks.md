## 1. 資料庫變更（esgchain-api）

- [x] 1.1 Migration：`suppliers` 新增 `impact_score`（nullable tinyInteger，1–5），欄位註解標明屬 ESG-Chain 擁有、ERP sync 不可覆蓋
- [x] 1.2 Migration：`risk_assessments` 新增 `impact_score`（nullable，作 point-in-time 快照）
- [x] 1.3 Seed / Migration：`system_settings` 新增 key `impact_spend_thresholds`，預設 `{"s5":10000000,"s4":3000000,"s3":1000000,"s2":300000}`
- [x] 1.4 更新 `Supplier` model `$fillable` / `$casts`（`impact_score`），確認 ERP sync 邏輯不覆寫此欄位

## 2. Impact 計分引擎（esgchain-ai）

- [x] 2.1 新增 Pydantic Schema：`ImpactScoreRequest`（tier、spend_amount、spend_thresholds、has_single_source、regulation_hits）、`ImpactScoreResponse`（impact_score 1–5、各因子子分數）
- [x] 2.2 新增純函式計分服務：四因子子分數對應 + 缺值中性 3 + 權重 0.30/0.30/0.20/0.20 + `clamp(1..5, round(...))`
- [x] 2.3 新增路由 `POST /ai/v1/impact-scoring`（kebab-case），回傳分數與子分數明細
- [x] 2.4 單元測試：四因子皆有值、全缺值→3、邊界四捨五入、clamp 上下限

## 3. Impact 觸發與寫回（esgchain-api）

- [x] 3.1 新增 `ImpactScoreService`：蒐集供應商四因子原始輸入（tier、spend、由 BomLineSupplier 推導單一來源、由 SalesProduct 法規推導關鍵性），call esgchain-ai，寫回 `suppliers.impact_score`
- [x] 3.2 單一來源推導：依供應商的 BOM line 統計 `BomLineSupplier` 供應商數，判定是否存在僅此供應商的 line
- [x] 3.3 材料關鍵性推導：彙整供應商涉及 `SalesProduct` 的 `applicable_regulations`/`inferred_regulations`，對應 UFLPA·EUDR→5 / CBAM→4 / 一般→2
- [x] 3.4 觸發點：SAQ→風險評估流程建立 RiskAssessment 前重算 `impact_score`，並將當下值快照寫入 `risk_assessments.impact_score`
- [x] 3.5 觸發點：Supplier `tier` 或 `spend_amount` 變動時重算（含 ERP sync 路徑）
- [x] 3.6 觸發點：`BomLineSupplier` 新增/刪除/角色變動時，重算受影響供應商（建議用 Model Observer）

## 4. 矩陣改讀 impact_score（esgchain-api）

- [x] 4.1 `RiskMatrixController::buildMatrix()` 與 `matrixSuppliers()` 改用 `suppliers.impact_score` 作為 Impact，移除 `tierToImpact()`
- [x] 4.2 供應商 `impact_score` 為 null 時，以 Impact=3 落點
- [x] 4.3 確認 `matrixSuppliers` 回傳 payload 仍包含供應商 impact 明細（供前端顯示）

## 5. 前端（esgchain-web）

- [x] 5.1 風險矩陣頁若有 Impact 來源說明文字，更新為四因子加權說明（繁體中文）
- [x] 5.2 若供應商卡片顯示 Impact，改讀新的 `impact_score` 並標示為 1–5

## 6. 回填、驗證與部署

- [x] 6.1 一次性腳本：重算全體供應商 `impact_score`；（可選）回填既有最新 RiskAssessment 的 `impact_score` 快照
- [x] 6.2 驗證矩陣落點涵蓋 1–5 全域，Impact=1 不再為死格、Tier1 預設不再全塌成 5
- [x] 6.3 依 CLAUDE.md Docker 同步規則部署（`docker cp` 後 `docker restart esgchain-api`；ai 服務同步後重啟）並冒煙測試登入與矩陣端點
