## 1. 評估週期卡合併（SAQ 推導 RA）

- [x] 1.1 在 `SupplierDetailView.vue` 評估週期卡（`v-if="ev.type === 'risk_assessment' && ev.risk.is_auto && ev.linked_saq"`）中，將六維分數橫條（`tl-period-risk` 區塊）移至問卷評分標頭（`tl-period-saq`）下方
- [x] 1.2 將 CAP badge（open / closed）從 `tl-period-divider` 移至問卷評分標頭列，位於分數 span 與「查看問卷 →」連結之間
- [x] 1.3 移除整個 `tl-period-divider` div（含「⚡ 推導風險評估」span、版本 badge、日期、CAP badge）

## 2. SAQ-only 卡清理

- [x] 2.1 移除 `tl-saq-standalone` 卡底部的「尚未推導風險評估」提示文字（`tl-saq-no-ra-hint` div）

## 3. 同步與驗證

- [x] 3.1 `docker cp` 更新後的 `SupplierDetailView.vue` 至 `esgchain-web` 容器並觸發 Vite HMR
- [ ] 3.2 瀏覽器確認 Dhaka Garment Factory Ltd. 風險歷史頁：兩筆評估週期卡各自顯示單一合併區塊，六維橫條位於標頭下方
- [ ] 3.3 確認地緣事件卡與手動 RA 卡結構不受影響
