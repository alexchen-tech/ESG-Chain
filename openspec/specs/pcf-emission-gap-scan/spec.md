## ADDED Requirements

### Requirement: BOM 匯入後自動觸發碳排缺口掃描
系統 SHALL 在每次 BOM 匯入完成後（CSV 或 Webhook），非同步（Celery job）掃描所有新建或更新的 `(material_item_id × primary_supplier_id)` 組合，對無 `MaterialItemEmission` 記錄的組合自動建立或更新 `PcfRequest` + `PcfRequestLine`（`trigger_source = 'system_bom_import'`）。

#### Scenario: 新 BOM 行缺少碳排記錄
- **WHEN** BOM 匯入建立一條 BomLine，material_item_id = M1，primary supplier = S1，且 MaterialItemEmission 中無 (M1, S1) 記錄
- **THEN** 系統 SHALL 建立 PcfRequest（supplier = S1，trigger_source = system_bom_import）及對應 PcfRequestLine（material_item_id = M1，status = pending）

#### Scenario: 已有碳排記錄的 BOM 行不重複建立
- **WHEN** BOM 匯入的 BomLine (M2, S2) 已有 MaterialItemEmission 記錄
- **THEN** 系統 SHALL 不建立 PcfRequest，PCF 計算直接使用現有碳排值

#### Scenario: 同一供應商多個缺口合併為一張請求單
- **WHEN** BOM 匯入後，供應商 S1 有 3 個物料（M1, M2, M3）缺乏碳排記錄
- **THEN** 系統 SHALL 建立 1 張 PcfRequest（supplier = S1），包含 3 條 PcfRequestLine

#### Scenario: 已有 pending PcfRequest 時新增 PcfRequestLine 而非新建請求
- **WHEN** 供應商 S1 已有 status = pending 的 PcfRequest，且新 BOM 行發現另一缺口 M4
- **THEN** 系統 SHALL 在現有 PcfRequest 下新增 PcfRequestLine（M4），不建立新 PcfRequest

### Requirement: 供應商切換時自動觸發缺口掃描
系統 SHALL 在 `BomLineSupplier` primary supplier 變更時（ERP 同步或手動更新），對新供應商執行缺口掃描：若新供應商無對應 MaterialItemEmission，SHALL 建立 PcfRequest（`trigger_source = 'system_supplier_change'`）。

#### Scenario: 換供應商且新供應商有碳排記錄
- **WHEN** BomLine (M1) 的 primary supplier 從 S1 改為 S2，且 MaterialItemEmission 有 (M1, S2) 記錄
- **THEN** 系統 SHALL 立即觸發 PCF 重算（新快照），不建立 PcfRequest

#### Scenario: 換供應商且新供應商無碳排記錄
- **WHEN** BomLine (M1) 的 primary supplier 從 S1 改為 S3，且 MaterialItemEmission 無 (M1, S3) 記錄
- **THEN** 系統 SHALL 建立 PcfRequest（supplier = S3，trigger_source = system_supplier_change），舊快照保留

### Requirement: 採購商手動觸發填報請求
系統 SHALL 提供 `POST /api/v1/sales-products/{id}/bom-lines/{lineId}/request-emission` endpoint，允許 buyer / sustain 角色對特定 BOM 行手動建立 PcfRequest（`trigger_source = 'buyer_manual'`）。

#### Scenario: 手動觸發成功
- **WHEN** 採購商對 BOM 行點擊「發送填報請求」
- **THEN** 系統 SHALL 建立 PcfRequest + PcfRequestLine（trigger_source = buyer_manual），回傳 201

#### Scenario: 已有 pending 請求時不重複建立

- **WHEN** 該 (material_item_id × supplier_id) 已有 status = pending 的 PcfRequestLine
- **THEN** 系統 SHALL 回傳 409，說明已有待填報請求，不呼叫缺口掃描、不建立新的 PcfRequest/PcfRequestLine
