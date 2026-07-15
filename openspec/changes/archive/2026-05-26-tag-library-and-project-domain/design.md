## Context

從探索討論中確立的核心設計決策，作為實作的依據。

## Data Model

### question_tags（標籤定義庫）

```sql
question_tags (
  id                  UUID PK,
  l1_domain           VARCHAR(40) NOT NULL,   -- 'ESG' | 'ISO20400' | 'Geo-Risk' | 'Product-Compliance'
  l2_pillar           VARCHAR(40) NOT NULL,   -- 'E-環境' | 'S-社會' | 'G-治理' | '人權' | ...
  l3_topic            VARCHAR(60) NOT NULL,   -- 'GHG(溫室氣體)' | 'Water(水資源)' | ...
  slug                VARCHAR(80) UNIQUE NOT NULL,  -- 'esg.e.ghg'（建立後不可修改）
  label_zh            VARCHAR(100),
  label_en            VARCHAR(100),
  scoring_engine_key  VARCHAR(60),            -- esgchain-ai handler 識別碼
  deprecated_at       TIMESTAMP NULL,         -- 軟停用，不做 hard delete
  sort_order          INT DEFAULT 0,
  created_at, updated_at
)
```

### question_tag_assignments（題目 ↔ 標籤）

```sql
question_tag_assignments (
  question_id   UUID FK → saq_questions.id ON DELETE CASCADE,
  tag_id        UUID FK → question_tags.id ON DELETE RESTRICT,
  PRIMARY KEY (question_id, tag_id)
)
```

### saq_projects（新增欄位）

```sql
ALTER TABLE saq_projects ADD COLUMN domain VARCHAR(30) NULL;
-- 'ESG' | 'ISO20400' | 'Geo-Risk' | 'Product-Compliance'
-- NULL 表示舊資料或通用型專案（計分時不過濾 domain）
```

## Key Decisions

### 1. Slug 不可變（Immutable）

slug 建立後永久鎖定。需要「改名」時，流程為：
1. 建立新 tag（新 slug）
2. 舊 tag 設 `deprecated_at`
3. 管理員批次遷移題目 assignments 至新 tag
4. esgchain-ai 同步部署新 slug handler

**原因**：slug 是 esgchain-ai `scoring_router` 的識別鍵，若可任意修改會造成計分 engine 找不到對應 handler。DB 層存 tag_id（UUID），slug 修改對 DB 完全無影響，但 Python code 的 router dict 是 hardcode slug，無法自動同步。

### 2. 快照副本完整隔離（Option 1）

題庫題目被加入範本時，`ImportFromBankController` 一併複製 `question_tag_assignments`，`question_id` 改為新快照 UUID。

**原因**：題庫事後修改標籤不影響已發出問卷的計分結果。快照是計分時的 source of truth。

### 3. 計分語意 C（Domain Filter）

esgchain-ai 收到計分請求時，依 `project_domain` 過濾：
```
effective_slugs = [s for s in tag_slugs if s.startswith(project_domain.lower() + ".")]
```
只有符合 domain prefix 的 L3 slug 會觸發對應 scoring engine。

**原因**：同一道題可能同時打了 `[esg.e.ghg]` 和 `[iso20400.環境.ghg]`，但「ESG 評核專案」只應計算 ESG 維度的分數，避免雙重計分。

### 4. SaqProject.domain 為計分決策點（非 SAQTemplate）

domain 掛在 SaqProject 而非 SAQTemplate，使同一份範本可被不同性質的專案複用。

**範例**：
- 「通用供應商永續問卷 v2.0」範本
  - 在「2025 ESG 年度評核」專案中 → domain=ESG → 計算 ESG 分
  - 在「2025 ISO 20400 稽核」專案中 → domain=ISO20400 → 計算 ISO 合規分

### 5. L1/L2 軟停用（deprecated_at），不做 hard delete

避免因底下有 L3 tag 正被題目使用而造成 FK 衝突。停用後前端篩選器和 TagSelector 不顯示該 L1/L2，但已存在的 assignments 不受影響。

## API 設計

### 標籤庫（TagLibrary）

```
GET    /api/v1/tag-library              列出所有 tags（支援 ?l1=&l2=&l3= 篩選）
GET    /api/v1/tag-library/tree         回傳 L1→L2→L3 巢狀結構（供前端樹狀渲染）
POST   /api/v1/tag-library              新增 tag（slug 由後端自動生成或前端傳入，建立後不可改）
PUT    /api/v1/tag-library/{id}         更新 label_zh/label_en/scoring_engine_key/sort_order（slug 欄位被忽略）
POST   /api/v1/tag-library/{id}/deprecate   軟停用
POST   /api/v1/tag-library/{id}/restore     取消停用
```

### 題目標籤（QuestionTagAssignment）

```
GET    /api/v1/question-bank/{questionId}/tags        取得題目的所有 tags
POST   /api/v1/question-bank/{questionId}/tags        新增 tag assignment { tag_id }
DELETE /api/v1/question-bank/{questionId}/tags/{tagId}  移除 tag assignment
```

