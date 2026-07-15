## Why

目前「產品清單」頁（BuyerProductsView）的展開互動分為兩個獨立 panel：點主列展開 AVL 供應商清單、點「BOM 明細」按鈕展開 BOM 表格。此設計造成兩個問題：(1) 使用者不清楚 AVL 供應商與 BOM 指定供應商的差異與連動關係；(2) BOM 明細是合規計算的唯一來源，卻在 UI 上地位與 AVL 相同，語意錯誤。

## What Changes

- **移除點主列展開 AVL panel**：主列點擊改為直接開啟 BOM Panel，取消目前展開 AVL 的行為
- **BOM Panel 成為唯一主展開**：BOM 表格升格為產品的主要展開內容
- **BOM 表格每列新增 BomLineSupplier 行內管理**：每個 BOM 明細列可展開顯示 primary / alternate 供應商，並支援新增、移除 BomLineSupplier
- **AVL 移至 BOM Panel 底部**：降格為輔助區塊，標示「此產品的已認可供應商（AVL）」，說明 AVL 廠商需透過 BOM 指定才有合規效果
- **產品摘要列調整**：供應商數量泡泡改為可點擊，展開 AVL popover／inline 區塊；BOM 數量直接顯示且不再需要點擊才載入

## Capabilities

### New Capabilities

- `bom-line-supplier-management`: BOM 明細列行內管理 BomLineSupplier（新增 primary/alternate 供應商、移除）

### Modified Capabilities

- `buyer-product-bom-view`: 展開互動從「雙 panel 分離」改為「BOM 為主、AVL 為輔」的單一展開結構
- `bom-line-supplier-avl`: AVL 管理移至 BOM Panel 底部，角色從並列降為輔助

## Impact

- `esgchain-web/src/views/compliance/BuyerProductsView.vue`：主要改動檔案，重構展開邏輯、BOM 表格結構、AVL 區塊位置
- `esgchain-api/app/Http/Controllers/Api/Compliance/BomLineSupplierController.php`：新增 Controller 處理 BomLineSupplier CRUD
- `esgchain-api/routes/api.php`：新增 BomLineSupplier 路由
- `esgchain-web/src/api/modules/compliance.ts`：新增 BomLineSupplier API 呼叫函數
