## ADDED Requirements

### Requirement: 三軸雷達分數計算與儲存

`risk_assessments` table SHALL 新增三軸分欄位：`axis1_score`（ESG 暴露，float 0~100）、`axis2_score`（治理成熟度，float 0~100）、`axis3_score`（地緣產業，float 0~100）、`axis1_source_saq_id`、`axis2_source_saq_id`（來源追溯）。所有新欄位 nullable，舊有 E/S/G/GP probability/impact 欄位保留。

三軸語義：
- 軸1（ESG 暴露）= 100 - ISO 26000 SAQ total_score（分數越高代表暴露越大）
- 軸2（治理成熟度風險）= 100 - ISO 20400 SAQ total_score（分數越高代表成熟度越低）
- 軸3（地緣產業）= 手動填報 0~100（100 = 極高地緣風險）

#### Scenario: Multi-tag SAQ 評分完成後更新三軸分

- **WHEN** `scoreCallback()` 收到 `scoring_framework = "multi-framework"` 的評分結果，且結果包含 `axis1_score` 與 `axis2_score`
- **THEN** 系統 SHALL 建立或更新 RiskAssessment，寫入 axis1_score、axis2_score，並記錄 axis1_source_saq_id、axis2_source_saq_id 為此 SAQ id

#### Scenario: 軸3 手動填報

- **WHEN** 使用者在供應商詳情頁 Risk tab 填入地緣產業風險分（0~100）並送出
- **THEN** 系統 SHALL 更新最新 RiskAssessment 的 axis3_score，記錄 assessed_by 與 assessed_at

#### Scenario: 舊版 SAQ 無三軸分

- **WHEN** 供應商的 RiskAssessment 建立時 scoring_framework 非 multi-framework（舊版評估）
- **THEN** axis1_score、axis2_score SHALL 為 null；前端顯示「舊版評估，不含三軸分」提示，axis3_score 可獨立手動填報

### Requirement: 三軸雷達視覺化

供應商詳情頁 Risk tab SHALL 顯示三軸雷達圖，三軸為 ESG 暴露（軸1）、治理成熟度（軸2）、地緣產業（軸3）。填色區域面積代表綜合風險大小，面積越大越危險。

點擊各軸 SHALL 展開 pillar 明細面板，顯示該框架的 category_scores 分項分數。

#### Scenario: 三軸資料完整時渲染雷達

- **WHEN** 供應商 RiskAssessment 的 axis1、axis2、axis3 均有值
- **THEN** 系統 SHALL 渲染完整三角填色雷達圖，三軸刻度 0~100

#### Scenario: 某軸缺少資料時顯示虛線

- **WHEN** axis3_score 為 null（尚未填報）
- **THEN** 系統 SHALL 將軸3 以虛線呈現，並顯示「尚未填報地緣產業風險」提示

#### Scenario: 點擊軸1 展開 ISO 26000 pillar 明細

- **WHEN** 使用者點擊雷達圖軸1（ESG 暴露）
- **THEN** 系統 SHALL 展開面板，顯示最新 SAQ 的 iso26k.* pillar 分數：組織治理、人權、勞工、環境、公平營運、消費者、社區

### Requirement: 供應商列表三軸概覽

供應商列表頁 SHALL 新增「三軸風險」欄位，以三個色塊（軸1/軸2/軸3）呈現各軸風險等級（very_low/low/medium/high/extreme）。排序支援依「綜合三軸最高等級」排序。

#### Scenario: 列表顯示三軸色塊

- **WHEN** 使用者開啟供應商列表
- **THEN** 每列供應商 SHALL 顯示三個色塊，顏色對應等級（綠/黃/橘/紅/深紅），無資料時顯示灰色「—」

#### Scenario: 依風險等級排序

- **WHEN** 使用者點擊「三軸風險」欄位排序
- **THEN** 系統 SHALL 依三軸最高等級降序排列，極高風險供應商置頂

### Requirement: 此供應商的出口路徑風險清單（緊耦合接縫）

供應商詳情頁 overview tab SHALL 在底部顯示「此供應商參與的出口路徑」清單，列出商品名稱、目標市場、路徑風險等級，連結至對應的熱力圖格子。

#### Scenario: 供應商參與多條出口路徑

- **WHEN** 使用者開啟有參與 TradeGood BOM 的供應商詳情頁
- **THEN** 系統 SHALL 列出該供應商作為 BOM 成員的所有（商品 × 市場）組合，顯示各路徑風險等級

#### Scenario: 供應商未參與任何出口路徑

- **WHEN** 供應商無對應 TradeGoodSupplier 或 BomLine 記錄
- **THEN** 系統 SHALL 顯示「尚未關聯出口商品」空狀態
