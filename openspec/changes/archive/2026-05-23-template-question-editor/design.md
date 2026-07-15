## Context

SAQTemplate 已有 `questions()` hasMany 關聯，`show()` 已 eager load。SAQQuestion 有 `template_id / category / question_text / question_type / options(JSON) / weight / order / is_required`。DB 題型為 `single_choice / multiple_choice / text / number / boolean`，分類為 `E / S / G`。目前無任何題目操作路由。

## Goals / Non-Goals

**Goals:**
- 獨立頁面 `/settings/templates/:id` 顯示範本詳情 + 題目列表
- 題目 CRUD：新增（Modal）、編輯（Modal）、刪除（確認 Modal）
- 排序：上移 ↑ / 下移 ↓ 按鈕，即時更新 order
- 後端 5 個 API endpoint
- SettingsView 加「編輯題目」連結按鈕

**Non-Goals:**
- 拖拉排序（drag-and-drop）
- 題目預覽模式（供應商填寫介面）
- 批次匯入題目（CSV）
- 複製範本

## Decisions

**D1：排序策略 — 前端即時重排 + 批次儲存**
- 點 ↑/↓ 時前端立即交換兩題的 order 值並重排陣列（樂觀更新）
- 每次移動後呼叫 `PUT .../questions/:id`（只更新 order）
- 不做 batch-reorder endpoint（保持簡單，移動一次一次 API）

**D2：options JSON 結構依題型**
```
single_choice / multiple_choice：
  options: ["選項A", "選項B", "選項C"]  （字串陣列）
  UI: 動態新增/刪除選項 input

boolean（是/否）：
  options: null（不需要選項）

text / number：
  options: null
```

**D3：weight 欄位目前只做輸入，不做即時加權合計驗證**
E/S/G 三類加權合計由 FastAPI 計算，前端本次只需讓使用者輸入 0~1 的浮點數，不做 sum=1 的強制驗證（避免一次建題時的麻煩）。

**D4：頁面麵包屑導覽**
`系統設定 → 問卷範本 → {範本名稱}` ，「系統設定」可點擊返回。

**D5：後端 Controller 放 Settings 資料夾（與 QuestionnaireTemplateController 並列）**

## Risks / Trade-offs

- **category 欄位目前只有 E/S/G**：若未來要支援 ISO 26000 七大主題，需遷移。本次先限定 E/S/G。
- **order 無唯一性約束**：移動時可能產生相同 order 值，前端重排後以 index 重新賦值 1..(n) 避免。
