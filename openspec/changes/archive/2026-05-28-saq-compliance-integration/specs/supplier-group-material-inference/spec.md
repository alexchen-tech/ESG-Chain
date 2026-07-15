## ADDED Requirements

### Requirement: 供應商群組物料類型推導
系統 SHALL 能依供應商群組內所有成員供應商的 TradeGoods 紀錄，自動推導該群組隱含的 MaterialGroup 集合與對應的合規文件需求，無需手動維護 pivot table。

#### Scenario: 群組內供應商有 TradeGoods 記錄
- **WHEN** 採購商請求 `GET /api/v1/supplier-groups/{id}/inferred-material-groups`
- **THEN** 系統 SHALL 回傳該群組下所有供應商中，`trade_goods.material_group_id` 不為 null 的 MaterialGroup 聚合（去重），以及彙總後的 `compliance_domains`（來自各 MaterialGroup.required_doc_types 的聯集映射）

#### Scenario: 群組內無 TradeGoods 或 material_group_id 全為 null
- **WHEN** 採購商請求推導 API，但群組內所有供應商均無有效 TradeGoods 綁定
- **THEN** 系統 SHALL 回傳 `material_groups: []`，`compliance_domains: []`，HTTP 200

#### Scenario: 推導結果快取
- **WHEN** 同一群組在 10 分鐘內被重複查詢
- **THEN** 系統 SHALL 回傳快取結果，不重複執行資料庫查詢

#### Scenario: 供應商 TradeGoods 變更時快取失效
- **WHEN** 群組內某供應商的 TradeGood 新增、更新或刪除
- **THEN** 系統 SHALL 清除該群組的推導快取，下次查詢重新計算

### Requirement: 推導結果 compliance_domains 映射
系統 SHALL 將 MaterialGroup.required_doc_types 的文件類型映射為合規範疇識別碼：`UFLPA_DECLARATION` → `UFLPA`、`EUDR_DDS` → `EUDR`、`CMRT` → `CMRT`、`SDS` → `SDS`、`CE_DOC` → `CE`。

#### Scenario: 多個 MaterialGroup 的 compliance_domains 合併
- **WHEN** 群組推導結果包含「電子五金」（CMRT）與「機電終端」（CE）兩個 MaterialGroup
- **THEN** `compliance_domains` SHALL 為 `["CMRT", "CE"]`（去重，不重複）
