## Context

目前 `SupplierDetailView` 的風險歷史區塊分兩處呈現：
1. Overview tab：`risk_assessments` 靜態 table（E/S/G/GP 分數 + ↑↓ delta + 來源 chip）
2. 永續績效 tab：最新一筆 `riskSummary` bar chart

SAQ 評分歷史則分散在問卷列表中，與風險評估無法在同一視圖關聯。`risk_assessments.notes` 以字串 "自動從 SAQ" 記錄觸發來源，無法精確 JOIN。

橫向比較功能目前不存在，用戶需要手動在多個供應商詳情頁切換比對。

## Goals / Non-Goals

**Goals:**
- `risk_assessments` 加 `source_saq_id`（nullable UUID FK → `saqs.id`），精確追蹤自動建立的來源
- 後端新 `SupplierTimelineService`，一次查詢回傳 SAQ + RA + CAP 的時間排序事件流
- 前端 `SupplierDetailView` 風險歷史區塊升級為事件流，可視化因果鏈
- Pinia 比較籃 + CompareModal，支援從風險矩陣 panel 與供應商清單兩處觸發

**Non-Goals:**
- 不新增 AI 分析或自動生成比較報告
- 不修改 `/risk/matrix` 或 `/risk/assessments` 現有 API 結構
- 比較籃不做 localStorage 持久化（session 結束即清空）
- CAP 詳情編輯不在本次範圍

## Decisions

### D1：`source_saq_id` FK 加在 `risk_assessments`

**決定**：加 nullable `source_saq_id CHAR(36)` + FK constraint，`RiskAssessmentObserver` 在 auto-create 時填入。

**理由**：現有 `notes` 欄位字串比對脆弱，且無法跨語言穩定 JOIN。有了 FK，`SupplierTimelineService` 可精確 LEFT JOIN 而非時間近似比對。

**替代方案考慮**：僅靠時間窗口（assessed_at 與 created_at 差 < N 分鐘）做關聯 → 有 false-positive 風險，且對批次補建 RA 的場景不適用。

---

### D2：後端 timeline API（`/api/v1/suppliers/:id/risk-timeline`）

**決定**：新增獨立 endpoint，`SupplierController::timeline()` 呼叫 `SupplierTimelineService`，回傳統一事件陣列。

**回傳結構**：
```json
{
  "events": [
    {
      "type": "risk_assessment",
      "date": "2026-01-15T08:00:00Z",
      "risk": {
        "id": "uuid",
        "e": { "probability": 4, "impact": 3, "score": 12, "level": "medium" },
        "s": { "probability": 3, "impact": 3, "score": 9,  "level": "medium" },
        "g": { "probability": 2, "impact": 2, "score": 4,  "level": "very_low" },
        "gp":{ "probability": 5, "impact": 4, "score": 20, "level": "extreme" },
        "is_auto": true,
        "assessed_by": null,
        "notes": "自動從 SAQ 建立"
      },
      "linked_saq": {
        "id": "uuid",
        "score": 65, "grade": "C",
        "score_e": 68, "score_s": 55, "score_g": 71,
        "submitted_at": "2026-01-13T10:00:00Z"
      },
      "caps": [
        { "id": "uuid", "status": "open", "findings_count": 2 }
      ]
    },
    {
      "type": "saq_scored",
      "date": "2025-01-10T14:30:00Z",
      "saq": {
        "id": "uuid",
        "score": 72, "grade": "B",
        "score_e": 74, "score_s": 68, "score_g": 71,
        "submitted_at": "2025-01-08T09:00:00Z",
        "status": "finalized"
      },
      "linked_ra": null
    }
  ],
  "pending_saq": {
    "id": "uuid",
    "status": "submitted",
    "submitted_at": "2026-06-20T11:00:00Z"
  }
}
```

`pending_saq`：最新一筆 SAQ 若 score IS NULL 且 status 在 `submitted/under_review`，則額外回傳，前端置頂顯示 pending 卡。

**替代方案考慮**：前端各自打 `/risk/assessments` 和獨立 SAQ list API 再合併 → 需要兩次 request，且前端合併邏輯難以維護；後端能精確 JOIN 且做 eager-load 效能更好。

---

### D3：`SupplierTimelineService` 放在 Laravel

