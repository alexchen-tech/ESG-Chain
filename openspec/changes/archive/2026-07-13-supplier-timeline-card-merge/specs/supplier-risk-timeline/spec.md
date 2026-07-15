## MODIFIED Requirements

### Requirement: 評估週期卡合併為單一區塊（SAQ 推導 RA）

系統 SHALL 將 SAQ 自動推導 RA 的評估週期卡從雙子區塊合併為單一卡片。

#### Scenario: 合併後的評估週期卡結構
- **WHEN** 時間軸渲染 `type='risk_assessment'` 且 `risk.is_auto=true` 且 `linked_saq` 存在的事件
- **THEN** 卡片顯示單一標頭列，包含：問卷圖示、「問卷評分」標籤、提交日期、grade chip、分數、CAP badge（若有）、「查看問卷 →」連結
- **AND** 標頭列下方直接顯示六維分數橫條（E1–E5，E6 若有則顯示）
- **AND** SHALL NOT 顯示「⚡ 推導風險評估」分隔行
- **AND** SHALL NOT 重複顯示 RA 的評估日期（已在標頭列以問卷提交日呈現）

#### Scenario: CAP badge 顯示位置
- **WHEN** 對應 RA 有 open CAP
- **THEN** CAP badge（⚠ N open CAP）顯示於問卷評分標頭列，位於分數與查看連結之間
- **WHEN** 對應 RA 的 CAP 全數關閉
- **THEN** 顯示「✓ N CAP closed」badge，位置同上

#### Scenario: 地緣事件 RA 卡維持雙區塊不變
- **WHEN** 時間軸渲染 `source_type='geo_event'` 的 RA 事件
- **THEN** 卡片結構維持現狀：分隔行顯示「🌍 地緣事件：{name}」+ 六維橫條
- **AND** 不受本次合併影響

#### Scenario: 手動 RA 卡維持現狀
- **WHEN** 時間軸渲染 `source_type` 非 SAQ 且非地緣事件的 RA（如 regulation_change、manual_review）
- **THEN** 卡片結構維持現狀：分隔行顯示「✎ 風險更新」+ 六維橫條

---

## MODIFIED Requirements

### Requirement: SAQ-only 卡移除「尚未推導風險評估」提示

#### Scenario: SAQ-only 卡底部提示移除
- **WHEN** 時間軸渲染 `type='saq_scored'` 的獨立 SAQ 事件（無對應 RA）
- **THEN** 卡片不顯示「尚未推導風險評估」提示文字
- **AND** 卡片僅顯示問卷評分標頭 + 六維橫條（若有分數）
