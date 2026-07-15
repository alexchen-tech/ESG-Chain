# Spec: Questionnaire Template Management (Delta)

## Changes from Base Capability

### Bug Fix: clone() — esg_category 殘留

`QuestionnaireTemplateController::clone()` 目前引用已刪除的 `esg_category` 欄位，導致複製時發生 SQL 錯誤或資料異常。

**修正**：移除所有 `esg_category` 相關欄位引用，僅複製現有欄位。

### Bug Fix: clone() — question_tag_assignments 未複製

複製範本時，題目的 tag 指派（`question_tag_assignments` 表）未被複製，導致新範本題目缺失標籤。

**修正**：clone 時遍歷所有複製後的題目，依原題目 ID mapping 複製對應的 `question_tag_assignments` 記錄。

### Bug Fix: BankImportModal — category badge 顯示已刪除欄位

`BankImportModal.vue` 顯示 `q.category` badge，但 `category` 欄位已從資料庫移除，實際為空值顯示異常。

**修正**：移除 `q.category` badge，改顯示題目 `question_tags` 關聯中 `level = 1`（L1 domain）的 tag chip，格式：`[domain_name]`。

## Acceptance Criteria

- [ ] 複製範本後，新範本題目的 tag 指派與原範本一致
- [ ] 複製範本不再產生 `esg_category` 相關 SQL 錯誤
- [ ] BankImportModal 顯示 L1 domain chip 而非空白 category badge
