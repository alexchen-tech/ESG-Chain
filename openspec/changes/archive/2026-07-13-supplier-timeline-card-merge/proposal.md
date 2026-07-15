## Why

供應商風險歷史時間軸中，SAQ 推導 RA 的評估週期卡分為兩個子區塊（問卷評分 + 推導風險評估），但當 RA 是自動從 SAQ 複製六維分數時，下方的「推導風險評估」區塊不提供任何額外資訊——六維橫條與 SAQ 分數完全相同，且日期通常也一致，造成視覺冗餘。

## What Changes

- 合併評估週期卡：移除「⚡ 推導風險評估」分隔行，將六維分數橫條移至 SAQ 問卷評分區塊下方，CAP badge 移至標頭列
- 卡片結構改為單一區塊：問卷評分標頭（grade、score、CAP、查看連結）+ 六維分數橫條
- 地緣事件 RA 卡與手動 RA 卡**維持現狀**（有獨立來源，仍需分隔顯示）
- 移除 SAQ-only 卡底部「尚未推導風險評估」提示文字（目前已透過 source_saq_id 修正，該文字應不再出現，確認後移除）

## Capabilities

### New Capabilities

（無，僅調整現有 UI 呈現）

### Modified Capabilities

- `supplier-risk-timeline`: 評估週期卡結構從雙區塊合併為單區塊（SAQ 推導 RA 情境）
- `supplier-risk-history`: 風險歷史 UI 呈現規格對應更新

## Impact

- `esgchain-web/src/views/suppliers/SupplierDetailView.vue`：評估週期卡 template 區段
- 不影響後端 API、資料模型、RA 記錄邏輯
