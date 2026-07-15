## 1. 資料庫：加權表清理與補齊

- [x] 1.1 刪除 `framework_default_weights` 中 `scoring_framework IN ('E1','E2','E3','E4','E5','E6')` 的 6 列
- [x] 1.2 新增 `ISO28000` 的 4 個 pillar 行（iso28k.physical / .cert / .cargo / .infosec，各 0.25）
- [x] 1.3 新增 `Product-Compliance` 的 4 個 pillar 行（prod_comp.cbam / .eudr / .chem / .trace，各 0.25）
- [x] 1.4 驗證 `DISTINCT scoring_framework` = {ESG, ISO20400, ISO26000, Geo-Risk, ISO28000, Product-Compliance}

## 2. 資料庫：題庫 tags 遷移

- [x] 2.1 建立 migration `retag_saq_questions_to_l1_domain_framework`
- [x] 2.2 轉換 60 筆 new_object 格式（E1|E/S/G → ESG|esg.env/.soc/.gov）
- [x] 2.3 轉換 120 筆舊字串格式（23 條關鍵字規則推斷 framework + L2 pillar_slug）
- [x] 2.4 同步更新 `compliance_domains` 欄位（tags framework 去重集合）
- [x] 2.5 執行 migration，驗證 180 題全部轉換，未轉換 = 0

## 3. Laravel API：Model 與 Controller

- [x] 3.1 `SAQQuestion::VALID_FRAMEWORKS` 改為 6 個 L1 domain 名稱
- [x] 3.2 新增 `SAQQuestion::VALID_PILLARS` 常數（每個 framework 的合法 pillar_slug 清單）
- [x] 3.3 `validateTagsStructure()` 新增 pillar 交叉驗證（pillar 須屬於該 framework 的 VALID_PILLARS）
- [x] 3.4 `QuestionBankController::store()` tags 驗證規則：`in:E1,...` → `in:ESG,ISO20400,...`
- [x] 3.5 `QuestionBankController::update()` 同步更新驗證規則

## 4. Vue 前端：框架加權設定頁

- [x] 4.1 移除 `FrameworkDefaultWeightPanel.vue` 中的 E1–E6 重複 FRAMEWORKS 項目
- [x] 4.2 現有 ESG / ISO20400 / ISO26000 / Geo-Risk 四個 tab label 加上 E-code 前綴（"E1 · ESG" 格式）
- [x] 4.3 新增 ISO28000（E5）tab，pillar 使用 iso28k.* slug
- [x] 4.4 新增 Product-Compliance（E6）tab，pillar 使用 prod_comp.* slug

## 5. FastAPI 計分任務（待辦）

- [ ] 5.1 `score_saq_v2` task：將 framework 比對邏輯從 E-code 改為 L1 domain 名稱
- [ ] 5.2 確認 `active_modules`（E-code）到 `scoring_framework`（L1 domain）的轉換對照表
- [ ] 5.3 驗證六維計分輸出 dim_e1–dim_e6 與 framework 對應正確
