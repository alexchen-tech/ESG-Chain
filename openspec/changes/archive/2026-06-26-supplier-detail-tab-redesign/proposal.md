## Why

供應商明細頁目前有 6 個以「資料分類」為軸的 Tab，但主要使用者（sustain / comply / buyer）每次開啟明細頁的工作情境不同，需要在多個 Tab 之間跳轉才能完成一個任務。此外，Disclosure Profile 因 DOM 結構錯誤（內容插在供應材料清單與合規文件之間），Tab 切換完全失效。

## What Changes

- 將 6 個資料分類 Tab 重構為 4 個工作情境 Tab：**概況**、**永續績效**、**合規管理**、**設施 & 聯絡**
- **概況**（預設 Tab）：將風險評估（E/S/G/GP scorecard）移至首位，合併識別資訊、產業分類、管理歸屬等稀疏 Tab
- **永續績效**：整合問卷記錄與 Disclosure Profile KPI 時間序列
- **合規管理**：將供應材料清單（BOM → required doc types）與合規文件（actual doc status）並列，明確呈現因果鏈
- **設施 & 聯絡**：整合聯絡人、地址/網站、生產設施、申報記錄、狀態歷程 Timeline
- **BREAKING**：修復 Disclosure Profile DOM 位置 bug（原本夾在供應材料清單和合規文件兩個 always-visible section 之間，Tab 切換無效）

## Capabilities

### New Capabilities

- `supplier-detail-overview-tab`：概況 Tab — 風險評估首位 + 識別/產業/管理歸屬合一展示
- `supplier-detail-compliance-tab`：合規管理 Tab — BOM 需求清單因果鏈接合規文件，同頁對照

### Modified Capabilities

- `supplier-compliance-status`：合規文件的展示從獨立 Tab 移至合規管理 Tab，與供應材料清單同頁，行為不變但 DOM 位置改變
- `supplier-bom-requirement-view`：供應材料清單移入合規管理 Tab，不再是 always-visible section
- `supplier-group-compliance-docs`：Disclosure Profile 從 DOM 錯誤位置移至永續績效 Tab，Tab 切換邏輯修正

## Impact

- **前端**：`esgchain-web/src/views/suppliers/SupplierDetailView.vue` — 主要改動文件
- **無 API 變更**：所有資料已由現有 API 提供，Tab 重構為純前端重排
- **RBAC 無異動**：頁面可存取角色維持 `admin / buyer / sustain / comply / analyst`
