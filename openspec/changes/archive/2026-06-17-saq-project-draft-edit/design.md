## Context

`SaqProjectDetailView.vue` Header 區現有：專案名稱、狀態 badge、domain chip、
截止日文字、「發送給供應商」按鈕、「結案」按鈕。

後端 `PUT /saq-projects/{id}` 已支援 `name / due_date / description / domain`，
`domain` 在 active 後 422。`DELETE /saq-projects/{id}` 在 active 時 422。

## Goals / Non-Goals

**Goals:**
- 草稿專案可編輯四個欄位（name、due_date、description、domain）
- 進行中專案可編輯三個欄位（domain 顯示但 disabled）
- 草稿專案可刪除（含確認步驟）
- 與現有 SaqProjectsView 建立 modal 的 UX 一致

**Non-Goals:**
- 不修改 template_id / series_id（換範本會孤兒化已發送的 SAQ）
- 不在明細頁管理問卷範本題目
- 不支援 closed 狀態的任何編輯

## Decisions

### 1. Modal 而非 inline 編輯
Header 已有多個元素，inline 會過擠。Modal 與「建立專案」的模式一致，學習成本低。

### 2. 欄位差異以 `disabled` 表達，不隱藏
active 狀態的 domain 欄位保持顯示但 disabled + 說明文字「已發送後無法修改」。
讓使用者知道欄位存在但受限，比直接消失更清楚。

### 3. 刪除按鈕只在草稿顯示，在編輯 Modal 底部左側放置
將刪除動作放在編輯 Modal 內，避免 Header 按鈕區過多。
刪除需二次確認（`window.confirm` 或內嵌確認文字），確認後刪除並跳回列表頁。

### 4. 儲存後就地更新，不重載整頁
`saqProjectApi.update()` 回傳最新 project 物件，直接替換 `this.project`。
刪除後 `router.push('/questionnaires/projects')`。

### 5. 按鈕位置
```
Header 右側按鈕區（由左到右）：
  [草稿] ✏ 編輯   發送給供應商
  [進行中]  ✏ 編輯   發送給供應商   結案
  [已結案]  （無按鈕）
```

## Risks / Trade-offs

- **domain 欄位選項**：目前在 `SaqProjectsView` 建立時已有 `VALID_DOMAINS` 常數，
  直接複用，不另外打 API。若日後 domain 清單改為動態，再重構。
- **刪除草稿的連鎖**：若草稿已有 SAQ（例如剛建立就發送），
  後端 `destroy` 會 422（active 狀態），前端 catch 後顯示錯誤訊息即可。