**決定**：新建 `app/Services/Suppliers/SupplierTimelineService.php`，不放在 `RiskMatrixController`。

**理由**：風險歷史是供應商維度的資料，`SupplierController` 是自然歸屬。`RiskMatrixController` 主責維度矩陣視圖，不宜承擔供應商詳情聚合邏輯。

---

### D4：前端事件流 component 架構

**決定**：在 `SupplierDetailView` 內建立獨立的 `RiskTimeline` inline component（非獨立 `.vue` 檔），保持單一頁面簡潔。三種事件卡片型別以 `v-if` 區分渲染，不拆子元件。

**事件卡片視覺規則**：
- `risk_assessment`（自動）：橙色左邊框，顯示 E/S/G/GP 4 dim bar + linked SAQ 摘要 + CAP 徽章
- `risk_assessment`（手動）：灰色左邊框，顯示 4 dim bar
- `saq_scored`：藍色左邊框，整體分數 + E/S/G 子分數 + grade chip
- `pending_saq`：頂部黃色虛線框，spinning 指示器，顯示「問卷已提交，等待計分」

---

### D5：比較籃使用 Pinia store，CompareModal 全屏 Modal

**決定**：`useCompareStore`（`src/stores/compareStore.ts`），陣列上限 4 筆，提供 `add(supplier)` / `remove(id)` / `clear()` / `canAdd` computed。CompareModal 用 Teleport 掛到 `body`，z-index 高於側欄 panel。

**UI 入口**：
1. 風險矩陣 panel 廠商卡：每張卡右上角加「+ 比較」icon 按鈕，比較籃已滿則 disabled + tooltip
2. 供應商清單：左側加 checkbox column，底部 sticky bar 顯示已選 N 家 + 「開始比較」按鈕

**CompareModal 欄位**：
- 表頭：供應商名稱、國家/Tier、onboarding_stage
- SAQ 區塊：整體分數 + grade + E/S/G 子分數（顏色標注最佳/最差）
- 風險四維度：E/S/G/GP 各顯示 probability × impact = score + level badge
- Open CAP 數量

**最佳/最差標注邏輯**：SAQ 分數最高標綠，最低標紅；風險分數（E/S/G/GP）最高（最嚴重）標紅。

## Risks / Trade-offs

**[舊資料無 source_saq_id]** → 歷史 RA 的 `source_saq_id` 為 NULL，前端以 `is_auto: true`（從 notes 判斷）顯示自動標籤，但無法點擊連結到來源 SAQ。新資料建立後才有完整連結。可接受，不需要回補。

**[比較籃不持久化]** → 頁面重整或跳轉後比較籃清空。用戶需重新選取。決定可接受（非核心決策工具），不做 localStorage 以避免過期資料問題。

**[SAQ 有多個 scoring snapshot，timeline 只顯示最終 score]** → `saqs` 表的 `score` 欄位是最新計分結果；`SaqScoreSnapshot` 是同一筆 SAQ 的重計歷程。Timeline 只顯示每筆 SAQ 的最終 `score`，不展開 snapshot 細節（細節仍可在問卷詳情頁查看）。

**[CompareModal 4 家並排在窄螢幕]** → 4 欄 × 最小 200px = 800px，在 1280px 以下會有橫向捲動。Modal 內容區加 `overflow-x: auto`。

## Migration Plan

1. 執行 migration：`ALTER TABLE risk_assessments ADD COLUMN source_saq_id CHAR(36) NULL`
2. 部署 Laravel（含更新 Observer、新增 Service/Controller action/路由）
3. 部署 Vue（含新 store、CompareModal、更新 SupplierDetailView、RiskMatrixView、SuppliersView）
4. 無需資料回填；舊 RA 記錄 `source_saq_id = NULL` 為合法狀態

**Rollback**：前端回滾只需重新部署舊版 bundle；後端 API 加的是新 endpoint，不影響現有路由；migration rollback 執行 `ALTER TABLE risk_assessments DROP COLUMN source_saq_id`。

## Open Questions

- CompareModal 是否需要「匯出為 PDF/CSV」？（本次不做，未來可加）
- 比較籃的廠商卡是否要顯示在畫面某處的 persistent floating button？（目前只有 sticky bar in 供應商清單）
