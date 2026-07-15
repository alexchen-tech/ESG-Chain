## Context

現有 `DashboardView.vue` 是單一頁面，對所有角色呈現相同的 4 張統計卡片（供應商總數、待審問卷、逾期 CAP、極高風險）加上供應商狀態分布與最近供應商列表。後端無專屬 dashboard API，前端直接呼叫各模組 API 並在 `mounted()` 中並行請求。

系統已有豐富資料基礎：`supplier_status_histories`（供應商狀態變更稽核）、`supplier_compliance_docs`（含 `expires_at`、`status`）、`trade_goods`（含 `is_cbam_applicable`、`embedded_emissions`）、`caps`（含 `due_date`、`status`）、`saqs`（含 E/S/G 分數）。

## Goals / Non-Goals

**Goals:**
- 依角色（sustain / buyer / comply / admin）動態渲染不同 Widget 組合
- 新增 4 支 dashboard 專屬 API，避免前端自行聚合多支 API 的 N+1 問題
- 「最近動態」改為事件驅動（狀態變更、文件到期、SAQ 提交、CAP 更新）
- 「合規文件到期預警」以 7 天為閾值，時間軸呈現
- CBAM 風險金額 = `embedded_emissions × carbon_price_eur`，碳價從系統設定讀取
- 新增「系統碳價假設」設定頁（admin 管理）

**Non-Goals:**
- 不實作 WebSocket 即時更新（維持頁面載入時一次性請求）
- 不實作使用者自訂 Widget 拖拉排序
- 不實作歷史趨勢折線圖（本次只做數字彙總與清單）
- admin 角色維持管理員視角，不做全局鳥瞰 Tab

## Decisions

### 決策 1：Widget 配置在前端還是後端？

**選擇：前端 computed 配置，後端提供角色感知的聚合資料。**

前端 `DashboardView.vue` 透過 `widgetConfig` computed（依 `authStore.user.role`）決定渲染哪些 Widget。後端 API 在 JWT claims 中已有 `roles`，可在 Service 層依角色過濾回傳欄位。

捨棄純後端配置（單一 endpoint 回傳所有 widget 資料）的原因：前端 Widget 渲染邏輯本就因角色而異，在前端決策更直覺，且避免後端回傳大量前端不需要的欄位。

### 決策 2：Dashboard API 結構

新增 `DashboardController`，包含以下 4 支 endpoint：

```
GET /api/v1/dashboard/summary          → 今日行動卡片數字（依角色過濾）
GET /api/v1/dashboard/recent-activity  → 過去 7 天有事件的供應商清單
GET /api/v1/dashboard/expiring-docs    → 7 天內到期的合規文件清單
GET /api/v1/dashboard/compliance-risk  → 商品合規風險彙總（含 CBAM 金額）
```

`DashboardService` 統一處理聚合邏輯，Controller 僅做請求轉發，符合現有 Laravel 慣例。

### 決策 3：CBAM 風險金額計算

`carbon_price_eur` 存放於 `system_settings` 表（key-value 結構），由 admin 在設定頁管理。計算時：

```
cbam_risk_amount_eur = embedded_emissions (tCO₂e) × carbon_price_eur (€/tCO₂e)
```

`system_settings` 表若不存在需新建 migration（key VARCHAR, value TEXT, updated_by UUID）。若已存在（Settings 模組使用中）則直接新增 key。

### 決策 4：「最近動態」事件來源

事件聚合查詢邏輯：

| 事件類型 | 資料來源表 | 查詢條件 |
|---------|-----------|---------|
| 供應商狀態變更 | `supplier_status_histories` | `created_at >= now()-7days` |
| SAQ 提交 | `saqs` | `submitted_at >= now()-7days` |
| CAP 狀態更新 | `caps` | `updated_at >= now()-7days AND status changed` |
| 文件到期（7天內） | `supplier_compliance_docs` | `expires_at BETWEEN now() AND now()+7days` |
| 文件新上傳 | `supplier_compliance_docs` | `created_at >= now()-7days` |

回傳統一格式：`{ supplier_id, supplier_name, event_type, event_label, occurred_at, severity }`，前端以 severity 決定圖示（`●` 狀態變化 / `!` 需注意 / `○` 等待中）。

### 決策 5：Widget 角色對應

```
sustain → ActionCards(saq_pending, cap_due_7d, cert_expiring_7d)
          RecentActivity
          ExpiringDocs
          EsgScoreDistribution

buyer   → ActionCards(doc_expiring_7d, high_risk_suppliers, pending_review)
          RecentActivity
          ExpiringDocs
          SupplierTierDistribution

comply  → ActionCards(compliance_issues, cbam_products, eudr_pending)
          RecentActivity
          ExpiringDocs
          ComplianceRiskSummary (含 CBAM 金額)

admin   → ActionCards(全部 6 個數字)
          RecentActivity
          ExpiringDocs
          SupplierStatusDistribution（維持現有）
```

### 決策 6：系統碳價設定頁路由與權限

- 路由：`/settings/carbon-price`（已在 settings 群組，admin only）
- Sidebar：加入 settings 群組，`roles: ['admin']`
- API：`GET/PUT /api/v1/settings/carbon-price`

## Risks / Trade-offs

- **[Risk] `system_settings` 表不存在** → 先 grep 確認，若不存在則建 migration；若已存在確認 key-value 結構相容
- **[Risk] `recent-activity` 聚合 5 種來源可能較慢** → 各表均有 `created_at`/`updated_at` 索引；限制回傳 20 筆，加 `LIMIT` 避免過大結果集
- **[Risk] `embedded_emissions` 為 null 的商品** → CBAM 金額計算時跳過 null 值，UI 顯示「未填報」提示
- **[Trade-off] Widget 配置在前端** → 後端 API 目前回傳角色無關的聚合資料，若未來需要細粒度權限控制需重構；現階段 RBAC 已由 JWT middleware 保護 endpoint，風險可接受
