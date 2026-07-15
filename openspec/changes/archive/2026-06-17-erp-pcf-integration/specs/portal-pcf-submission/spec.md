## ADDED Requirements

### Requirement: 供應商填報後自動更新 PcfRequestLine 狀態
系統 SHALL 在供應商透過 Portal 填報 `MaterialItemEmission`（source = portal-self）後，自動查找匹配的 `PcfRequestLine`（material_item_id 相符且 pcf_request.supplier_id = 填報供應商），更新 `status = submitted`、`fulfilled_emission_id = 新建 emission id`、`submitted_at = 填報時間`。

#### Scenario: 填報後 PcfRequestLine 自動 submitted
- **WHEN** 供應商填報 MaterialItemEmission（material = M1）
- **THEN** 系統 SHALL 找到對應的 PcfRequestLine(M1)，status 更新為 submitted，fulfilled_emission_id 指向新建 emission

#### Scenario: 無對應 PcfRequestLine 時不報錯
- **WHEN** 供應商主動填報一個沒有對應 PcfRequestLine 的物料碳排
- **THEN** 系統 SHALL 正常建立 MaterialItemEmission，跳過 PcfRequestLine 更新，不報錯

### Requirement: Portal PCF 任務區與 SAQ 任務區分離顯示
系統 SHALL 在供應商 Portal 首頁（`PortalView.vue`）將任務分為兩個獨立區塊：「待填問卷（SAQ）」與「待填碳排（PCF）」，分別顯示各自的待處理任務數量與最近截止日。

#### Scenario: Portal 首頁顯示兩區任務
- **WHEN** 供應商進入 Portal 首頁
- **THEN** 系統 SHALL 同時顯示 SAQ 待填任務區（pending SAQs）與 PCF 待填任務區（pending PcfRequestLines），各自有計數與截止日

#### Scenario: PCF 任務區顯示物料層級任務
- **WHEN** 供應商 Portal PCF 任務區有待填項目
- **THEN** SHALL 顯示各物料名稱、HS Code、截止日，連結至碳排填報頁面（`/supplier/portal/material-emissions`）
