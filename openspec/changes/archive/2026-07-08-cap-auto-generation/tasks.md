## 1. DB Schema 擴充

- [x] 1.1 建立 migration：`caps` 加入 `triggered_by_axis`（ENUM nullable）與 `auto_generated`（boolean default false）
- [x] 1.2 建立 migration：`cap_findings` 加入 `framework`（varchar 20 nullable）、`topic_slug`（varchar 80 nullable）、`source_score`（decimal 5,2 nullable）、`threshold`（decimal 5,2 nullable）
- [x] 1.3 更新 `CAP` Model `$fillable` 加入 `triggered_by_axis`、`auto_generated`
- [x] 1.4 更新 `CAPFinding` Model `$fillable` 加入 `framework`、`topic_slug`、`source_score`、`threshold`；`$casts` 加入 `source_score`/`threshold` 為 float

## 2. CapAutoGenerationService

- [x] 2.1 建立 `app/Services/CAP/CapAutoGenerationService.php`，注入 `CapAutoGenerationService`
- [x] 2.2 實作 `generateFromRisk(RiskAssessment $ra, SAQ $saq, array $categoryScores): void` 主入口
- [x] 2.3 實作冪等保護：建立前查詢是否已有 `auto_generated=true AND saq_id=? AND triggered_by_axis=? AND status != 'closed'` 的 CAP，有則跳過
- [x] 2.4 實作 `buildAxis1Cap()`：篩 `$categoryScores` 中 value < 60 的 `iso26k.*` key，從 `question_tags` 查詢 `label_zh`，組裝 CAP + findings
- [x] 2.5 實作 `buildAxis2Cap()`：JOIN `saq_responses` → `project_questions` → `question_tag_assignments` → `question_tags`（`l1_domain='iso20400'`），GROUP BY slug AVG(raw_score) < 60，組裝 CAP + findings
- [x] 2.6 實作 `notifyHighRisk()`：寫入 `notifications` 表，對象為 `role IN ('sustain','comply')` 的 User，type = `risk_high_axis`

## 3. scoreCallback 接入

- [x] 3.1 修改 `SAQController::scoreCallback()`：在 `RiskAutoDerivationService::deriveFromSaq()` 之後，若 `$snapshotTrigger === 'submit'`（首次計分），呼叫 `CapAutoGenerationService::generateFromRisk($ra, $updated, $categoryScores)`
- [x] 3.2 確認僅 `submit` 觸發（`weight_updated` 不呼叫，冪等保護為次要防線）

## 4. CAPController 更新

- [x] 4.1 `store()` 驗證規則新增：`triggered_by_axis`（nullable, in:axis1,axis2,axis3）、`auto_generated`（nullable boolean）、finding 的 `framework`、`topic_slug`、`source_score`、`threshold`
- [x] 4.2 `show()` 回傳的 findings 加入 `topic_label_zh`（runtime JOIN question_tags.label_zh by topic_slug）

## 5. 前端顯示

- [x] 5.1 `CAPView.vue`（CAP 列表）：加入 `auto_generated` 標籤 badge（「自動生成」）與 `triggered_by_axis` 顯示（「軸1 / 軸2」）
- [x] 5.2 CAP 詳情頁 finding 列表：若 finding 有 `framework`，顯示 `topic_label_zh`（`iso26k`→綠色, `iso20400`→藍色）、`source_score` / `threshold` 進度條；無 framework 則顯示舊版 `category` E/S/G chip
- [x] 5.3 建立 `TOPIC_SLUG_LABELS` 前端常數對映（iso26k.*/iso20400.* → 中文），作為 API 回傳 `topic_label_zh` 的前端備援

## 6. Seed 資料

- [x] 6.1 更新 `DualRiskDashboardDemoSeeder` 或建立新 seeder，對 axis extreme 的供應商模擬呼叫 `CapAutoGenerationService`（或直接 insert demo CAP + findings），讓開發環境有可 DEMO 的自動 CAP 資料
