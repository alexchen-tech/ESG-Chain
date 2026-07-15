# Design: template-fix-and-reorder

## Bug Fix: clone() — esg_category & tag_assignments

### 現況

`QuestionnaireTemplateController::clone()` 大致流程：
1. 複製 template 記錄
2. 複製 questions（select 時包含 `esg_category`，但欄位已刪除）
3. **未複製** `question_tag_assignments`

### 修改方案

```php
// clone() 核心邏輯修改
$newQuestion = $originalQuestion->replicate()->fill([
    // 移除 esg_category 相關欄位
]);
$newQuestion->template_id = $newTemplate->id;
$newQuestion->save();

// 複製 tag assignments
foreach ($originalQuestion->tagAssignments as $assignment) {
    $newQuestion->tagAssignments()->create([
        'tag_id' => $assignment->tag_id,
    ]);
}
```

## Bug Fix: BankImportModal — category → L1 domain chip

### 修改方案

從 `q.question_tags`（已有 `with('questionTags.tag')` 關聯）過濾 `tag.level === 1`，顯示 chip：

```vue
<!-- 移除 -->
<span class="badge badge-gray">{{ q.category }}</span>

<!-- 新增 -->
<span v-for="tag in getL1Tags(q)" :key="tag.id" class="badge badge-green">
  {{ tag.name }}
</span>
```

## New: Reorder API

### Route

```
PATCH /api/v1/settings/questionnaire-templates/{template}/questions/reorder
```

### Controller

```php
public function reorder(Request $request, QuestionnaireTemplate $template): JsonResponse
{
    $ids = $request->validate(['question_ids' => 'required|array', 'question_ids.*' => 'uuid'])['question_ids'];
    // 驗證所有 ID 屬於此 template
    // 批次更新 sort_order
}
```

### 批次更新策略

使用單一 SQL CASE WHEN 更新，避免 N+1：

```sql
UPDATE questionnaire_questions
SET sort_order = CASE id
  WHEN 'uuid1' THEN 1
  WHEN 'uuid2' THEN 2
  ...
END
WHERE template_id = ?
```

## New: vue-draggable-next 整合

### 安裝

```bash
npm install vue-draggable-next
```

### TemplateDetailView.vue 改動

```vue
<template>
  <draggable v-model="questions" item-key="id" handle=".drag-handle" @end="onReorder">
    <template #item="{ element }">
      <div class="question-row">
        <span class="drag-handle">⠿</span>
        <!-- 現有題目內容 -->
      </div>
    </template>
  </draggable>
</template>

<script>
import { VueDraggableNext as draggable } from 'vue-draggable-next'

methods: {
  async onReorder() {
    const ids = this.questions.map(q => q.id)
    await templateQuestionsApi.reorder(this.templateId, ids)
  }
}
</script>
```

### Optimistic Update 策略

拖曳結束 → 立即更新本地順序 → 背景呼叫 API → 失敗時 rollback + toast 錯誤
