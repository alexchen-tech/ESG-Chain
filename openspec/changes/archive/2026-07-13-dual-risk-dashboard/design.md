## Context

現有風險評估架構：
- `RiskAssessment` model 以 E/S/G/GP 四個維度（probability × impact）呈現供應商風險，GP 為手動填報
- `saq-to-risk-auto-derivation`：SAQ 評分完成後自動推導 E/S/G probability（impact 固定為 3）
- `MarketComplianceChecker`：商品 × 市場輸出 pass/warning/fail，不含量化風險分
- SAQ 範本為單一框架（scoring_framework 單值），ISO 20400 / ISO 26000 / ESG 各自獨立問卷
- `QuestionTag` 已有 L1/L2/L3 slug 階層（iso26k.*、iso20400.*、geo_risk.* 等）

破壞性變更：移除現有獨立框架範本，SAQ 專案從 Multi-tag 混合範本重建。

## Goals / Non-Goals

**Goals:**
- Dashboard A（永續風險）：三軸雷達以供應商為主詞，同一 Multi-tag 問卷一次輸出三軸分
- Dashboard B（出口合規）：商品 × 市場熱力圖，路徑風險分量化，缺口明細可觸發補文件或換供應商
- 路徑風險分：`Σ(ESG暴露_i × 碳排占比_i) × 市場法規放大係數`，碳排缺失 fallback emission_factor 並標記
- 替換供應商推薦：同 HS Code、異來源國、有 SAQ 分，依路徑風險改善幅度排序
- Multi-tag 計分：scoring_service 支援同一 SAQ 多次 filter 輸出多框架分數

**Non-Goals:**
- 軸3 地緣產業風險不做自動計算（外部資料依賴），維持結構化手動輸入
- 替換供應商不觸發 ERP 採購流程（ESG-Chain 僅提供決策支援，實際換源在 ERP）
- 路徑風險分不納入金額占比（金額為 ERP 資料，不在 ESG-Chain 範圍）
- 現有 PcfSnapshot 不重算，路徑風險使用最近一次有效 snapshot

## Decisions

### D1：Multi-tag 範本的 scoring_framework 欄位改為 multi-framework

**決定**：`saq_templates.scoring_framework` 新增枚舉值 `"multi-framework"`，代表此範本支援多框架 slug 混合標記。計分引擎收到 `scoring_framework = "multi-framework"` 時，對同一份答題資料執行三次 filter，各自輸出 iso26000、iso20400、geo_risk 分數。

**替代方案考慮**：
- `scoring_frameworks[]` 陣列欄位：schema 改動較大，與現有 `framework_default_weights` 外鍵需重設
- NULL framework = 多框架：語意模糊（現有 NULL = 全 slug 參與），會破壞現有邏輯

**理由**：schema 改動最小，向後相容；單一枚舉值清晰標記「這是混合範本」。

---

### D2：三軸雷達分數的儲存位置

**決定**：在 `risk_assessments` table 新增欄位：
- `axis1_score`（ESG 暴露，float，0~100，來自 iso26000 計分，反轉：100 - total_score）
- `axis2_score`（治理成熟度，float，0~100，來自 iso20400 計分，反轉）
- `axis3_score`（地緣產業，float，0~100，手動填報）
- `axis1_source_saq_id`、`axis2_source_saq_id`（來源追溯）

同時保留既有 E/S/G/GP probability/impact 欄位（向後相容，舊資料不受影響）。

**理由**：雷達分與矩陣分語義不同（雷達是 0~100 連續分，矩陣是 1~5 離散格），混用同欄位會造成計算歧義。

---

### D3：路徑風險分的計算位置

**決定**：路徑風險分由 `esgchain-ai` 計算，`esgchain-api` 只負責組裝輸入資料並快取結果。新增端點：
- `POST /ai/v1/path-risk`：輸入 `{trade_good_id, market, supplier_emissions[]}`，回傳 `path_risk_score`、`risk_level`、`amplifier`、各供應商貢獻明細
- 結果快取於 `trade_good_path_risks` table（`trade_good_id + market + calculated_at`），有效期 24 小時或 PcfSnapshot 更新時失效

**理由**：計算密集任務屬於 esgchain-ai 職責；快取避免熱力圖每格即時計算造成的 N+1 問題。

---

