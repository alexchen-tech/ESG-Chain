## 1. 資料模型：risk_assessments 加 source_saq_id

- [x] 1.1 建立 migration：`ALTER TABLE risk_assessments ADD COLUMN source_saq_id CHAR(36) NULL`，加 FK constraint → `saqs.id`，ON DELETE SET NULL
- [x] 1.2 更新 `RiskAssessment` Model：`$fillable` 加入 `source_saq_id`，`$casts` 加 `'source_saq_id' => 'string'`
- [x] 1.3 更新 `RiskAutoDerivationService::deriveFromSaq()`：auto-create 時將 `source_saq_id` 填入觸發 SAQ 的 ID
- [x] 1.4 執行 migration：`docker exec esgchain-api php artisan migrate`

## 2. 後端：SupplierTimelineService

- [x] 2.1 建立 `app/Services/Suppliers/SupplierTimelineService.php`
- [x] 2.2 實作 `getEvents(string $supplierId): array`：查詢所有有 score 的 SAQ（含 `submitted_at`、`score`、`grade`、`score_e`、`score_s`、`score_g`、`status`）
- [x] 2.3 查詢所有 `risk_assessments`（含 `source_saq_id`），批次 LEFT JOIN `saqs`（`linked_saq`）
- [x] 2.4 批次載入 CAP：`WHERE source_type = 'risk_assessment' AND source_id IN (ra_ids)`，group by `source_id`
- [x] 2.5 組合事件陣列：`risk_assessment` 事件帶入 `linked_saq` + `caps`；`saq_scored` 事件帶入 `linked_ra`（`source_saq_id` 反查）
- [x] 2.6 實作 `getPendingSaq(string $supplierId): ?array`：取最新 SAQ，若 `score IS NULL` 且 `status IN ('submitted','under_review')` 則回傳

## 3. 後端：API endpoint

- [x] 3.1 `SupplierController` 加 `timeline(Supplier $supplier): JsonResponse`，呼叫 `SupplierTimelineService`，回傳 `{ events, pending_saq }`
- [x] 3.2 `routes/api.php` 加 `Route::get('suppliers/{supplier}/risk-timeline', [SupplierController::class, 'timeline'])`（放在 auth middleware 群組內）
- [x] 3.3 docker cp + restart + curl 驗證 endpoint 回傳結構正確

## 4. 前端：比較籃 store

- [x] 4.1 建立 `src/stores/compareStore.ts`：`suppliers: Supplier[]`、`canAdd` computed（length < 4）、`add(s)`（防重複）、`remove(id)`、`clear()`
- [x] 4.2 compareStore 採用 defineStore，由各 View import 使用（Pinia 無需額外 register）

## 5. 前端：CompareModal component

- [x] 5.1 建立 `src/components/CompareModal.vue`（Options API，Teleport to body）
- [x] 5.2 Modal 使用 `useCompareStore`，監聽 `suppliers` 變化
- [x] 5.3 實作表頭欄：供應商名稱、國家/Tier、onboarding_stage，加「× 移除」按鈕
- [x] 5.4 實作 SAQ 區塊：整體分數 + grade chip + E/S/G 子分數，最高分欄綠底、最低分欄紅底
- [x] 5.5 實作風險四維度區塊：E/S/G/GP 各顯示 `prob × impact = score`，最高分（最嚴重）紅色標注
- [x] 5.6 實作 Open CAP 數量列
- [x] 5.7 Modal 內容區加 `overflow-x: auto`，支援 4 欄在窄螢幕橫向捲動
- [x] 5.8 `suppliers.length === 0` 時自動關閉 Modal

## 6. 前端：風險矩陣 panel 加入比較入口

- [x] 6.1 `RiskMatrixView.vue` 的 `sc-card` 加「+ 比較」icon 按鈕（右上角），呼叫 `compareStore.add(s)`
- [x] 6.2 按鈕狀態：已在比較籃顯示 check icon（toggle 移除）；`!canAdd` 且未加入時 disabled + tooltip
- [x] 6.3 panel footer 加「比較 N 家 →」按鈕，`compareStore.suppliers.length >= 2` 時顯示，點擊開啟 CompareModal
- [x] 6.4 `data()` 加 `showCompareModal: false`，template 加 `<CompareModal v-if="showCompareModal" @close="showCompareModal=false" />`

## 7. 前端：供應商清單 checkbox 加入比較入口

- [x] 7.1 `SuppliersView.vue` 加 `selectedIds: string[]` 到 `data()`
- [x] 7.2 表格第一欄加 checkbox，綁定 `selectedIds`；第 5 家以上（`!canAdd` 且未選中）checkbox disabled
- [x] 7.3 底部 sticky bar：`selectedIds.length >= 1` 時顯示，「已選 N 家 · 開始比較」按鈕（`length >= 2` 才可點擊）
- [x] 7.4 「開始比較」：批次 `compareStore.add()`（從 selectedIds 找到對應 supplier 物件），開啟 CompareModal
- [x] 7.5 checkbox 與 compareStore 雙向同步：mounted 時將 compareStore.suppliers 的 id 反映到 selectedIds

## 8. 前端：SupplierDetailView 事件流升級

- [x] 8.1 `src/api/modules/suppliers.ts` 加 `riskTimeline(id)` → `GET /api/v1/suppliers/:id/risk-timeline`
- [x] 8.2 `SupplierDetailView.vue` 加 `timeline: null as any`、`timelineLoading: false` 到 `data()`
- [x] 8.3 `loadRiskHistory()` 改為呼叫 timeline API，結果存入 `timeline`
- [x] 8.4 移除舊的靜態 `<table>` 風險歷史區塊
- [x] 8.5 新增 pending_saq 卡：頂端黃色虛線框、spinning 指示器、提交日期
- [x] 8.6 新增 `risk_assessment` 事件卡：橙色（自動）/ 灰色（手動）左邊框，4 維度 progress bar（score/25）+ level badge；有 `linked_saq` 時顯示來源摘要；有 `caps` 時顯示 CAP 徽章
- [x] 8.7 新增 `saq_scored` 事件卡：藍色左邊框，整體分數 + grade + E/S/G 子分數三欄，「提交於」副文字
- [x] 8.8 事件流 CSS 樣式（`.tl-*` hierarchy）全部加入 `<style scoped>`

## 9. 驗收測試

- [x] 9.1 SAQ 計分後，`risk_assessments.source_saq_id` 正確填入（DB 驗證）
- [x] 9.2 `GET /api/v1/suppliers/:id/risk-timeline` 回傳正確結構，linked_saq、caps 有值
- [x] 9.3 SupplierDetailView 事件流正確顯示三種卡片，pending_saq 頂端置頂
- [x] 9.4 風險矩陣 panel「+ 比較」→ CompareModal 並排顯示，最佳/最差標注正確
- [x] 9.5 供應商清單 checkbox → sticky bar → CompareModal 流程完整
- [x] 9.6 比較籃超過 4 家時加入按鈕正確 disabled
