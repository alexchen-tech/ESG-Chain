## 1. question_tags Schema 擴充

- [x] 1.1 Migration：`question_tags` 新增 `default_weight DECIMAL(5,4) NULL` 欄位
- [x] 1.2 Migration：`question_tags` 新增 `label_en VARCHAR(100) NULL`（若尚未存在）
- [x] 1.3 更新 `QuestionTag` Model：`$fillable` 加入 `default_weight`，`$casts` 加入 `'default_weight' => 'float'`

## 2. question_tags L2 節點重建

- [x] 2.1 刪除 ESG 舊 L2 節點（`esg.e.general` / `esg.g.general` / `esg.s.general`）
- [x] 2.2 新增 ESG L2 節點：`esg.env`（環境）/ `esg.soc`（社會）/ `esg.gov`（治理），各設 default_weight = 0.40/0.35/0.25
- [x] 2.3 刪除 ISO20400 錯誤 L2 節點（7 個 iso20400.*.general ISO26000主題行）
- [x] 2.4 新增 ISO20400 L2 節點：`iso20400.policy` / `.due_diligence` / `.action` / `.reporting` / `.capacity` / `.stakeholder`，各 default_weight = 1/6
- [x] 2.5 新增 ISO26000 L2 節點（7個）：`iso26000.governance`（0.20）/ `.hr`（0.15）/ `.labor`（0.15）/ `.environment`（0.20）/ `.fairop`（0.15）/ `.consumer`（0.10）/ `.community`（0.05）
- [x] 2.6 新增 Geo-Risk L2 節點（4個）：`georisk.political`（0.30）/ `.environmental`（0.25）/ `.social`（0.25）/ `.regulatory`（0.20）
- [x] 2.7 新增 ISO28000 L2 節點（4個）：`iso28k.physical` / `.cert` / `.cargo` / `.infosec`，各 default_weight = 0.25
- [x] 2.8 新增 Product-Compliance L2 節點（4個）：`prod_comp.cbam` / `.eudr` / `.chem` / `.trace`，各 default_weight = 0.25
- [x] 2.9 驗證：每個 l1_domain 下 L2 節點 default_weight 總和 = 1.0

## 3. question_tag_assignments 清理

- [x] 3.1 刪除所有指向 General 節點（l3_topic='General'）的 question_tag_assignments 記錄（目前 720 筆）
- [x] 3.2 重置 `saq_questions.tags` 為空陣列（廢棄現有 {framework,pillar,weight} 格式）
- [x] 3.3 重置 `saq_questions.compliance_domains` 為空陣列

## 4. saq_questions.tags 格式遷移

- [x] 4.1 `SAQQuestion::VALID_FRAMEWORKS` 和 `VALID_PILLARS` 常數改為從 question_tags 動態讀取（或移除靜態常數）
- [x] 4.2 `SAQQuestion` Model：`tags` cast 改為簡單字串陣列（JSON array of strings）
- [x] 4.3 `QuestionBankController`：`tags.*` 驗證改為「每個元素必須是存在的 L3 slug」（`exists:question_tags,slug` + `l3_topic != 'General'`）
- [x] 4.4 `SAQQuestion::syncComplianceDomains()`：從 slug 前綴推導 l1_domain，更新 compliance_domains

## 5. 計分 API 新端點（question_tags L2 操作）

- [x] 5.1 建立 `QuestionTagWeightController`（或在現有 controller 擴充）
- [x] 5.2 `GET /api/v1/settings/tag-library/l2-nodes`：回傳所有 L2 節點（依 l1_domain 分組，含 default_weight）
- [x] 5.3 `PUT /api/v1/settings/tag-library/l2-nodes/{id}/weight`：更新單一 L2 節點的 default_weight，驗證 l3_topic='General'

## 6. assessment_series.pillar_weights 遷移

- [x] 6.1 確認 `pillar_weights` 欄位無現存資料（預期 0 筆）
- [x] 6.2 更新 `AssessmentSeriesService`（或相關 service）：新建 series 時從 question_tags L2 節點讀 default_weight 填入 `pillar_weights`（key = L2 節點 UUID）
- [x] 6.3 更新計分讀取：pillar_weights 的 key 為 question_tags UUID，JOIN question_tags 取 slug 與 default_weight

## 7. FrameworkDefaultWeightPanel.vue 改版

- [x] 7.1 API 呼叫從 `frameworkDefaultWeightsApi` 改為 `/api/v1/settings/tag-library/l2-nodes`
- [x] 7.2 FRAMEWORKS 常數改為從 API 動態載入（按 l1_domain 分組）
- [x] 7.3 存檔呼叫改為 `PUT /api/v1/settings/tag-library/l2-nodes/{id}/weight`（逐筆更新）
- [x] 7.4 Tab 標籤維持 `E1 · ESG` 格式（前端靜態 E-code 對照表）

## 8. 廢棄 framework_default_weights

- [x] 8.1 確認無任何 PHP/Vue/Python 程式碼仍引用 `framework_default_weights` 表或 `FrameworkDefaultWeightsController`
- [x] 8.2 Migration：DROP TABLE `framework_default_weights`
- [x] 8.3 刪除 `FrameworkDefaultWeightsController.php`（若已被新 controller 替代）
- [x] 8.4 移除舊 API route：`/api/v1/settings/framework-default-weights`

## 9. esgchain-ai 計分任務更新

- [x] 9.1 `score_saq_v2`：pillar weight 查詢改為呼叫 `/api/v1/settings/tag-library/l2-nodes` 或直接讀 PostgreSQL（若有 cross-DB 快取機制）
- [x] 9.2 確認 `active_modules`（E-code）→ l1_domain 的對照表存在且正確
- [x] 9.3 驗證六維計分輸出 dim_e1–dim_e6 與新計分路徑一致

## 10. 驗證

- [x] 10.1 驗證 `SHOW TABLES LIKE 'framework_default_weights'` 回傳 0 筆
- [x] 10.2 驗證 6 個框架各有對應 L2 節點且 weight 總和為 1.0
- [x] 10.3 驗證 GET `/api/v1/settings/tag-library/l2-nodes` 回傳 28 個 L2 節點（3+6+7+4+4+4）
- [x] 10.4 端對端：題目掛 L3 tag → 計分引擎可正確取到 L2 weight
