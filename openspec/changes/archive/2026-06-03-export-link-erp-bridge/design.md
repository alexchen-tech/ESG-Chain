## Context

Phase 2 的 ERP Webhook 會推送生產批號，payload 格式為：
```json
{
  "erp_batch_no": "PRD-A-0601",
  "erp_product_code": "SAP-MAT-00123",
  "supplier_code": "GMN-001",
  "quantity": 3000
}
```

Webhook handler 需要用 `erp_product_code` 找到對應的 `buyer_product_trade_goods` 記錄，再建立 `production_batches`。但目前此表只有 UUID，無法從 ERP 料號快速匹配。

## Goals / Non-Goals

**Goals:**
- `buyer_product_trade_goods` 加入 `erp_product_code` nullable 欄位
- API 支援讀寫此欄位
- 前端 Modal 提供選填輸入

**Non-Goals:**
- ERP Webhook endpoint 本身（Phase 2a 範疇）
- ProductionBatch 建立邏輯（Phase 2a 範疇）
- erp_product_code 的唯一性驗證（Phase 2 再考慮，現在允許重複）

## Decisions

**欄位放在哪？**
放在 `buyer_product_trade_goods`（型號對應層），不放在 `buyer_products` 或 `trade_goods`。原因：同一個 BuyerProduct 可能在不同出口連結對應不同的 ERP 料號（例如 TEX-001 在歐盟出口連結用 SAP-MAT-001，在美國出口連結用 SAP-MAT-002）。

**欄位類型：** `VARCHAR(100) nullable`，不加 unique constraint（Phase 2 再評估）。

**前端：** Modal 新增「ERP 料號（選填）」輸入欄，放在備註欄之前，placeholder 說明用途。列表顯示時，若有值則在 badge 旁顯示小字。

## Risks / Trade-offs

- `erp_product_code` 現在無唯一約束，Phase 2 Webhook 匹配時若有多筆相同值需要額外邏輯處理。接受此風險，Phase 2 再加約束或匹配策略。
- 欄位選填，既有工作流程不受影響。
