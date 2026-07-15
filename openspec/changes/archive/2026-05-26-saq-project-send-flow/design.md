# Design: saq-project-send-flow

## Architecture Overview

```
┌─────────────────────────────────────────────────────┐
│              Frontend Flow                           │
│                                                     │
│  /questionnaires/projects                           │
│    └── SaqProjectsView.vue                         │
│         ├── Tab: 全部/草稿/進行中/已結案             │
│         ├── [建立] → CreateProjectModal             │
│         └── 點擊列 → /questionnaires/projects/:id   │
│                                                     │
│  /questionnaires/projects/:id                       │
│    └── SaqProjectDetailView.vue                     │
│         ├── 進度摘要卡片                             │
│         ├── SAQ 供應商列表                           │
│         ├── [發送] → SendModal                      │
│         └── [結案] → confirm → close API            │
└─────────────────────────────────────────────────────┘
```

## Backend Changes

### SaqProject Model — Status Machine

```php
const STATUS_TRANSITIONS = [
    'draft'  => ['active'],
    'active' => ['closed'],
    'closed' => [],
];

public function transitionStatus(string $new): void
{
    if (!in_array($new, self::STATUS_TRANSITIONS[$this->status] ?? [])) {
        throw new InvalidStatusTransitionException($this->status, $new);
    }
    $this->update(['status' => $new, 'closed_at' => $new === 'closed' ? now() : $this->closed_at]);
}
```

### SaqProjectController::send() — Multi-Supplier

```php
public function send(Request $request, SaqProject $project): JsonResponse
{
    if ($project->status === 'closed') {
        return response()->json(['message' => '已結案的專案不能再發送'], 422);
    }

    $supplierIds = $request->validate(['supplier_ids' => 'required|array|min:1', 'supplier_ids.*' => 'uuid'])['supplier_ids'];

    // 取得已發送的供應商 ID
    $existing = $project->saqs()->pluck('supplier_id')->toArray();

    $created = 0; $skipped = 0;
    foreach ($supplierIds as $supplierId) {
        if (in_array($supplierId, $existing)) { $skipped++; continue; }
        $project->saqs()->create(['supplier_id' => $supplierId, ...]);
        $created++;
    }

    if ($project->status === 'draft' && $created > 0) {
        $project->transitionStatus('active');
    }

    return response()->json(['success' => true, 'created' => $created, 'skipped' => $skipped]);
}
```

### New Route: close

```php
Route::post('saq-projects/{project}/close', [SaqProjectController::class, 'close']);
```

## Frontend: CreateProjectModal

欄位：
- `name` text（必填）
- `template_id` select（從 `/api/v1/settings/questionnaire-templates` 載入）
- `domain_tag_id` select（L1 tags，從 `/api/v1/settings/tags?level=1` 載入）
- `due_date` date（選填）

建立後 domain 鎖定（display-only）。

## Frontend: SendModal

```
┌──────────────────────────────────────┐
│ 發送給供應商                   [×]   │
├──────────────────────────────────────┤
│ [選擇群組] [搜尋供應商]              │
├──────────────────────────────────────┤
│ ☑ 台灣廠商群 (12家)                 │
│   ├── ☑ 台積供應 TW01              │
│   ├── ☑ 聯發科協 TW02              │
│   └── ☐ 南亞電路 TW03 [已發送]     │
│                                      │
│ ☐ 東南亞夥伴群 (8家)               │
├──────────────────────────────────────┤
│ 已選 10 家                  [確認發送]│
└──────────────────────────────────────┘
```

## Frontend: API Module (saq.ts additions)

```typescript
export const saqProjectsApi = {
  list: (params?) => http.get('/api/v1/saq-projects', { params }),
  get: (id) => http.get(`/api/v1/saq-projects/${id}`),
  create: (data) => http.post('/api/v1/saq-projects', data),
  send: (id, supplierIds) => http.post(`/api/v1/saq-projects/${id}/send`, { supplier_ids: supplierIds }),
  close: (id) => http.post(`/api/v1/saq-projects/${id}/close`),
}
```

## Migration: saq_projects table

```sql
ALTER TABLE saq_projects ADD COLUMN closed_at TIMESTAMP NULL AFTER status;
```

status 欄位已存在，只需確保預設值為 `'draft'`（已有）。
