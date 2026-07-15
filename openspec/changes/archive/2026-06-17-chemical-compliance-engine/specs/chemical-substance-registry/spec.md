## ADDED Requirements

### Requirement: 化學物質主檔（ECHA/RoHS 同步）

`Chemical` 模型（`chemicals` 表）為 ECHA SVHC 清單、RoHS Annex II、EU 電池法規等公開管制清單的本地快取。由 esgchain-ai Celery Task 每週自動同步，Laravel 側只讀。

欄位：`id (UUID)`、`cas_no VARCHAR(15) UNIQUE`、`substance_name VARCHAR(255)`、`iupac_name TEXT nullable`、`regulated_lists JSON`（如 `['REACH_SVHC','RoHS_Annex_II']`）、`restriction_notes TEXT nullable`（限制用途說明）、`svhc_date DATE nullable`（列入 SVHC 清單日期）、`synced_at TIMESTAMP`、`timestamps`。

#### Scenario: 查詢 CAS No. 的管制狀態

- **WHEN** 合規掃描引擎以 CAS No. 查詢 `Chemical` 主檔
- **THEN** 系統回傳 `regulated_lists`（空陣列代表未受管制）

#### Scenario: esgchain-ai 週期同步

- **WHEN** Celery Beat 每週一觸發 `sync_chemical_database` Task
- **THEN** Task 從 ECHA API 拉取最新 SVHC 清單，upsert `chemicals` 表，更新 `synced_at`，不影響現有 `MaterialItemChemical` 關聯

#### Scenario: 前端查詢物質資訊

- **WHEN** 買方在「新增化學組成」modal 輸入 CAS No.
- **THEN** 前端即時呼叫 `GET /api/v1/chemicals?cas_no={cas}` 預填 `substance_name`，並顯示 `regulated_lists`（若有管制，顯示紅色警示）
