## ADDED Requirements

### Requirement: ExportLink ERP 料號橋接欄位

**What**: `buyer_product_trade_goods` 新增 `erp_product_code`（nullable string, max 100）欄位，供 Phase 2 ERP Webhook 匹配用。

**Behavior**:
- 欄位選填，不填不影響既有功能
- API `store()` 接受 `erp_product_code` 參數（nullable string）
- API `index()` 回傳值包含 `erp_product_code`
- 前端 Modal 提供「ERP 料號（選填）」輸入欄

#### Scenario: 建立含 ERP 料號的出口連結
- **WHEN** 使用者在「新增出口商品連結」Modal 填入 ERP 料號後送出
- **THEN** `buyer_product_trade_goods.erp_product_code` 儲存該值

#### Scenario: 建立不含 ERP 料號的出口連結
- **WHEN** 使用者不填 ERP 料號直接送出
- **THEN** `erp_product_code` 為 null，連結正常建立，行為與修改前完全相同

#### Scenario: 列表顯示 ERP 料號
- **WHEN** 出口連結的 `erp_product_code` 不為 null
- **THEN** 在連結列表該筆顯示 ERP 料號小字（灰色），有值才顯示，無值不佔空間
