## Context

`saq_questions` 目前以 `tags` JSON array 混放三種語意：`["E", "ISO-勞工"]`。`category` 欄位已正確承擔 L2 ESG Pillar 角色，但 ISO 26000 七大主題沒有獨立欄位，導致篩選需要 `"tag:ISO-勞工"` 前綴 hack，且多選語意不清。Phase 1 的目標是切乾淨這兩個維度，為 Phase 2（ISO L3 細項字典）預留結構。

## Goals / Non-Goals

**Goals:**
- `iso_subject` 成為獨立的 nullable enum 欄位，單選
- `tags` 欄位清空 E/S/G 與 ISO-xxx 項目（保留欄位供未來 Phase 2 L3 細項使用）
- QuestionBankFilter 的 ISO 20400 群組改為查詢 `?iso_subject=`，ESG 群組移除（已由 `?category=` 覆蓋）
- 前端 Modal 明確呈現兩個獨立維度

**Non-Goals:**
- ISO 26000 L3 細項（Phase 2）
- L4 系統行為標籤（Phase 3）
- 範本題目（template questions）的欄位變更
- SASB topic 欄位不動

## Decisions

### 1. iso_subject 欄位設計

```sql
iso_subject ENUM(
  '組織治理','人權','勞工','環境',
  '公平營運','消費者','社區'
) NULL
```

nullable — 非每道題都需要 ISO 主題（尤其是 SASB 行業題）。不用 FK 關聯表，七大主題是 ISO 26000 固定規範，不會新增。

### 2. 資料遷移策略

現有 tags 中 `ISO-勞工` → `iso_subject = '勞工'`（移除前綴）。
`E`/`S`/`G` tag 直接刪除（category 欄位已有）。
遷移在 migration 的 `up()` 中執行，`down()` 反向還原。

### 3. QuestionBankFilter TAXONOMY 調整

```
移除前：                    移除後：
ESG 群組（查 category）      （移除，category 已有獨立 select）
ISO 20400 群組（查 tag）  →  ISO 26000 群組（查 iso_subject）
地緣政治群組（查 tag）        地緣政治群組（查 tag，暫時保留）
```

`地域風險` 仍在 tags，Phase 2 再處理。

### 4. 前端 Modal 改 radio

```
ESG 分類（必填）：   ● E 環境  ○ S 社會  ○ G 治理
ISO 26000（選填）：  ○ 組織治理 ○ 人權 ○ 勞工 ○ 環境
                    ○ 公平營運 ○ 消費者 ○ 社區
                    [清除]
```

Radio 取代 checkbox，視覺上明確表達「單選」語意。ISO 26000 加「清除」按鈕（因為選填）。

### 5. 後端 API 變更

- `store`/`update` 增加 `iso_subject` 驗證：`nullable|in:組織治理,人權,勞工,環境,公平營運,消費者,社區`
- `index` 支援 `?iso_subject=勞工` 查詢參數
- response 加入 `iso_subject` 欄位
- `DEFAULT_TAGS` 常數移除 E/S/G 與 ISO-xxx（只保留 `地域風險`）

## Risks / Trade-offs

- [資料遷移不可逆] → migration `down()` 提供還原路徑；staging 先驗證
- [現有種子資料 tags 格式不一] → migration 加容錯：`ISO-勞工` 與 `勞工` 都能處理
