# Spec: template-draft-publish

## 概述

範本採 draft/publish 版本管理：任何題目或基本資訊編輯自動建立 draft，使用者確認後發佈為新版（整數遞增），舊版封存可檢視。

## 狀態定義

| status | 說明 |
|--------|------|
| `published` | 正式版，可被專案快照，可被推薦 |
| `draft` | 草稿，不可被快照，不出現在推薦清單 |
| `archived` | 封存，不可編輯，不可快照，可檢視 |

## 版本號規則

- 整數，從 1 開始
- 每次 publish 時：新版 = 舊版 + 1
- draft 的 version 與其 draft_of 版本相同（發佈時才遞增）

## Draft 建立條件

下列操作若範本無 draft，自動建立 draft：
- PUT 更新範本基本資訊
- POST 新增題目
- PUT/PATCH 編輯題目
- DELETE 刪除題目
- PATCH 排序題目

若已有 draft，直接在 draft 上操作（不重複建立）。

## Publish 條件

- 必須有對應的 draft（`draft_of = template.id`）
- draft 自動升版並成為新 published
- 舊 published → archived

## 約束

- `status = 'draft'` 的範本不出現在 `GET /settings/questionnaire-templates` 列表（使用者在詳情頁操作 draft）
- `status = 'archived'` 出現在封存 Tab，所有題目操作 disabled
- `is_active` 只對 published 版有意義；draft/archived 固定 is_active = false

## 驗收條件

- 編輯範本題目後，在 TemplateDetailView 顯示「草稿中」banner
- 按「確認發佈」後，版本號遞增，舊版進入封存 Tab
- 封存版本詳情頁題目全部 disabled
- draft 範本不出現在問卷專案建立的範本選單
