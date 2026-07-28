## Context

出口合規現況分散在三處：出貨單層級的 EUDR DDS（ShipmentService）、產品×市場文件檢核（MarketComplianceChecker）、DPP readiness 儀表板。批號才是出口的實體單位（同批可分路多市場），需要一個批次×市場的審查中樞與對外資料包。

## Goals / Non-Goals

**Goals:**
- 批次×市場審查紀錄（一批多市場）、findings 可稽核、可重跑
- 審查引擎整合既有資料面（文件規則/溯源/PCF/DPP 欄位），不重造輪子
- 對外 API Key 認證的批次護照端點，供 DPP/報關系統拉取

**Non-Goals:**
- 不取代出貨單層級 DDS 草稿（互補：DDS 是申報文件、本功能是批次放行審查）
- 不做金鑰管理 UI（金鑰存 system_settings，換發走設定）
- 不做審查排程/自動觸發（先手動觸發，後續可掛事件）

## Decisions

- **D1 批次×市場一筆紀錄**（unique constraint），重跑 upsert——避免歷史紀錄爆炸；審查歷史需求出現時再加 append-only log。
- **D2 審查邏輯放 esgchain-api**：屬文件/資料完備度規則檢核（同 MarketComplianceChecker 先例），非計分計算，不違反「計算放 ai」原則。
- **D3 EUDR 管制原料判定**：由原料的 MaterialGroup.required_doc_types 含 `EUDR_DDS` 判定（木漿/橡膠所屬群組已具此設定），不另建清單。
- **D4 UFLPA 棉質判定**：MaterialGroup.required_doc_types 含 `UFLPA_DECLARATION`。
- **D5 對外端點獨立 middleware（ApiKeyMiddleware）**：與 JWT 全域中介層並行，路由群組獨立掛載；金鑰比對 hash_equals 防 timing attack。
- **D6 市場代碼沿用 market_compliance_rules.market**（EU/US/UK/JP），與 market_definitions.code（EU_MARKET…）的映射在 service 內處理。

## Risks / Trade-offs

- [API Key 單一金鑰粒度粗] → demo 階段可接受；正式環境可演進為多金鑰＋scope。
- [審查結果非即時（資料變動後過期）] → findings 附 reviewed_at；資料包 API 回傳審查時間讓對接方判斷新鮮度。
- [MarketComplianceChecker 以產品為單位] → 批次審查重用其產品檢核，批次特有面（溯源/PCF）由本 service 補。

## Migration Plan

1. migration：`batch_export_reviews` + `system_settings.export_api_key` seed
2. `BatchExportReviewService` + 內部端點 + ApiKeyMiddleware + 對外端點
3. UI：批號 Drawer 出口市場審查區塊
4. Seeder：demo 審查（EU/US 各數批，含 pass 與 fail 案例）
5. Docker 同步 + 冒煙測試

## Open Questions

- 金鑰換發流程與到期策略（demo 先固定金鑰）。
