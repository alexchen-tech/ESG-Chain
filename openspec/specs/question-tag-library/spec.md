## MODIFIED Requirements

### Requirement: 題目多重框架標記結構
題庫中每道題的 `tags` 欄位 SHALL 儲存物件陣列，結構為 `[{framework, pillar, weight}]`，取代原本的字串陣列。單題可同時帶多個框架標記，每個標記有獨立的 pillar 分類與 per-framework 計分權重。`compliance_domains` 欄位由系統自動同步為 tags 中所有 framework 值的去重集合，供快速過濾用。

#### Scenario: 建立多重標記題目
- **WHEN** 管理員在題庫編輯一道環境題，標記 E1（E 支柱, weight=0.04）+ E3（env, weight=0.06）
- **THEN** tags 儲存 `[{"framework":"E1","pillar":"E","weight":0.04},{"framework":"E3","pillar":"env","weight":0.06}]`；compliance_domains 自動更新為 `["E1","E3"]`

#### Scenario: 無效的 framework 值被拒絕
- **WHEN** API 收到 framework 值不在 {E1,E2,E3,E4,E5,E6} 集合中的 tag
- **THEN** 回傳 422 Validation Error，訊息說明有效的 framework 值範圍

### Requirement: E6 題目的 pillar 對應法規代碼
E6 標記題目的 `pillar` 欄位 SHALL 使用標準法規代碼（如 `cbam`、`eudr`、`uflpa`、`reach`、`rohs`），與 `SalesProduct.applicable_regulations` 的值集合對齊，以支援動態篩題機制正確過濾。

#### Scenario: E6 CBAM 題動態篩出
- **WHEN** 供應商 applicable_regulations 包含 `cbam`，系統進行動態篩題
- **THEN** 篩選條件 `WHERE framework='E6' AND pillar='cbam'` 能正確找出所有 CBAM 相關題目

#### Scenario: 跨法規共用題目
- **WHEN** 某道碳排報告題同時適用 CBAM 與 EUDR
- **THEN** 該題帶兩個 E6 tag：`{framework:"E6",pillar:"cbam",weight:0.05}` 與 `{framework:"E6",pillar:"eudr",weight:0.05}`，在兩種適用情境下均被篩出（但在同一問卷中去重，只出現一次）
