## Context

現有系統在 `TradeGood` 上存放靜態合規旗標（`is_cbam_applicable`、`is_eudr_applicable`），這些旗標由 HS code 或上游物料群組推算，**不考慮目標市場**。這導致出口到美國的木材商品仍顯示 EUDR 警告（假陽性），無法支援「選擇目標市場 → 確認合規義務」的正確工作流程。

合規義務的正確計算需要三層資訊：
1. **物料層**：上游供應商所屬物料群組的 `required_doc_types`（已有）
2. **市場規則層**：哪個市場要求哪些文件（缺失，本次新增）
3. **交集計算層**：物料文件需求 ∩ 市場規則 → 實際義務（缺失，本次新增）

## Goals / Non-Goals

**Goals:**
- 建立 `market_compliance_rules` 資料表與管理 UI，讓 admin 可維護市場 × doc_type 映射
- 實作 `MarketComplianceChecker` 服務，動態計算 TradeGood × target_market 的合規狀態
- 在 `TradeGoodsView` 加入 target_market 篩選，展示每項商品的市場合規結果
- 預置 EU / US / APAC 三個市場的基本規則種子資料

**Non-Goals:**
- 不實作複雜的法規版本管理（effective_from 記錄歷史，但 UI 只顯示當前有效規則）
- 不整合外部法規資料庫 API
- 不自動判定 HS code 是否在各國禁止清單（只管文件類型層面）
- Shipment 層的 CBAM 申報計算（碳排量加總）不在本次範圍

## Decisions

### D1：market_compliance_rules 放 MySQL（esgchain-api）而非 PostgreSQL（esgchain-ai）

規則是業務設定資料，屬於流程管理範疇，與 Laravel/MySQL 的其他業務設定一致。計算結果（MarketComplianceChecker 輸出）是即時計算，不需要存 PostgreSQL。

### D2：MarketComplianceChecker 為同步計算，不走 Celery

單次檢核只需查詢本機 MySQL（`market_compliance_rules` + `supplier_compliance_docs`），延遲 < 100ms，不需要非同步任務。

### D3：`is_eudr_applicable` 從 TradeGood 移除，`is_cbam_applicable` 重新定義語意

`is_eudr_applicable` 靜態旗標語意本就錯誤（市場相關），予以移除。`is_cbam_applicable` 改名為 `cbam_eligible`（向後相容：API response 同時回傳兩個名稱過渡期），語意為「HS code 屬於 CBAM 品類，出口 EU 時可能觸發申報義務」。

### D4：合規檢核結果不持久化，每次 API 請求即時計算

結果依賴 SupplierComplianceDoc 現況（隨時可更新），持久化會有失效問題。TradeGoodsView 批次查詢改為前端選定市場後 lazy-load 每筆商品的合規結果。

### D5：market 欄位用字串 enum（EU / US / NA / APAC / GB / JP）

延續 Shipment.target_market 現有值域，不另外建立 markets 主檔表（避免過度設計）。UI 顯示完整名稱，DB 存代碼。

## Risks / Trade-offs

**[風險] 前端批次合規查詢造成 N+1 請求** → 緩解：TradeGoodsView 選定市場後，一次呼叫批次端點 `POST /api/v1/trade-goods/market-compliance-batch`（body: `{ market, trade_good_ids[] }`），後端一次查詢所有相關 docs，避免 N 次請求。

**[風險] `is_eudr_applicable` 移除影響現有 Shipment addLine() 邏輯** → 緩解：ShipmentService::addLine() 改呼叫 MarketComplianceChecker，以 shipment.target_market 動態判定是否需要 EUDR DDS，取代靜態旗標。

**[取捨] market_compliance_rules 由 admin 手動維護** → 接受：法規異動頻率低（每年數次），手動維護可行。未來可加 webhook 或外部同步。

## Migration Plan

1. 執行 migration 建立 `market_compliance_rules` 資料表
2. 執行 `MarketComplianceRuleSeeder` 植入 EU/US/APAC 預設規則
3. 部署後端（MarketComplianceChecker、Controller、Routes）
4. 前端部署（MarketComplianceRulesView、TradeGoodsView 更新）
5. 驗證：TradeGoodsView 選 EU → 木材商品顯示 EUDR 缺文件警告；選 US → 不顯示 EUDR
6. `is_eudr_applicable` DB 欄位保留（nullable）但不再寫入，transition period 後可移除

## Data Model

```
market_compliance_rules
───────────────────────────────────────────────────────────
id              UUID PK
market          VARCHAR(10)   EU / US / NA / APAC / GB / JP
doc_type        VARCHAR(50)   EUDR_DDS / CBAM_REPORT / UFLPA_DECLARATION / ...
is_mandatory    BOOLEAN       true = 強制義務, false = 建議
effective_from  DATE          法規生效日
notes           TEXT NULL     說明、法規條文參考
is_active       BOOLEAN       false = 停用（軟停）
created_at / updated_at

UNIQUE(market, doc_type)
INDEX(market, is_active)
```

```
MarketComplianceChecker::check(TradeGood $good, string $market): array
─────────────────────────────────────────────────────────────────────
1. 取 good.tradeGoodSuppliers → 各 supplier 的 materialGroup.required_doc_types
   → union → $materialDocTypes[]

2. if market == 'EU' and good.cbam_eligible → push 'CBAM_REPORT' to $materialDocTypes

3. $rules = MarketComplianceRule::where(market, is_active=true, effective_from<=today)
              ->whereIn('doc_type', $materialDocTypes)->get()

4. foreach $rules as $rule:
     $doc = SupplierComplianceDoc::where(trade_good_id or supplier link)
                                    ->where(doc_type, $rule->doc_type)
                                    ->latest()->first()
     status = missing | expired | expiring_soon | valid

5. return [
     'market'   => $market,
     'required' => [...],   // rule records
     'results'  => [{ doc_type, status, expires_at, supplier_name }],
     'overall'  => 'pass' | 'warning' | 'fail',
   ]
```
