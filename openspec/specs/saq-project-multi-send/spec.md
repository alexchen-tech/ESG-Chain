# Spec: SAQ Project Multi-Supplier Send

## Overview

從 SaqProject 詳情頁一次發送問卷給多位供應商，支援群組批次選擇與個別搜尋兩種模式。

## Requirements

### Backend

- `POST /api/v1/saq-projects/{id}/send`（擴充現有端點）
  - Request body: `{ "supplier_ids": ["uuid", ...], "note": "optional" }`
  - 驗證：
    - project status 必須為 draft 或 active（closed 回 422）
    - 每個 supplier_id 必須存在
    - 已存在 SAQ 的供應商：跳過（不重複建立），response 中標記 `skipped`
  - 行為：批次建立 SAQ 記錄，project status 若為 draft 則轉為 active
  - Response: `{ success: true, created: N, skipped: M, data: [SAQ 陣列] }`

### Frontend: 發送 Modal

- 兩個 Tab：「選擇群組」/ 「搜尋供應商」
- **群組 Tab**：顯示所有 SupplierGroup，勾選後展開供應商清單，可全選/反選
- **搜尋 Tab**：即時搜尋框，輸入供應商名稱/代碼，結果以 checkbox 列表顯示
- 已在本專案收到問卷的供應商：顯示但標記「已發送」，禁止勾選
- 底部顯示「已選 N 家」，確認按鈕呼叫 send API
- 送出成功後顯示摘要：「已發送 N 家，跳過 M 家（已發送過）」

## Acceptance Criteria

- [ ] 可透過群組一鍵選取群組內所有未發送供應商
- [ ] 重複供應商（已在本專案收到問卷）不可被選取，UI 有明確標記
- [ ] 送出後詳情頁供應商列表立即更新
- [ ] project status 從 draft 變為 active 後，列表頁 Tab 計數更新
