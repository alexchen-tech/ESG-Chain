# Design: saq-unified-flow

## 架構決策

### 1. status enum 遷移

**Migration**: `ALTER TABLE saqs MODIFY status ENUM('sent','in_progress','submitted','under_review','review_returned','completed','reviewed')`

- 移除 `not_started`，加入 `sent`
- 目前 SAQ 資料 = 0 筆，無資料遷移風險
- `SaqProjectController::send()` 已寫入 `'sent'`（此 migration 讓它合法）

### 2. 舊版發送端點廢棄

`POST /questionnaires/send` → 返回 `410 Gone`：

```php
public function send(Request $request): JsonResponse
{
    return response()->json([
        'success' => false,
        'message' => '請透過問卷專案發送問卷',
    ], 410);
}
```

前端 `QuestionnaireView.vue` 移除整個發送 Modal，改顯示引導連結。

### 3. QuestionnaireService::TRANSITIONS 更新

```php
private const TRANSITIONS = [
    'sent'            => ['submit' => 'submitted', 'start_fill' => 'in_progress'],
    'in_progress'     => ['submit' => 'submitted'],
    'submitted'       => ['start_review' => 'under_review'],
    'under_review'    => ['complete_review' => 'completed', 'return_review' => 'review_returned'],
    'review_returned' => ['submit' => 'submitted'],
    'completed'       => ['mark_reviewed' => 'reviewed'],
    'reviewed'        => [],
];
```

### 4. 審核路由移至 /saqs/ 命名空間

新增 4 個路由（複用既有 service 方法，只是換路徑）：

```
POST /api/v1/saqs/{saq}/start-review
POST /api/v1/saqs/{saq}/complete-review
POST /api/v1/saqs/{saq}/return-review
POST /api/v1/saqs/{saq}/mark-reviewed
```

對應 Controller：`SAQController`（新建或掛在 SaqProjectController 下）

舊版 `/questionnaires/{id}/start-review` 等路由保留（避免 Portal 中斷），但可在 phase 2 移除。

### 5. QuestionnaireView 審核 mode 加 project 篩選

`GET /api/v1/questionnaires?project_id=xxx&status=xxx`

`QuestionnaireService::list()` 加入 `project_id` filter：
```php
->when($filters['project_id'] ?? null, fn($q, $v) => $q->where('project_id', $v))
```

Response 帶 `project` relation（`with(['supplier', 'template', 'project'])`）

### 6. SaqProjectDetailView 審核按鈕

依 SAQ status 顯示動作按鈕，呼叫新 `/saqs/{saq}/` 路由。退回需彈出 comment modal。

### 7. Status label 前端映射

集中在一個 util/constant 檔，所有 View 引用同一份（避免散落多處）。
