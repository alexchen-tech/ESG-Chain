# Spec: SAQ Project UI

## Overview

SaqProject 列表與詳情頁面，作為問卷發送的主要操作入口。

## Requirements

### Page: /questionnaires/projects（列表頁）

- 顯示所有問卷專案，以 Tab 切分狀態：全部 / 草稿 / 進行中 / 已結案
- 每列顯示：專案名稱、框架（domain L1）、範本名稱、狀態 badge、已回覆/總發送數、截止日期、建立日期
- 右上角「建立問卷專案」按鈕，點擊開啟 Modal
- 建立 Modal 欄位：名稱（必填）、問卷範本（必填，下拉）、評核框架 domain（必填，L1 tag 下拉，建立後鎖定）、截止日期（選填）
- 點擊專案列導向詳情頁

### Page: /questionnaires/projects/:id（詳情頁）

- 頂部：專案名稱、狀態 badge、評核框架、截止日期、建立人
- 進度摘要卡片：已發送、已回覆、待回覆、逾期
- 供應商列表 Table：供應商名稱、層級、狀態（已回覆/待回覆/逾期）、發送時間、查看連結
- 右上角「發送給供應商」按鈕（status = draft 或 active 時可用）
- 右上角「結案」按鈕（status = active，需確認對話框）

### Navigation

- `SideBar` 的「問卷管理」下新增「問卷專案」子項目，路由 `/questionnaires/projects`

## Acceptance Criteria

- [ ] 列表頁正確顯示各狀態 Tab 及計數
- [ ] 建立 Modal 送出後立即在列表顯示新專案（status = draft）
- [ ] 詳情頁進度卡片數字與供應商列表一致
- [ ] 結案後 status 變為 closed，發送按鈕 disabled
