## Why

題庫題目目前用 `category`（E/S/G）和 `iso_subject`（ISO 26000 七大主題）兩個平行欄位分類，缺少統一的三層標籤架構，無法支援跨框架（ESG / ISO 20400 / Geo-Risk / Product-Compliance）的標籤疊加。同時，問卷專案（SaqProject）沒有 `domain` 屬性，計分引擎（esgchain-ai）無法依「這個專案是 ESG 評核還是 ISO 20400 稽核」來決定要套用哪組計分演算法。

此變更取代並廢棄 `question-bank-taxonomy-phase1`（iso_subject 獨立欄位方案），以三層標籤庫（L1 領域 / L2 支柱 / L3 主題）重新設計題目分類系統，並在 SaqProject 加入 `domain` 欄位作為計分語意的決策點。

## What Changes

### 資料層
- **廢棄** `saq_questions.category` 與 `saq_questions.iso_subject` 欄位（含對應 migration rollback）
- **新增** `question_tags` 表：定義 L1/L2/L3 三層標籤，`slug` 建立後不可修改，為計分引擎的識別鍵
- **新增** `question_tag_assignments` 表：題目（含快照副本）與標籤的多對多關聯
- **新增** `saq_projects.domain` 欄位：enum `ESG | ISO20400 | Geo-Risk | Product-Compliance`
- **資料遷移**：現有 `category` / `iso_subject` 值轉換為對應的 `question_tag_assignments` 記錄；快照副本一併複製其 tag assignments

### 後端（esgchain-api）
- 新增 `QuestionTag` / `QuestionTagAssignment` Model
- 新增 `TagLibraryController`：L1/L2/L3 的 CRUD，slug 欄位建立後鎖定不可更新
- 修改 `QuestionBankController`：改用 `?l1=&l2=&l3=` 查詢參數取代 `?category=&iso_subject=`
- 修改 `ImportFromBankController`：快照建立時同步複製 `question_tag_assignments`
- 修改 `SaqProject` Model 與 `SaqProjectController`：新增 `domain` 欄位（建立/更新均可設定）
- esgchain-ai 計分 payload 新增 `project_domain` 與 `tag_slugs`（由 Laravel 組裝）

### 前端（esgchain-web）
- **新增** `TagLibraryView.vue`：左樹（L1/L2 導覽）+ 右側 L3 列表，含新增/編輯 Modal，路由 `/settings/tag-library`
- **新增** `TagSelector.vue`：三層級聯 + 已選 chip 顯示的共用打標籤元件，供題庫 Modal 和範本題目 Modal 使用
- 修改 `QuestionBankView.vue`：題目 Modal 的分類欄位改用 `<TagSelector>`
- 修改 `TemplateDetailView.vue`：題目 Modal 改用 `<TagSelector>`
- 修改 `QuestionBankFilter.vue`：改用三層 L1/L2/L3 級聯篩選取代舊 category/iso_subject
- **新增** 問卷專案建立/編輯 Modal 加入 `domain` 選擇欄位
- 修改 `SettingsView.vue`：導覽列新增「標籤庫」入口

### 計算後端（esgchain-ai）
- 計分 payload 新增 `project_domain`：引擎依 `project_domain` 過濾只計算對應 slug prefix 的標籤（語意 C）
- `scoring_router` 從 slug 映射到 engine handler，slug 為不可變識別鍵

## Capabilities

### New Capabilities

- `question-tag-library`：三層標籤定義庫（L1 領域 / L2 支柱 / L3 主題），管理員 UI 維護，slug 不可變，為計分引擎識別鍵
- `question-tag-assignment`：題目多路徑標籤疊加，一道題可同時屬於多個跨域 L3 主題，快照副本完整隔離
- `saq-project-domain`：問卷專案具備 domain 屬性，決定計分語意（哪組 L3 slug 參與計分）

### Modified Capabilities

- `question-bank-taxonomy`：由 L2 雙欄位模型升級為三層標籤庫模型（破壞性變更，取代 phase1）
- `question-bank-filter`：篩選器改用 L1/L2/L3 三層級聯取代 category/iso_subject 雙欄位

## Supersedes

- `question-bank-taxonomy-phase1`：本變更完全取代其設計，phase1 的 migration 須先 rollback 或在本次 migration 中合併處理

## Impact

- **DB**：新增兩張表（`question_tags` / `question_tag_assignments`），`saq_questions` 移除兩欄，`saq_projects` 新增一欄
- **後端**：`QuestionBankController`、`ImportFromBankController`、`SAQQuestion` model、`SaqProject` model、新增 `TagLibraryController`
- **前端**：`QuestionBankView`、`TemplateDetailView`、`QuestionBankFilter`、`SettingsView`、新增 `TagLibraryView`、`TagSelector`
- **AI**：esgchain-ai scoring payload 格式變更，`scoring_router` 需同步部署
- **不影響**：`SAQResponse` 結構、`source_bank_question_id` snapshot 機制的其餘邏輯、SASB 相關欄位
