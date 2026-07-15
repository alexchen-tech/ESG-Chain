# Design: Wave A — API Contract Alignment

## A1 Supplier 狀態設計

### Migration 策略

```sql
-- 新增 onboarding_stage 欄位，遷移現有 status 資料
ALTER TABLE suppliers
  ADD COLUMN onboarding_stage ENUM('potential','invited','reviewing','certified') NULL,
  MODIFY COLUMN status ENUM('active','inactive','suspended') DEFAULT 'active';

-- 資料遷移邏輯
UPDATE suppliers SET
  onboarding_stage = CASE status
    WHEN 'potential'  THEN 'potential'
    WHEN 'invited'    THEN 'invited'
    WHEN 'reviewing'  THEN 'reviewing'
    WHEN 'certified'  THEN 'certified'
    WHEN 'suspended'  THEN 'certified'
    WHEN 'terminated' THEN 'certified'
  END,
  status = CASE status
    WHEN 'potential'  THEN 'active'
    WHEN 'invited'    THEN 'active'
    WHEN 'reviewing'  THEN 'active'
    WHEN 'certified'  THEN 'active'
    WHEN 'suspended'  THEN 'suspended'
    WHEN 'terminated' THEN 'inactive'
  END;
```

### /suppliers/{id}/risk-summary 暫時回應

Wave B 實作前，回傳空結構但 HTTP 200：
```json
{
  "success": true,
  "data": {
    "supplier_id": "...",
    "e": null,
    "s": null,
    "g": null,
    "gp": null,
    "assessed_at": null
  },
  "message": "尚無風險評估資料"
}
```

---

## A2 問卷狀態機設計

### 狀態轉換圖

```
not_started ──────────────────────────────────────────┐
    │                                                  │
    │ (supplier PUT)                                   │
    ▼                                                  │
in_progress ◀─────────────────────────────────────┐   │
    │                                              │   │
    │ POST /submit                                 │   │
    ▼                                              │   │
submitted ──[POST /start-review]──▶ under_review   │   │
                                        │          │   │
                              ┌─────────┴──────┐   │   │
                              ▼                ▼   │   │
                          completed    review_returned
                              │                │   │
                    [POST /mark-reviewed]       │   │
                              ▼           (供應商修改)
                           reviewed            │   │
                           （終態）            └───┘
```

### is_editable 邏輯

```php
// 在 Questionnaire Model 的 appends 中
public function getIsEditableAttribute(): bool
{
    return !in_array($this->status, ['under_review', 'completed', 'reviewed']);
}
```

### under_review 403 鎖

在 `QuestionnaireController` 的寫入方法中加入：

```php
private function checkSupplierWriteLock(Request $request, Questionnaire $q): void
{
    $supplierRoles = ['supplier', 'sup_esg'];
    $userRole = $request->user()->getRoleNames()->first();

    if (in_array($userRole, $supplierRoles) && $q->status === 'under_review') {
        abort(403, '問卷審核中，暫時無法編輯');
    }
}
```

### QuestionnaireCounts 查詢

```php
// just_submitted_count = 管線（僅 submitted）
$justSubmitted = Questionnaire::where('status', 'submitted')->count();

// submitted_count = 累計 KPI
$submittedStatuses = ['submitted', 'under_review', 'review_returned', 'completed', 'reviewed'];
$submittedTotal = Questionnaire::whereIn('status', $submittedStatuses)->count();
```

### Migration 狀態欄位更新

```php
// 舊 ENUM 值 → 新 ENUM 值
$statusMap = [
    'pending'    => 'not_started',
    'sent'       => 'not_started',
    'in_progress'=> 'in_progress',
    'submitted'  => 'submitted',
    'reviewing'  => 'under_review',
    'approved'   => 'completed',
    'rejected'   => 'review_returned',
];
```

---

## A3 Refresh Token 設計

### Token 儲存

Refresh Token 的 `jti` 存入 Redis，key 格式：
```
esgchain:refresh_token:{jti}  →  user_id
TTL: 604800 秒（7 天）
```

### 換發流程

```
客戶端                    Laravel
  │                          │
  │── POST /auth/refresh ───▶│
  │   { refresh_token }       │── 1. 驗證 JWT 簽章
  │                          │── 2. 從 Redis 確認 jti 存在
  │                          │── 3. 刪除舊 jti（Rotation）
  │                          │── 4. 發行新 access_token + refresh_token
  │◀── 200 { tokens } ───────│── 5. 新 jti 寫入 Redis
```

### 登入回應補充

```json
{
  "success": true,
  "data": {
    "access_token": "eyJ...",
    "refresh_token": "eyJ...",
    "token_type": "bearer",
    "expires_in": 3600
  }
}
```

---

## 路由對照表（完整）

| Spec 路徑 | Laravel 路由 | Controller Method |
|-----------|-------------|-------------------|
| GET /questionnaires | GET /api/v1/questionnaires | index |
| POST /questionnaires/send | POST /api/v1/questionnaires/send | send |
| GET /questionnaires/counts | GET /api/v1/questionnaires/counts | counts |
| GET /questionnaires/{id} | GET /api/v1/questionnaires/{id} | show |
| PUT /questionnaires/{id} | PUT /api/v1/questionnaires/{id} | update |
| POST /questionnaires/{id}/submit | POST /api/v1/questionnaires/{id}/submit | submit |
| POST /questionnaires/{id}/start-review | POST /api/v1/questionnaires/{id}/start-review | startReview |
| POST /questionnaires/{id}/complete-review | POST /api/v1/questionnaires/{id}/complete-review | completeReview |
| POST /questionnaires/{id}/return-review | POST /api/v1/questionnaires/{id}/return-review | returnReview |
| POST /questionnaires/{id}/mark-reviewed | POST /api/v1/questionnaires/{id}/mark-reviewed | markReviewed |
