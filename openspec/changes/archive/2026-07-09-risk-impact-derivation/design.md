## Context

`RiskAutoDerivationService::deriveFromSaq()` 在 SAQ 計分 callback 時自動建立 `RiskAssessment`，目前將所有維度的 `impact` 固定設為 3。矩陣最高分因此為 5×3=15（high 上限），`RiskAssessmentObserver` 的 extreme CAP 觸發（需 ≥20）對 AI 自動產生的 RA 永遠不成立。

Impact 的語意是「這家供應商出問題對採購商的業務衝擊有多大」，應由**採購商側的依賴程度**決定，與供應商自身的 ESG 得分（probability 的來源）無關。現有資料中有兩個可用信號：`supplier.tier`（直接量化依賴層級）和 `supplier.country_code`（量化地緣/勞工/環境背景風險）。

## Goals / Non-Goals

**Goals:**
- 建立 `country_risk_ratings` 主檔表，含 labor / env / geo 三維度評等（1–5）
- 修改 `deriveFromSaq()` 使各維度 impact 動態計算
- Settings 頁面提供 CRUD 讓 admin/sustain 維護國家評等
- Seed 初始資料覆蓋現有 demo 供應商國家

**Non-Goals:**
- 不修改 `{dim}_probability` 的換算邏輯（仍從 SAQ 分數推導）
- 不修改手動建立的 RiskAssessment 介面
- 不整合外部風險資料庫 API（初始值人工維護）
- 不回算已存在的歷史 RiskAssessment

## Decisions

### Decision 1：impact 公式

```
tier_weight = { 1→2, 2→1, 3→0 }[supplier.tier] (預設 1)

s_impact  = clamp(country.labor_risk + tier_weight, 1, 5)
e_impact  = clamp(country.env_risk   + tier_weight, 1, 5)
g_impact  = clamp(tier_weight + 2,                  1, 5)
gp_impact = clamp(country.geo_risk,                 1, 5)
```

**G 維度 impact 只用 tier_weight + 2**：G（公司治理）的衝擊主要來自採購集中度，而非國家環境；用 tier 足夠表達。

**country 查無記錄時 fallback = 3**：保持與舊行為一致，不中斷流程。

**替代方案考量**：
- 用 spend_amount 計算 impact → 量級不一致（demo 用百萬台幣），維護難度高，先排除
- 用 iso26k category_scores 計算 impact → 混淆了 probability（供應商表現）和 impact（對我方衝擊）語意，排除

### Decision 2：country_risk_ratings 放在 MySQL（esgchain-api）

設定主檔由 Laravel API 管理，符合「ESG-Chain 擁有」的 ESG 情報層定位。PostgreSQL（esgchain-ai）只儲存評分模型資料，不管設定。

### Decision 3：Settings 頁面子路由，非獨立模組

國家風險評等屬於系統設定，放在現有 `/settings` 路由下作為子頁面（`/settings/country-risk`），RBAC 限 `admin` 和 `sustain` 角色。

### Decision 4：不回算歷史 RA

歷史 RA 是稽核軌跡，不應被新公式靜默覆蓋。使用者若需要，可手動觸發重新評估。

## Risks / Trade-offs

- **Observer CAP 觸發率上升**：extreme 條件對 AI 自動 RA 現在可能成立（例如 BD tier 1 供應商 S 維度 = 4×5=20）。Observer 已有冪等保護（`source_id` 唯一），不會重複建 CAP，但採購商可能突然看到更多 CAP 警示。→ 在 Seed 資料驗證時確認 CAP 數量合理。

- **country fallback = 3 掩蓋資料缺漏**：若 Seeder 未覆蓋某國，impact 默默退回 3 不報錯。→ 在 `deriveFromSaq()` 加入 Log::info 記錄 fallback 事件。

- **tier 欄位可為 null**：現有 Supplier 有 tier = null 的情況。→ `tier_weight` null 時視為 0（等同 tier 3）。

## Migration Plan

1. 執行 migration 建立 `country_risk_ratings` 表
2. 執行 Seeder 填入初始國家評等
3. 部署 `RiskAutoDerivationService` 修改（不影響已有資料）
4. 部署 Settings API + Vue 頁面

回滾：`country_risk_ratings` 表可直接 drop，`deriveFromSaq()` 回退 impact=3 fallback 即可恢復舊行為。

## Open Questions

- 初始國家評等的依據來源：由 PM 對齊 ITUC/WJP 標準後填入，或先以判斷值 seed？（目前先用判斷值，標記 `source = 'manual'`）
