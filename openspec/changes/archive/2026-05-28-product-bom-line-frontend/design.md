## Context

ESG-Chain 前端使用 Vue 3 Options API + Pinia，設計語言為 Warm Paper Light。現有的 BuyerProductsView 已有卡片清單佈局、可展開的供應商 panel，以及新增/刪除供應商關聯的功能。SupplierComplianceDetailView 已有合規文件列表與供應材料清單兩個 section。供應商 Portal 目前只有 SAQ 問卷填寫功能。

## Goals / Non-Goals

**Goals:**
- 在現有 BuyerProductsView 卡片中內嵌 BOM 明細管理（不跳頁）
- 在 SupplierComplianceDetailView 中以 section 呈現被哪些採購產品引用
- 供應商 Portal 新增「採購需求」頁，匿名顯示客戶產品需求與合規缺口
- 所有列表實作 loading / empty state

**Non-Goals:**
- 不實作 BOM 版本歷史 UI
- 不實作批量編輯 BomLines
- 不修改 ERP 整合設定頁（無此頁面）
- 不修改 SAQ 或 CAP 模組

## Decisions

### D1：BOM 管理內嵌在產品卡片（不跳頁）

**決定**：BOM section 作為卡片的第三展開層，在「供應商關聯」panel 下方。

**理由**：跳頁會失去上下文，且 BOM 明細是產品附屬資料，內嵌符合現有卡片展開模式。BOM section 預設收合，點擊「BOM 明細」標籤展開。

```
ProductCard
  ├─ card-header（產品名、法規標籤、操作按鈕）
  ├─ supplier-panel（展開層 1：供應商關聯）
  └─ bom-panel（展開層 2：BOM 明細）
       ├─ toolbar（CSV 匯入按鈕、新增按鈕）
       └─ bom-table（物料名、HS Code、物料群組、指定供應商、數量/單價）
```

### D2：BomLine 行內編輯（inline edit）

**決定**：BomLine 列表支援點擊行進入 inline edit 模式，不用 modal。

**理由**：BomLine 欄位多，modal 也難以全部容納；inline edit 讓使用者直接在表格中修改，更直覺。新增時在表格頂部插入一列空白 input row。

### D3：CSV 匯入 UX — 上傳 + 預覽確認

**決定**：CSV 匯入分兩步：上傳後先顯示解析預覽（created/updated 預計數量），使用者確認後再送出。

**理由**：避免誤上傳大量錯誤資料，讓使用者有機會檢查。

### D4：Portal 採購需求頁匿名化

**決定**：客戶產品以「客戶產品 #N」呈現（N 為流水號，不洩漏實際名稱），顯示：物料名稱、物料群組、required_doc_types、目前已提交文件狀態。

**理由**：探索模式已決定——採購商的產品名稱/編號屬商業機密，供應商只需知道「我需要為哪個物料準備哪些文件」。

### D5：Portal 路由與佈局

**決定**：新增路由 `/supplier/portal/procurement`，使用現有 `SupplierLayout`（無 Sidebar 的 Portal 專屬佈局）。在 Portal 頂部 nav 加入「採購需求」連結。

## Risks / Trade-offs

- **[風險] BomLine inline edit 複雜度**：多欄位同時編輯、下拉選單（物料群組、指定供應商）可能影響效能 → 緩解：供應商下拉僅載入該產品已關聯的供應商，物料群組使用現有 materialGroupApi
- **[風險] Portal 匿名化 API 設計**：需要後端提供 `/supplier/portal/procurement-requirements` endpoint（目前尚未在 backend change 中規劃）→ 標記為 open question，前端先以 mock 開發，backend 補齊後接入

## Open Questions

- Portal 採購需求的 API endpoint 是否應包含在 product-bom-line-backend change，或另立 portal-supplier-requirements change？
- BomLine inline edit 的物料群組下拉，是否需要「依 HS Code 自動推斷」的即時提示？
