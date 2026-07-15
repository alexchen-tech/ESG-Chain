## Context

現有系統的合規路徑：`MaterialGroup.required_doc_types` → 供應商上傳文件 → 買方核對。這條路徑適用需要「供應鏈盡職調查文件」的法規（EUDR、UFLPA、CMRT）。

新路徑：`MaterialItem → MaterialItemChemical（CAS No.）→ 交叉比對 Chemical 主檔管制清單 → ChemicalComplianceAlert`。適用物質管制法規（REACH SVHC、RoHS 限制清單、EU 電池法規 Annex VI）。

兩條路徑互不干擾，共存於同一系統。

## Goals / Non-Goals

**Goals:**
- 建立 `MaterialItemChemical` 記錄物料化學組成（CAS No. + 重量百分比）
- 建立 `Chemical` 主檔，由 esgchain-ai 從 ECHA API 定期同步
- 合規掃描：比對 BOM 中物料的 CAS No. 與管制清單，產出警示
- `ErpAdapterInterface` 新增 `pushComplianceTag()` 與 `lockMaterial()` 方法（目前只有讀，無回寫）

**Non-Goals:**
- 實作具體的 ERP Adapter（留給各 ERP 整合 Change）
- 替代現有文件驅動合規路徑
- SDS（安全數據表）解析自動提取 CAS No.（另立 Change）

## Decisions

### D1：Chemical 主檔由 esgchain-ai 管理，MySQL 只存快取

`chemicals` 表是 ECHA/RoHS 公開資料庫的本地快取，由 esgchain-ai Celery Task 每週同步更新。Laravel 側只讀，不寫。

### D2：管制清單以 JSON 陣列記錄

`Chemical.regulated_lists JSON`（如 `['REACH_SVHC','RoHS_Annex_II']`），避免多對多表爆炸，且 MySQL 8.4 的 JSON_CONTAINS 可索引查詢。

### D3：合規警示 append-only，掃描後產新快照

每次執行掃描（手動或 BOM 變更觸發）產出一批 `ChemicalComplianceAlert`，舊警示標記為 resolved 而非刪除，保留稽核軌跡。

### D4：ErpAdapterInterface 擴充為雙向

現有 `ErpAdapterInterface` 只有 `fetch*` 方法（讀）。本 Change 新增：
- `pushComplianceTag(string $erpCode, array $tags)` — 回寫合規標記至 ERP 物料主檔
- `lockMaterial(string $erpCode, string $reason)` — 在 ERP 鎖定物料採購（危險物質確認前禁採）

具體 Adapter 實作留空（`throw new NotImplementedException()`），由各 ERP 整合 Change 覆寫。

### D5：掃描觸發時機

- BOM 匯入（新增 MaterialItem 或更新 hs_code）後自動掃描
- 化學組成更新後自動掃描
- 永續團隊手動觸發（`POST /api/v1/products/{product}/scan-chemicals`）

## Risks / Trade-offs

- **ECHA API 速率限制**：同步 Task 需限速，建議分批次執行
- **CAS No. 資料品質**：供應商填寫的 CAS No. 可能不標準，需格式驗證（正則：`^\d{1,7}-\d{2}-\d$`）
- **ERP 回寫 Adapter 尚未實作**：`pushComplianceTag` 會記錄呼叫但不實際推送，直到 Adapter 實作完成
