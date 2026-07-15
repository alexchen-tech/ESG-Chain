## Context

ESG-Chain 目前碳排資料路徑為：供應商在 Portal 填報 `MaterialItemEmission.emissions_value`（kgCO₂e/unit），系統依此計算 PCF Snapshot。此路徑適用產品碳足跡（PCF），但不適用 GHG Protocol Scope 3 Category 1 的「原始活動資料」需求。

Scope 3 計算需要的是供應商**生產設施**的能源消費資料（電力 kWh、天然氣 GJ、燃油 L 等），搭配當地電網排放係數或 IPCC 係數，才能計算出 Scope 3 排放量。此計算通常由外部 ESG 計算引擎（或客戶自己的 EHS 系統）執行，ESG-Chain 的角色是**資料收集與推送**，不在本系統內做碳排計算。

## Goals / Non-Goals

**Goals:**
- 建立供應商設施（SupplierFacility）主檔，作為活動資料的申報主體
- 建立活動資料申報（ActivityDataReport）記錄原始能源消費資料
- Portal 讓供應商為其設施填報活動資料
- 審核通過後透過 esgchain-ai Celery Task 推送至外部 Scope 3 計算服務

**Non-Goals:**
- 在 ESG-Chain 內部計算 Scope 3 排放量（由外部系統負責）
- 替代現有 MaterialItemEmission 的 PCF 計算路徑
- 設施的能源採購憑證管理（另立 Change）

## Decisions

### D1：SupplierFacility 獨立模型，不合併至 Supplier

設施（廠區）與供應商為 1:N 關係。一個供應商可能有多個廠區（台灣廠、越南廠），各廠的能源結構不同，需分別申報。

### D2：ActivityDataReport 按季度申報，不覆寫

與 `MaterialItemEmission` 同樣採 append-only 策略。每季新增一筆，舊資料保留。每個設施的最新一筆用於推送計算。

### D3：外部推送採 Celery Task，不同步呼叫

審核通過（`status → verified`）時，由 Laravel 透過 Guzzle 呼叫 esgchain-ai 的 `/ai/v1/celery/scope3-push` 端點，Celery Worker 非同步執行推送（避免阻塞 HTTP 請求）。

### D4：推送失敗不阻塞審核，記錄在 push_log

推送結果（成功/失敗、外部系統回傳 ID）記錄在 `ActivityDataReport.push_log JSON` 欄位，方便重送。

### D5：Portal 任務以設施為單位顯示

Portal 首頁「活動資料」區顯示供應商旗下各設施的最新申報狀態（未申報 / 申報中 / 已核實），點擊進入設施詳情頁進行填報。

## Risks / Trade-offs

- **外部系統 API 格式未定**：push_log 先記錄 raw response，格式依外部系統調整
- **設施與 MaterialItem 的映射**：目前不強制建立設施→物料的關聯，留給後續 Change 實作
- **電網排放係數更新**：係數更新需重算歷史申報，此版本不處理（外部系統責任）
