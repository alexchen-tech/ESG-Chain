## Why

現有風險矩陣以單一 E/S/G/GP 座標呈現供應商風險，無法同時表達「供應商本身的永續健康狀態」與「特定商品出口至特定市場的法規暴露」這兩個語義截然不同的風險面向。採購商、法遵部門在做「補文件」或「換供應商」決策時，缺乏從合規缺口直接連結到供應商風險的量化依據。

## What Changes

- **新增** 永續風險三軸雷達（ESG 暴露 / 治理成熟度 / 地緣產業），取代現有單一 E/S/G/GP 矩陣，作為 Dashboard A
- **新增** 商品 × 市場出口風險熱力圖（雙入口：從商品看市場暴露、從市場看商品暴露），作為 Dashboard B
- **新增** 路徑風險分計算：`Chain_Risk × 市場法規放大係數`，碳排占比來自 PcfSnapshot，缺失時 fallback emission_factor 並標記高風險
- **新增** 替換供應商推薦（層次 2）：從義務缺口面板觸發，依「路徑風險改善幅度」排序同 HS Code 不同來源國候選
- **新增** Multi-tag SAQ 混合範本機制：單一問卷同時掛 iso26k.* / iso20400.* slug，計分引擎一次輸出三軸分數
- **BREAKING** 移除現有獨立 ISO 20400 / ISO 26000 / ESG 範本，SAQ 專案從 Multi-tag 混合範本重建
- **修改** `saq-to-risk-auto-derivation`：自動推導輸出擴展為三軸分（軸1 ESG暴露、軸2 治理成熟度）

## Capabilities

### New Capabilities

- `sustainability-risk-radar`: 永續風險三軸雷達——ESG暴露（iso26k.*）、治理成熟度（iso20400.*）、地緣產業（geo_risk.* 手動）的計算、儲存與視覺化
- `trade-export-risk-heatmap`: 商品 × 市場出口風險熱力圖——路徑風險分計算、雙入口互動、義務缺口明細面板
- `supplier-replacement-recommendation`: 替換供應商推薦——從義務缺口觸發、同HS Code異來源國候選、路徑風險改善幅度排序
- `multi-framework-saq-template`: Multi-tag 混合問卷範本——單一範本同時支援多框架 slug 標記、計分引擎多軸輸出

### Modified Capabilities

- `saq-to-risk-auto-derivation`: 自動推導輸出從 E/S/G probability 擴展為三軸雷達分（軸1、軸2）；軸3 不自動推導，保持手動
- `saq-scoring-engine`: 計分引擎需支援同一 SAQ 一次計算多框架分數輸出（multi-framework scoring）
- `trade-good-market-compliance`: 合規狀態（pass/warning/fail）升級為量化路徑風險分，並加入供應鏈碳排加權邏輯

## Impact

- **esgchain-ai**：`scoring_service.py` 擴展多框架輸出；新增路徑風險計算端點 `POST /ai/v1/path-risk`；新增替換模擬端點 `POST /ai/v1/supplier-replacement-candidates`
- **esgchain-api**：`RiskAssessment` model 擴展三軸欄位；`MarketComplianceChecker` 整合路徑風險分；新增 `GET /api/v1/trade-goods/export-risk-matrix`
- **esgchain-web**：新增 Dashboard A（永續風險，sustain/analyst 角色）、Dashboard B（出口風險，comply/buyer 角色）；供應商詳情頁整合三軸雷達與出口路徑風險列表
- **資料**：現有 SAQ 範本移除，相關 SaqProject 需從 Multi-tag 範本重建；PcfSnapshot 碳排明細作為路徑風險計算輸入