### D4：碳排缺失的 fallback 標記機制

**決定**：路徑風險計算時，若供應商無有效 PcfSnapshot 明細：
1. 從 `material_item_emissions` 取該物料行業均值（`emission_factor`）作為 fallback
2. 該供應商在回傳結果的 `contributors[]` 中標記 `data_gap: true`
3. 整體路徑風險結果標記 `has_data_gap: true`，前端顯示橘色警示標籤

**理由**：碳排缺失不應導致供應商被「隱形」（貢獻清零），fallback 確保風險不被低估；明確標記讓使用者知道資料品質。

---

### D5：替換供應商推薦的候選查詢策略

**決定**：`POST /ai/v1/supplier-replacement-candidates`
- 輸入：`{trade_good_id, market, replace_supplier_id}`
- 查詢：系統中其他供應商，WHERE `hs_code 交集 ≠ 空 AND country_code ≠ replace_supplier.country_code AND has_saq_axis1_score`
- 模擬計算：以 candidate 的 `axis1_score` 替換 replace_supplier 的貢獻，重算路徑風險
- 輸出：候選清單，含模擬後路徑風險、改善幅度百分比，限 10 筆，依改善幅度排序

**理由**：層次 2 推薦需要跨供應商資料，計算邏輯集中在 AI 服務；API 設計讓前端可從缺口面板直接觸發，不需要頁面跳轉。

---

### D6：Dashboard 角色入口設計

**決定**：
- Dashboard A（永續風險）：納入現有 sidebar `dashboard` 模組下，sustain / analyst / admin 可見
- Dashboard B（出口合規）：納入現有 sidebar `tradegoods` 模組下，comply / buyer / admin 可見
- 兩個 Dashboard 不共用頁面 URL，但供應商詳情頁 overview tab 同時顯示三軸雷達與該供應商參與的出口路徑風險清單（緊耦合接縫）

**理由**：分離入口符合角色分工，不強迫 comply 角色看永續雷達；詳情頁整合讓需要全貌的 admin 可從單一頁面理解兩個維度。

## Risks / Trade-offs

- **Multi-tag slug 設計複雜度** → 需要新建 Multi-tag 問卷範本時，標籤員需理解跨框架 slug 語義；緩解：建立官方題庫（Question Bank）並預標記常見題目的多框架 slug，範本建立時從題庫選題
- **舊 SAQ 資料斷層** → 移除舊範本後，歷史評分記錄仍存在但無法對應三軸雷達；緩解：歷史 RiskAssessment 保留，雷達視圖顯示「舊版評估，不含三軸分」提示
- **路徑風險快取失效邊界** → PcfSnapshot 更新後未即時失效快取可能顯示舊風險分；緩解：PcfSnapshot Observer 觸發相關 `trade_good_path_risks` 失效
- **替換候選覆蓋率不足** → 系統中可能沒有足夠的同 HS Code 替代供應商；緩解：UI 明確顯示「系統內找到 N 家候選」，並提示「可透過 ERP 引入新供應商後重新評估」
- **軸3 手動填報品質** → 地緣產業風險依賴人工評分，不同評估者標準不一；緩解：提供結構化填報欄位（國家風險等級、受管制物料清單），並顯示填報者與日期

## Migration Plan

1. 建立 Multi-tag 混合範本（含 iso26k.*、iso20400.*、geo_risk.* slug 題目）
2. 停用現有獨立框架範本（`is_active = false`，不刪除資料）
3. 執行 `risk_assessments` table migration（新增 axis1/2/3 欄位）
4. 建立 `trade_good_path_risks` 快取 table
5. 部署 esgchain-ai 新端點（`/ai/v1/path-risk`、`/ai/v1/supplier-replacement-candidates`）
6. 部署前端兩個新 Dashboard
7. 通知使用者重建 SAQ 專案

Rollback：axis 欄位為 nullable，舊版前端不讀取新欄位，可安全回滾前端。

## Open Questions

- 軸3 地緣產業手動填報的 UI 入口：放在供應商詳情頁 Risk tab 還是獨立設定頁？
- 路徑風險快取有效期：24 小時是否足夠？是否需要手動「強制重算」按鈕？
- Multi-tag 範本的建立工具：是否需要 admin 介面讓非工程師可以新建題目並掛多框架 slug？
