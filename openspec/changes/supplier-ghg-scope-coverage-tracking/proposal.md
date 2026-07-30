## Why

供應商填報範疇一/二/三排放量的主要用途，經使用者釐清後定調為**稽核供應商是否有做碳盤查、做到哪個範圍**（成熟度稽核），不是要 ESG-Chain 自己精確計算供應商排放量。現有 `supplier_disclosure_fields` 已有 `ghg.scope1_mt_co2e`／`ghg.scope2_mt_co2e` 兩個揭露欄位且有真實填報資料，但缺 `ghg.scope3_mt_co2e`，且中心廠端沒有任何「這家供應商盤查做到哪裡」的彙總稽核視圖。

## What Changes

- 新增 `ghg.scope3_mt_co2e` 揭露欄位（比照既有 scope1/scope2 欄位定義）
- 新增中心廠端「供應商碳盤查覆蓋度」稽核視圖：依供應商列出範疇一/二/三各自「已填報」或「未填報」（依 `SupplierDisclosure` 是否存在對應 slug 與期間的紀錄判斷，不做第三方查證/方法論欄位）
- 這次範圍明確只做「有沒有填」層級的判斷，不新增驗證機構、盤查方法論、邊界說明等欄位（經使用者確認，這些留待未來有需要再追加）

## Capabilities

### New Capabilities
- `supplier-ghg-scope-coverage-tracking`：供應商範疇一/二/三揭露覆蓋度稽核

## Impact

- 資料庫：`supplier_disclosure_fields` 新增一筆 `ghg.scope3_mt_co2e` 種子資料
- 後端：新增覆蓋度彙總查詢 Service/API
- 前端：新增稽核視圖頁面；供應商 Portal 永續 KPI 填報頁自動出現範疇三欄位（沿用既有揭露欄位渲染機制，不需額外前端改動）
- 不影響：既有 scope1/scope2 資料與既有揭露填報流程
- 明確排除範圍：不做第三方查證機構、盤查方法論、邊界說明欄位；不做 ESG-Chain 內部精算供應商排放量
