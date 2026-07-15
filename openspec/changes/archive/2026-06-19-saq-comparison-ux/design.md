## Context

供應商比較頁（`SeriesDetailView.vue`）是 Assessment Series 的核心視圖，包含「總分趨勢折線圖」與「逐題分數趨勢矩陣」。原始實作存在以下問題：

1. **SVG polyline 座標錯誤**：`buildPolylinePoints()` 將 X 值組合為 `"50%,80"` 字串，SVG `points` 屬性不接受百分比，導致折線完全不渲染
2. **趨勢矩陣 Map key 衝突**：後端 `getComparison()` 在 `source_template_question_id` 為 null 時，回傳欄位仍為 `null`；前端以此為 Map key，導致所有題目折疊成 1 筆
3. **SAQ 提交無必填驗證**：供應商可送出完全空白的問卷

## Goals / Non-Goals

**Goals:**
- 折線圖正確渲染，折線連接各波次分數點
- 趨勢矩陣顯示所有題目（包含 `source_template_question_id = null` 的 seeded data）
- SAQ 提交前前後端雙重驗證必填題已作答
- 矩陣可用性：sticky 題目欄、智慧波次標籤

**Non-Goals:**
- 重寫為第三方圖表庫（保留 SVG 手刻方案）
- 填補 `raw_score` 空資料（AI 計分非此次範疇）
- 改動 `project_questions.source_template_question_id` DB 欄位

## Decisions

### D1：SVG 改用固定 viewBox 座標系
**決策**：SVG 加 `viewBox="0 0 1000 200"`，`chartX` 返回 0–1000、`chartY` 返回 0–200 的絕對數值。
**原因**：SVG `points` 屬性只接受數值，百分比在 polyline/polygon 無效。viewBox 搭配 `preserveAspectRatio="none"` 使圖表自適應容器寬度，不需 JS resize listener。
**淘汰方案**：改用 `foreignObject` 內嵌 Canvas — 增加複雜度且無法 SSR。

### D2：後端 fallback key 寫入回傳欄位
**決策**：`AssessmentSeriesService::getComparison()` 中，`source_template_question_id` 回傳值改為 `$key`（即 `$pq->source_template_question_id ?? 'order:' . $pq->order`）。
**原因**：前端需要唯一、穩定的 key 來識別同一道題跨 project 的分數；`null` 無法作為 Map key 區分多筆。`order:N` 在單一 project 內唯一，可作為臨時代理鍵。
**限制**：若同一系列不同 project 的題目順序不同，`order:N` 會誤對齊；正式資料應有 `source_template_question_id`，此 fallback 僅為 seeded data 相容性。

### D3：必填驗證前後端各自執行
**決策**：前端 `unansweredRequired` computed 在開啟確認 Modal 時即時列出未答題；後端 `assertRequiredAnswered()` 在狀態機 transition 前再次驗證。
**原因**：前端驗證提供即時回饋（不需送 API）；後端驗證確保資料完整性（防繞過前端直接打 API）。

## Risks / Trade-offs

- **`order:N` fallback 不跨波次穩定**：若同系列兩個 project 使用不同模板，題目順序不同，`order:1` 在兩個 project 可能指不同題目 → 趨勢對齊錯誤。**Mitigation**：透過 Snapshot 機制確保正式 project 有 `source_template_question_id`；seeded data 的 fallback 行為已在 UI 備註「資料待補」。
- **`JSON_LENGTH` 在 answer_options 非 JSON 時報錯**：MySQL `JSON_LENGTH` 若欄位存的是純字串會 throw。**Mitigation**：`answer_options` 欄位型別為 `json`，Laravel migration 已確保只存 array，不會有純字串。
