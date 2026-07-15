## MODIFIED Requirements

### Requirement: 採購商手動觸發填報請求
系統 SHALL 提供 `POST /api/v1/sales-products/{id}/bom-lines/{lineId}/request-emission` endpoint，允許 buyer / sustain 角色對特定 BOM 行手動建立 PcfRequest（`trigger_source = 'buyer_manual'`）。

#### Scenario: 手動觸發成功
- **WHEN** 採購商對 BOM 行點擊「發送填報請求」
- **THEN** 系統 SHALL 建立 PcfRequest + PcfRequestLine（trigger_source = buyer_manual），回傳 201

#### Scenario: 已有 pending 請求時不重複建立
- **WHEN** 該 (material_item_id × supplier_id) 已有 status = pending 的 PcfRequestLine
- **THEN** 系統 SHALL 回傳 409，說明已有待填報請求，不呼叫缺口掃描、不建立新的 PcfRequest/PcfRequestLine
