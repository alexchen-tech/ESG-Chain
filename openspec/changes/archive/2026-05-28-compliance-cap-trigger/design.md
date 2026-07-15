## Context

目前 CAP（矯正行動計畫）的觸發來源只有 SAQ 問卷，透過 `saq_id` FK 連結。`SupplierComplianceDoc` 有動態計算的 `status`（valid / expiring_soon / expired / pending），但沒有任何觸發輸出。採購商必須主動進入合規看板才能察覺問題，無法形成閉環。

相關程式碼：
- `app/Models/CAP.php` — `saq_id nullable FK`，`source_type` / `source_id` 尚不存在
- `app/Models/SupplierComplianceDoc.php` — `getStatusAttribute()` 動態計算，無 Observer
- `app/Console/` — 目前無任何 scheduled job

## Goals / Non-Goals

**Goals:**
- 每日掃描合規文件，對 `expiring_soon`（30 天內）和 `expired` 文件自動建立 CAP
- CAP 可追溯觸發來源（`source_type: compliance_doc`，`source_id → SupplierComplianceDoc.id`）
- 重複觸發保護：同一文件的 open/in_progress CAP 存在時不重複建立
- CAP priority 依到期距離自動決定

**Non-Goals:**
- 不發 Email / Notification 通知（通知系統是獨立模組）
- 不自動關閉 CAP（需人工確認文件已更新）
- 不處理 `expires_at = null` 的文件（`pending` 狀態不觸發）

## Decisions

### D1：Scheduled Job 而非 Observer

**選擇**：Laravel `Schedule` + `ComplianceDocExpiryJob`，每日 UTC 01:00 執行。

**理由**：「到期」是時間驅動事件，不是資料變更事件。Observer 在 `saving` / `saved` 時觸發，無法感知「今天距離到期剩 N 天」這個條件。Scheduler 更直覺，也方便補跑（`artisan compliance:check-expiry`）。

**排除**：Event-driven（Observer）— 僅在文件更新時觸發，對靜止不動的過期文件無效。

---

### D2：CAP 加 source_type / source_id，而非新建 compliance_cap 表

**選擇**：在 `caps` 表加兩欄：
```
source_type  ENUM('saq', 'compliance_doc', 'manual')  DEFAULT 'manual'
source_id    UUID nullable
```

**理由**：CAP 的業務語意不變，只是多了來源資訊。拆表會讓 CAP 列表查詢複雜化，且現有前端元件只需加 badge，不需重構。`saq_id` 保留為 legacy FK，逐步遷移：當 `source_type = saq` 時 `source_id` 與 `saq_id` 同值。

**排除**：獨立 `compliance_caps` table — 過度設計，增加 JOIN 複雜度。

---

### D3：重複觸發保護以 (supplier_id, source_id) 為 key

```
查詢：caps WHERE source_id = $doc->id
         AND status IN ('open', 'in_progress')
         AND source_type = 'compliance_doc'
```

**理由**：`source_id` 唯一指向一張合規文件，比 `(supplier_id, doc_type)` 更精確。同供應商同類型但不同文件 ID 的情況（如換版 CMRT）應各自觸發。

---

### D4：CAPFinding category 使用 'G'（治理）

合規文件屬於供應鏈治理議題（ISO 26000 — 公平營運實踐），對應 ESG 分類中的 G。`finding` 欄位自動填入：
```
"合規文件 {file_name}（{doc_type}）將於 {expires_at} 到期，請更新並上傳最新版本。"
```

## Risks / Trade-offs

| 風險 | 緩解 |
|------|------|
| Job 執行失敗導致 CAP 未建立 | Artisan command 加 `--dry-run` 選項，CI 可驗證；Laravel Scheduler 失敗自動 log |
| 大量供應商同時到期造成批次建立效能問題 | Job 內用 chunk 逐批處理，每批 100 筆 |
| `expires_at` 資料不準確觸發誤 CAP | CAP 可人工關閉（`closed` status）；重新觸發保護只擋 open/in_progress，不擋 closed |
| source_type migration 影響現有 CAP | DEFAULT 'manual'，舊資料自動填 manual，不需資料遷移 |

## Migration Plan

1. 執行 migration：`caps` 加 `source_type` / `source_id` 欄位
2. 部署新程式碼（Job + Command + Model 更新）
3. 手動執行一次 `php artisan compliance:check-expiry` 驗證輸出（dry-run 模式先跑）
4. 確認 CAP 列表頁 source badge 顯示正確後，啟用 Scheduler

Rollback：移除 Scheduler 設定即可停止觸發；migration rollback 移除兩欄（現有 CAP 不受影響）。
