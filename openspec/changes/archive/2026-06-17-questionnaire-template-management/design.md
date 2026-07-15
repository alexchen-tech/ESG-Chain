## Context

`saq_templates` 現有欄位：id/name/version/description/is_active/sasb_industry_id/created_by_id。無 archived_at。後端有 CRUD API 但無 clone/archive。TemplateDetailView 麵包屑仍指向「系統設定」（已移除問卷功能）。前端 `settingsApi.templates` 已有 list/get/create/update/delete 方法。

## Goals / Non-Goals

**Goals:**
- `archived_at` nullable timestamp migration
- Controller 補 `clone()`、`archive()`、`unarchive()`
- `QuestionnaireTemplatesView.vue`：列表（啟用/停用/封存 Tab）、新增 Modal、每列操作（編輯基本資訊、複製、封存/取消封存、進入詳情）
- `TemplateDetailView.vue`：頁頭加基本資訊 inline 編輯（名稱/版本/描述）、麵包屑修正、封存時顯示警告 banner 且 disabled 所有編輯操作

**Non-Goals:**
- 版本 diff 比對（版本歷史查詢）
- 範本預覽（供應商視角）
- 範本匯入/匯出（JSON/Excel）

## Decisions

**D1：封存語義**
- `archived_at IS NOT NULL` = 封存；`IS NULL` = 正常
- 封存的範本：列表頁顯示於「封存」Tab，不出現在「問卷發送」的範本選擇清單
- 封存的範本：TemplateDetailView 顯示黃色 banner「此範本已封存，無法編輯題目」，所有操作按鈕 disabled
- 可取消封存（設 archived_at=null）→ 恢復可編輯

**D2：clone 邏輯**
- 複製欄位：name（加「 (複製)」後綴）、version（加 `.copy`）、description、sasb_industry_id
- 複製所有 SAQQuestions（template_id 換成新範本，source_bank_question_id 保留）
- is_active = false（複製的草稿，需手動啟用）、archived_at = null

**D3：列表頁 Tab 結構**
```
啟用  |  停用  |  封存
```
- 啟用 Tab：`is_active=true AND archived_at IS NULL`
- 停用 Tab：`is_active=false AND archived_at IS NULL`
- 封存 Tab：`archived_at IS NOT NULL`

**D4：前端 settingsApi 擴充**
- `settingsApi.templates.clone(id)`
- `settingsApi.templates.archive(id)`
- `settingsApi.templates.unarchive(id)`

**D5：麵包屑修正**
- TemplateDetailView 的第一段麵包屑：`ESG 問卷` → router.push('/questionnaires/templates')
- 第二段：`問卷範本設計` → router.push('/questionnaires/templates')

## Risks / Trade-offs

- **發送清單過濾**：QuestionnaireView 的發送 Modal 需過濾掉 archived_at IS NOT NULL 的範本，此處不做（留待後續修復），設計上 archived 範本在 API index 加 `?is_archived=false` 過濾即可
