## MODIFIED Requirements

### Requirement: 產品法規欄位結構
BuyerProduct SHALL 維護兩個獨立的法規欄位：`inferred_regulations`（系統自動推算，JSON array）與 `applicable_regulations`（人工聲明，JSON array）。前端顯示時 SHALL 合併兩者為 union，並視覺區分來源（推算 vs 人工）。`applicable_regulations` 主要用於 ESPR 及系統無法自動推算的邊緣案例。

#### Scenario: 顯示合規標籤
- **WHEN** 前端渲染產品的法規標籤
- **THEN** 推算來源標籤顯示「系統」標記，人工聲明標籤顯示「手動」標記，兩者均顯示於同一標籤列

#### Scenario: 人工編輯 applicable_regulations
- **WHEN** 使用者在產品 edit modal 勾選/取消 ESPR 等法規
- **THEN** `applicable_regulations` 更新，`inferred_regulations` 不受影響

#### Scenario: 重算後人工聲明保留
- **WHEN** 系統執行 `syncProductInferredRegulations()` 更新 `inferred_regulations`
- **THEN** `applicable_regulations` 欄位內容不變
