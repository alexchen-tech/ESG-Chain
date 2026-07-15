# Tasks: Six-Dimension Assessment

## Phase 1：資料庫 Schema 擴充

- [x] **T1** `suppliers` 新增 `industry_group` enum 欄位（製造業/勞動密集製造/農林漁業/科技電子/物流倉儲/原物料化工/服務業 + nullable）
- [x] **T2** `risk_assessments` 新增 `dim_e1`–`dim_e6`（decimal(5,2) nullable）與 `assessment_version`（varchar(10) default 'legacy'）
- [x] **T3** `saq_questions.tags` 結構定義文件（無需 migration，欄位已是 JSON），補充 JSON Schema 驗證規則至程式碼

## Phase 2：題庫標記補全（E1 最低要求）

- [x] **T4** 將現有全部 SAQ 題目至少補上 E1 tag（pillar 依 E/S/G 分類，weight 均分至 1.0），確保六維計分上線後 E1 分數可立即使用
- [x] **T5** 建立 `config/industry_module_map.php`，定義 industry_group → 加掛維度（E2/E3/E5/E6）的 mapping

## Phase 3：esgchain-api 問卷發送邏輯

- [x] **T6** `SaqService::buildQuestionSet()` 新方法：依 industry_group 查 mapping + 動態篩選 E6 題（`SalesProduct.applicable_regulations`），回傳含 module 來源標記的題集
- [x] **T7** 問卷發送時（狀態 draft → sent）建立 `project_questions` 快照，寫入 dim tag + weight
- [x] **T8** 供應商提交時必答驗證：`POST /api/v1/saq/{id}/submit` 驗證所有 `is_required=true` 題目已填答，否則 422 回傳未填題目 ID

## Phase 4：esgchain-ai 計分引擎

- [x] **T9** `score_saq_v2` Celery task：讀 project_questions 快照，依多重標記計分，輸出 dim_e1–dim_e6（純問卷分）
- [x] **T10** `compute_e4_score` Celery task：查 `country_risk_ratings.geo_risk`（最高風險廠址，fallback=3），與 E4 問卷分混合（4:6），更新 dim_e4
- [x] **T11** `compute_e6_score` Celery task：計算 regulation_pressure（法規數/最大法規數），與 E6 問卷分混合，更新 dim_e6；若無適用法規則設 null
- [x] **T12** feature flag `SIX_DIM_SCORING=true` 控制是否呼叫 v2 task（false 時沿用舊版 `score_saq`）

## Phase 5：esgchain-api score-callback 接收

- [x] **T13** `RiskAutoDerivationService` 更新：接收含 dim_e1–dim_e6 的 callback payload，upsert risk_assessments，標記 assessment_version='v2'
- [x] **T14** D6 投影規則實作：依 dim_e1–dim_e6 計算 E/S/G/GP 四軸 probability/impact（null 維度回退 probability=3），夾限 1–5

## Phase 6：前端 UI 更新

- [x] **T15** 供應商詳情頁 `SupplierDetailView.vue`：風險評估卡依 assessment_version 分流，v2 顯示六維長條圖 + E4/E6 混合來源 tooltip；legacy 顯示現有三軸
- [x] **T16** 供應商編輯頁（`SupplierEditView.vue` 或相關表單）：新增 `industry_group` 下拉選單
- [x] **T17** SAQ 問卷發送頁：顯示「將加掛模組」預覽（依 industry_group 自動計算 + 可手動覆蓋）

## Phase 7：SLA 逾期標記

- [x] **T18** 排程任務（每日 00:05）：掃描 due_date 已過且 status 為 sent/in_progress 的 SAQ，標記 `is_overdue=true`，觸發通知給採購方責任人

---

## 實作順序建議

```text
T1 T2 T3 → T4 T5 → T6 T7 T8 → T9 T10 T11 T12 → T13 T14 → T15 T16 T17 → T18
```

T12 的 feature flag 確保新舊計分可以並行部署，上線後逐步切換。
