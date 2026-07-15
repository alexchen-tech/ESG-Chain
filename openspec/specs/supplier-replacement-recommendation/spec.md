## ADDED Requirements

### Requirement: 替換供應商候選推薦 API（esgchain-ai）

系統 SHALL 提供 `POST /ai/v1/supplier-replacement-candidates`，回傳可替換指定供應商的候選清單。

查詢條件：
- 候選供應商 HS Code 與被替換供應商有交集（來自 Supplier.hs_code 或 TradeGoodSupplier 關聯）
- 候選供應商 `country_code ≠ replace_supplier.country_code`（不同來源國，降低地緣集中風險）
- 候選供應商有有效的 `axis1_score`（已完成 Multi-tag SAQ 評分）

排序：依「替換後路徑風險改善幅度」降序，最多回傳 10 筆。

模擬計算：以候選供應商的 `axis1_score` 替換被替換供應商的貢獻，重算 `Chain_Risk`，計算改善幅度百分比。

#### Scenario: 查詢替換候選

- **WHEN** `POST /ai/v1/supplier-replacement-candidates` 收到 `{trade_good_id, market, replace_supplier_id}`
- **THEN** 系統 SHALL 回傳候選清單，每筆含：`supplier_id, name, country_code, axis1_score, simulated_path_risk_score, improvement_pct`，依 `improvement_pct` 降序排列，最多 10 筆

#### Scenario: 無符合候選時回傳空清單

- **WHEN** 系統中無任何供應商同時滿足 HS Code 交集、異來源國、有 SAQ 分三個條件
- **THEN** 系統 SHALL 回傳 `{candidates: [], message: "系統內無符合條件候選供應商"}`

#### Scenario: 候選已在同一商品的供應鏈中

- **WHEN** 候選供應商已是該 TradeGood 的 BOM 成員
- **THEN** 系統 SHALL 在該候選標記 `already_in_supply_chain: true`，仍列出但排序置後

### Requirement: 替換推薦 UI 面板

義務缺口明細面板點擊 [換供應商] 後，SHALL 在同頁面展開「替換供應商推薦」子面板，顯示候選清單與路徑風險改善預覽。

每筆候選顯示：供應商名稱、來源國國旗、ESG暴露等級（軸1色塊）、替換後路徑風險等級、改善幅度（↓ XX%）。

#### Scenario: 展開替換推薦面板

- **WHEN** 使用者在義務缺口面板點擊「換供應商」（replace_supplier_id = VGE）
- **THEN** 系統 SHALL 呼叫推薦 API，展開子面板顯示候選清單，載入中顯示 skeleton

#### Scenario: 點擊候選供應商查看詳情

- **WHEN** 使用者點擊候選供應商名稱
- **THEN** 系統 SHALL 在新分頁開啟該供應商詳情頁（三軸雷達視圖）

#### Scenario: 無候選時提示引入新供應商

- **WHEN** 候選清單為空
- **THEN** 系統 SHALL 顯示提示：「系統內無符合條件的替換候選。可透過 ERP 引入新供應商後，完成 SAQ 評估即可出現於此清單。」

### Requirement: 替換推薦免責聲明

替換供應商推薦 SHALL 在面板頂部顯示固定提示：「推薦僅基於 ESG 風險評估，不含交期、價格、產能等商業因素。實際換源需透過 ERP 採購流程執行。」

#### Scenario: 免責聲明固定顯示

- **WHEN** 替換推薦面板展開
- **THEN** 免責聲明 SHALL 固定顯示於候選清單上方，不可關閉

### Requirement: 供應商替代候選評分模型改用六維
替代候選評分 SHALL 改為 `total_score × 0.5 + 六維加權分 × 0.5`，並套用最差維度硬性過濾。

評分公式：
```
six_dim_score = Σ(dim_eN × default_weight_N) / Σ(active_weight_N)
candidate_score = total_score × 0.5 + six_dim_score × 0.5
```

硬性過濾：`min(dim_e1, dim_e2, dim_e3, dim_e4, dim_e5) ≥ 30`（任一維度合規分 < 30 者不推薦）

#### Scenario: 標準候選評分
- **WHEN** 查詢替代供應商，候選廠 A：total_score=85，dim_e1=90 / e2=75 / e3=82 / e4=78 / e5=80，min=75
- **THEN** 系統 SHALL 計算 six_dim_score = (90×0.25+75×0.15+82×0.20+78×0.15+80×0.10) / 0.85，candidate_score = total_score×0.5 + six_dim_score×0.5
- **THEN** 候選廠通過硬性過濾（min=75 ≥ 30），列入推薦清單

#### Scenario: 最差維度過低被過濾
- **WHEN** 候選廠 B：total_score=75，dim_e4=25（其餘均 ≥ 60）
- **THEN** 系統 SHALL 排除候選廠 B（min=25 < 30），不列入推薦清單

#### Scenario: 候選池為空時退化
- **WHEN** 所有同 HS Code 異來源國候選廠均未通過硬性過濾
- **THEN** 系統 SHALL 退化為僅用 total_score 排序，並在回應中標記 `fallback: true`

#### Scenario: 回傳六維分數供前端顯示
- **WHEN** 推薦結果回傳
- **THEN** 每筆候選記錄 SHALL 包含 `dim_e1`–`dim_e5` 各別合規分，供前端顯示維度強弱對比
