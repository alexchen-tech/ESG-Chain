## ADDED Requirements

### Requirement: 比較籃 Pinia store

系統 SHALL 提供 `useCompareStore`（`src/stores/compareStore.ts`），管理待比較的供應商清單，上限 4 家。Store SHALL 提供 `suppliers`（陣列）、`canAdd`（computed，目前數量 < 4）、`add(supplier)`、`remove(id)`、`clear()` 方法。

#### Scenario: 加入供應商至比較籃

- **WHEN** 使用者點擊「加入比較」
- **THEN** 該供應商 SHALL 被加入 `compareStore.suppliers`，若已存在則不重複加入

#### Scenario: 比較籃已滿時無法加入

- **WHEN** `compareStore.suppliers.length === 4` 且使用者點擊「加入比較」
- **THEN** 加入動作 SHALL 被阻止，按鈕 SHALL 顯示為 disabled 狀態並帶 tooltip「已達上限 4 家」

#### Scenario: 從比較籃移除供應商

- **WHEN** 使用者點擊移除
- **THEN** 對應供應商 SHALL 從 `compareStore.suppliers` 移除，`canAdd` 重新計算

---

### Requirement: 風險矩陣 panel 加入比較入口

風險矩陣右側 panel 的每張供應商卡 SHALL 在右上角顯示「+ 比較」icon 按鈕。

#### Scenario: 點擊加入比較

- **WHEN** 使用者點擊供應商卡的「+ 比較」按鈕
- **THEN** 該供應商 SHALL 被加入比較籃，按鈕狀態切換為「已加入」（綠色 check icon）

#### Scenario: 已在比較籃中的廠商狀態顯示

- **WHEN** 某供應商已在比較籃中
- **THEN** 其卡片上的按鈕 SHALL 顯示為「已加入」狀態，再次點擊 SHALL 移除（toggle 行為）

#### Scenario: 比較籃有 2 家以上時顯示「開始比較」

- **WHEN** `compareStore.suppliers.length >= 2`
- **THEN** panel footer 區域 SHALL 顯示「比較 N 家廠商 →」按鈕，點擊開啟 CompareModal

---

### Requirement: 供應商清單 checkbox 加入比較入口

`SuppliersView` SHALL 在列表左側提供 checkbox column，允許 multi-select。底部 SHALL 有 sticky bar，在選取 1 家以上時顯示。

#### Scenario: 選取廠商後顯示 sticky 比較列

- **WHEN** 使用者勾選至少 1 家供應商
- **THEN** 頁面底部 SHALL 出現 sticky bar，顯示「已選 N 家」及「開始比較」按鈕（N >= 2 才可點擊）

#### Scenario: 從清單加入超過 4 家

- **WHEN** 使用者嘗試勾選第 5 家
- **THEN** 第 5 家的 checkbox SHALL 無法被勾選（disabled），並顯示 tooltip「最多比較 4 家」

#### Scenario: 清單 checkbox 與比較籃 store 同步

- **WHEN** 使用者從風險矩陣加入某廠商至比較籃後切換至供應商清單
- **THEN** 該廠商的 checkbox SHALL 顯示為已勾選狀態（與 store 同步）

---

### Requirement: CompareModal 並排呈現比較資料

`CompareModal` SHALL 以全屏 Modal（Teleport 至 body）並排呈現最多 4 家供應商的比較資料。每欄寬度平均分配（min 200px），Modal 內容區 SHALL 支援橫向捲動。

#### Scenario: 開啟 CompareModal

- **WHEN** 使用者點擊「開始比較」
- **THEN** CompareModal SHALL 開啟，並排顯示各廠商的供應商名稱、國家/Tier、SAQ 分數 + grade、E/S/G/GP 風險分數、Open CAP 數量

#### Scenario: 最佳/最差值標注

- **WHEN** CompareModal 顯示 SAQ 整體分數
- **THEN** 分數最高的欄 SHALL 以綠色背景標注，最低的欄 SHALL 以紅色背景標注；風險維度分數最高（最嚴重）SHALL 以紅色標注

#### Scenario: 某廠商無 SAQ 資料

- **WHEN** 被比較的廠商之一尚無已計分的 SAQ
- **THEN** 該廠商的 SAQ 欄 SHALL 顯示「尚無 SAQ」佔位文字，不影響其他廠商的標注邏輯

#### Scenario: 從 CompareModal 移除廠商

- **WHEN** 使用者點擊廠商欄標頭的「× 移除」
- **THEN** 該廠商 SHALL 從比較籃移除，CompareModal 欄數縮減；若剩餘 0 家則 Modal 自動關閉
