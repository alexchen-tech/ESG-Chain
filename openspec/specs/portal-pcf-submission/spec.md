## ADDED Requirements

### Requirement: Portal 碳排申報頁面
`esgchain-web` SHALL 提供 `/supplier/portal/pcf` 頁面（`PortalPcfView.vue`），供 `supplier` / `sup_esg` 角色使用，列出指向本供應商的所有 `pcf_requests`，顯示各請求的申報週期、截止日、逐料填寫進度。

#### Scenario: 頁面載入顯示待申報請求
- **WHEN** 供應商進入碳排申報頁
- **THEN** 系統 SHALL 呼叫 `GET /api/v1/portal/pcf-requests`，列出 `status = pending` 或 `overdue` 的請求，並依截止日遠近排序

#### Scenario: 顯示截止日警示
- **WHEN** 某請求的 `due_date` 距今 ≤ 14 天
- **THEN** 截止日顯示為橙色警示；`overdue` 狀態顯示為紅色

#### Scenario: 已完成請求另列
- **WHEN** 頁面包含 `status = submitted` 或 `verified` 的請求
- **THEN** SHALL 在「已完成」摺疊區塊中顯示，預設收合

### Requirement: 逐料 PCF 數值填寫
系統 SHALL 提供 `PUT /api/v1/portal/pcf-requests/{id}/lines/{lineId}` endpoint，供應商可填入 `declared_value`（kgCO₂e/unit）與 `quantity_unit`（unit 單位）。

前端 SHALL 在點擊某 PCF Request 後展開逐料填寫介面，每條 BomLine 顯示：物料名稱、HS Code、目前填寫狀態、輸入欄位（數值 + 單位）。

#### Scenario: 成功填寫單條物料
- **WHEN** 供應商填入有效數值並儲存
- **THEN** 系統 SHALL 更新 `pcf_request_line.status = 'submitted'`，`submitted_at = now()`，並通知 esgchain-ai 建立或更新 PCFRecord

#### Scenario: 數值驗證
- **WHEN** 供應商填入負數或非數字
- **THEN** 系統 SHALL 回傳 422，前端顯示 inline 錯誤提示

#### Scenario: 填寫後立即反映進度
- **WHEN** 某條物料填寫完成
- **THEN** 請求進度條（submitted/total）SHALL 即時更新

### Requirement: PCF 請求整體提交
系統 SHALL 提供 `POST /api/v1/portal/pcf-requests/{id}/submit` endpoint，當所有 `pcf_request_lines` 均為 `submitted` 時，將 `pcf_request.status` 更新為 `submitted`。

#### Scenario: 全部填寫後才可提交
- **WHEN** 尚有 pending 的 pcf_request_line
- **THEN** 系統 SHALL 回傳 422，說明仍有未填寫的物料

#### Scenario: 提交後狀態更新
- **WHEN** 所有物料均已填寫，呼叫 submit
- **THEN** `pcf_request.status` SHALL 更新為 `submitted`，採購商側可在管理頁看到進度更新

### Requirement: Portal PCF 與 SAQ 整合入口
若 `pcf_request.saq_round_id` 不為 null，Portal 頁面 SHALL 在對應請求卡片上顯示「填寫企業排放問卷」按鈕，導向現有 SAQ Portal 填寫流程。

#### Scenario: 有連結 SAQ 的請求
- **WHEN** pcf_request 有 saq_round_id
- **THEN** 顯示 SAQ 完成狀態（未填 / 已提交），並提供快速跳轉連結

#### Scenario: 無連結 SAQ 的請求
- **WHEN** pcf_request 的 saq_round_id 為 null
- **THEN** 不顯示 SAQ 相關 UI，只顯示逐料填寫區塊

---

## ADDED Requirements (erp-pcf-integration)

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
