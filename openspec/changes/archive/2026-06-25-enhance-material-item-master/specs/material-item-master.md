target: openspec/specs/material-item-master/spec.md
action: append

---

### Requirement: 料號詳情頁（MaterialItemDetailView）
系統 SHALL 提供獨立詳情頁 `/materials/items/:id`，讓使用者在單一頁面存取料號的所有維度資訊。詳情頁採用 Tab 導覽，內容包含：基本資料、碳排資料庫、來源供應商、化學組成四個 Tab，各 Tab 採 lazy loading（切換時才呼叫 API）。

#### Scenario: 進入詳情頁
- **GIVEN** 使用者在物料主檔列表點擊「詳情」按鈕
- **WHEN** 進入 `/materials/items/{id}`
- **THEN** 頁面顯示料號代碼（accent 色、monospace）、品名、HS Code、物料群組作為 subtitle，右上角顯示啟用狀態 badge 與「編輯」按鈕；預設顯示「基本資料」Tab

#### Scenario: 料號不存在時
- **WHEN** `GET /api/v1/material-items/{id}` 回傳 404
- **THEN** 頁面顯示「料號不存在」空狀態

#### Scenario: 基本資料 Tab
- **WHEN** 使用者停留在「基本資料」Tab
- **THEN** 顯示 3 欄 grid：料號代碼、品名、HS Code、物料群組、計量單位、淨重、說明（全欄）；以及「可回收成分」子區塊顯示 PCR、PIR、Bio-based 百分比與可回收性評級

#### Scenario: 從詳情頁編輯料號
- **WHEN** 使用者點擊「編輯」按鈕並修改欄位後儲存
- **THEN** 呼叫 `PUT /api/v1/material-items/{id}`，成功後頁面即時更新，不需重新整理；item_code 不可修改（disabled）

### Requirement: 料號可回收成分細項（三欄位擴充）
MaterialItem SHALL 支援三個可回收相關欄位：`pir_percentage`（製程廢料回收比例）、`bio_based_percentage`（生物基材料比例）、`recyclability_rating`（可回收性評級，枚舉 high/medium/low/not_rated）。現有 `pcr_percentage` 欄位保留不重命名。

#### Scenario: 列表頁顯示回收成分
- **WHEN** 使用者瀏覽物料主檔列表
- **THEN** 每列顯示「回收成分」欄：pcr_percentage > 0 顯示綠底 PCR badge；pir_percentage > 0 顯示藍底 PIR badge；兩者皆為 null/0 則顯示「—」

#### Scenario: 更新可回收欄位
- **WHEN** 呼叫 `PUT /api/v1/material-items/{id}` 帶有 pir_percentage / bio_based_percentage / recyclability_rating
- **THEN** 欄位驗證：百分比欄位須為 numeric 且介於 0~100；recyclability_rating 須為 high/medium/low/not_rated 其一；更新成功後回傳完整的 MaterialItem 物件

#### Scenario: recyclability_rating 不合法值
- **WHEN** 傳入 recyclability_rating 不在枚舉值內
- **THEN** 系統回傳 422 驗證錯誤，說明允許的值

### Requirement: 物料主檔列表 UX 精簡
物料主檔列表頁操作欄 SHALL 只保留「詳情」、「編輯」、「✕」三個按鈕；碳排、供應商、化學、回收四個功能按鈕移除，統一由詳情頁提供。列表頁新增行號欄（`#`）與「回收成分」欄。

#### Scenario: 列表分頁
- **WHEN** 列表超過 20 筆
- **THEN** 使用全域 `.pagination` + `.pg-btn` class，顯示「第 N / M 頁」，分頁位置與供應商列表一致

#### Scenario: 列表篩選清除
- **WHEN** 使用者設定了任一篩選條件（搜尋字串、群組、僅啟用）
- **THEN** 出現「✕ 清除」按鈕，點擊後重設所有篩選條件並重新載入第一頁