### 問卷專案（SaqProject，修改現有 API）

```
POST  /api/v1/saq-projects        建立專案，body 新增 domain 欄位
PUT   /api/v1/saq-projects/{id}   更新專案，domain 可修改（尚未開始計分前）
```

### esgchain-ai 計分 Payload（修改）

```json
{
  "saq_id": "uuid",
  "project_domain": "ESG",
  "responses": [
    {
      "question_id": "快照副本UUID",
      "source_question_id": "題庫原題UUID",
      "answer": "...",
      "tag_slugs": ["esg.e.ghg", "iso20400.環境.ghg"]
    }
  ]
}
```

## UI 架構

### TagLibraryView.vue（/settings/tag-library）

```
┌────────────────────┬─────────────────────────────────────────┐
│ L1/L2 樹狀導覽     │ L3 主題列表（依選中 L2 顯示）            │
│                    │                                         │
│ ▼ ESG              │ 標籤名稱 | Slug | 計分鍵 | 狀態 | 操作  │
│   ▶ E-環境  ●      │ GHG(溫室) | esg.e.ghg | ghg_v1 | 啟用 │
│   ▶ S-社會         │ Water(水) | esg.e.water | water_v1 | 啟用│
│   ▶ G-治理         │                                         │
│                    │                           [+ 新增 L3]   │
│ ▶ ISO20400         │                                         │
│ ▶ Geo-Risk         │                                         │
│ ▶ Product-Compl.   │                                         │
│                    │                                         │
│ [+ 新增 L1]        │                                         │
│ [+ 新增 L2]        │                                         │
└────────────────────┴─────────────────────────────────────────┘
```

### TagSelector.vue（共用打標籤元件）

```
分類標籤                                        [+ 新增標籤]
┌────────────────────────────────────────────────────────┐
│ [ESG › E-環境 › GHG] ✕   [ISO20400 › 環境 › GHG] ✕   │
└────────────────────────────────────────────────────────┘

點擊「+ 新增標籤」→ 三層級聯選擇器（L1 → L2 → L3 cascade）→ [加入]
已選 chips 可個別刪除
deprecated 的 tag 不出現在選擇器
```

Props: `modelValue: TagAssignment[]`
Emits: `update:modelValue`

### 新增 L3 Modal（Slug 自動生成 + 鎖定警告）

- 所屬 L1/L2：唯讀（根據當前選中自動填入）
- 中文名稱 / 英文名稱：可填
- Slug：根據 `l1.l2.label_en` 自動生成，建立前可手動覆寫，顯示 `⚠ 建立後永久不可修改`
- 計分引擎識別碼：可填，顯示 `需與 esgchain-ai 部署版本一致`

### 編輯 L3 Modal（Slug 鎖定）

- Slug：disabled input + 🔒 圖示
- label_zh / label_en / scoring_engine_key：可修改
- scoring_engine_key 修改時顯示 `⚠ 修改後需同步更新並部署 esgchain-ai`
- 底部「停用此標籤」區塊（獨立確認動作）

### 問卷專案 domain 欄位

建立/編輯 SaqProject Modal 新增：
```
評核框架
[ESG ▼]   ESG / ISO 20400 / Geo-Risk / Product-Compliance
說明：決定此專案的計分維度，同一份問卷範本可依不同框架產生不同評分結果
```

## Migration Strategy

### Phase 0（pre-condition）：rollback question-bank-taxonomy-phase1

若 `iso_subject` 欄位已 migrate：
```bash
php artisan migrate:rollback --step=1   # 回滾 add_iso_subject_to_saq_questions
```

### Phase 1：建立新表與新欄位

1. `create_question_tags_table`
2. `create_question_tag_assignments_table`
3. `add_domain_to_saq_projects`

### Phase 2：資料遷移

4. `migrate_category_iso_to_tag_assignments`
   - 現有 `category`（E/S/G）→ 建立 ESG domain 的對應 question_tags → 插入 assignments
   - 現有 `iso_subject` 值（若存在）→ 建立 ISO20400 domain 的對應 question_tags → 插入 assignments
   - 快照副本（template_id IS NOT NULL）→ 從 source_bank_question_id 複製 assignments

### Phase 3：移除舊欄位

5. `drop_category_and_iso_subject_from_saq_questions`

### Seeder

`QuestionTagSeeder`：初始標籤資料集（ESG 三支柱 × 各 4-6 個 L3 主題，ISO20400 七大主題 × 各 2-3 個 L3）

## Slug 命名規範

```
{l1_key}.{l2_key}.{l3_key}

l1_key: esg | iso20400 | geo_risk | product_compliance
l2_key: e | s | g（ESG 域）; 組織治理→org_gov, 人權→human_rights, 勞工→labor（ISO20400 域）
l3_key: 英文 snake_case，例如 ghg, water, forced_labor, anti_corruption

範例：
  esg.e.ghg
  esg.s.forced_labor
  esg.g.anti_corruption
  iso20400.labor.forced_labor
  geo_risk.political.sanctions
```
