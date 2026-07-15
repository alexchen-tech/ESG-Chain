## 1. 資料庫 Migration

- [x] 1.1 建立 migration：`caps` 表加 `source_type` ENUM('saq','compliance_doc','manual') DEFAULT 'manual'
- [x] 1.2 同一 migration 加 `source_id` UUID nullable，加 index
- [x] 1.3 部署 migration 至容器（docker cp + migrate）

## 2. CAP Model 更新

- [x] 2.1 `CAP` model `$fillable` 加入 `source_type`、`source_id`
- [x] 2.2 `CAP` model `$casts` 加入 `source_type`（string），確保 `source_id` 正確處理 UUID
- [x] 2.3 `CAP` model 加 `complianceDoc()` BelongsTo 關聯（`source_id → supplier_compliance_docs.id`）
- [x] 2.4 複製更新後的 model 至容器

## 3. ComplianceDocExpiryJob

- [x] 3.1 建立 `app/Jobs/ComplianceDocExpiryJob.php`，繼承 `ShouldQueue`
- [x] 3.2 Job 內以 chunk(100) 查詢 `expires_at <= today + 30 days` 且 `expires_at` 不為 null 的文件
- [x] 3.3 對每筆文件查詢是否已有 `source_id = doc->id AND status IN ('open','in_progress')` 的 CAP
- [x] 3.4 無有效 CAP 時，依到期距離計算 priority（< 7 天 → critical，其他 → high）
- [x] 3.5 建立 CAP：`source_type='compliance_doc'`、`source_id=doc->id`，title 格式：「[doc_type] 合規文件即將到期 — {supplier_name}」
- [x] 3.6 同步建立 CAPFinding：`category='G'`，finding 填入文件名稱與到期日描述
- [x] 3.7 已過期（expires_at < today）的文件 priority 強制為 critical，title 標示「已過期」
- [x] 3.8 複製 Job 至容器

## 4. Artisan 指令

- [x] 4.1 建立 `app/Console/Commands/CheckComplianceExpiry.php`（`compliance:check-expiry`）
- [x] 4.2 加入 `--dry-run` option：列出將觸發的文件清單，不實際建立 CAP
- [x] 4.3 正式執行時輸出摘要（已建立 N 筆 CAP，跳過 M 筆）
- [x] 4.4 在 `app/Console/Kernel.php` 加入排程：`->daily()->at('01:00')`（或 Laravel 11 的 `routes/console.php` Schedule 寫法）
- [x] 4.5 複製 Command 與 Kernel/console routes 至容器

## 5. 前端：CAP 列表 source badge

- [x] 5.1 確認 CAP 列表 API 回傳 `source_type` 與相關欄位
- [x] 5.2 在 CAP 列表頁（`CAPView.vue`）的來源欄位加入 badge：`compliance_doc` 顯示「合規文件」標籤（橘色），`saq` 顯示「問卷」，`manual` 顯示「手動」
- [x] 5.3 badge 點擊或 hover 時顯示文件名稱（`source_id` 對應的 `file_name`）
- [x] 5.4 複製更新後的 Vue 檔案至容器

## 6. 驗收測試

- [x] 6.1 手動執行 `php artisan compliance:check-expiry --dry-run`，確認列出現有 expiring_soon / expired 文件
- [x] 6.2 手動執行 `php artisan compliance:check-expiry`，確認 CAP 正確建立（priority、source_type、title）
- [x] 6.3 再次執行，確認重複保護生效（不重複建立）
- [x] 6.4 在瀏覽器 CAP 列表頁確認 source badge 正確顯示
