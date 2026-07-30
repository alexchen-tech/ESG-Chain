## Context

`PcfSnapshot.lines`（JSON）已經是逐物料的排放小計（`emission_per_unit × BOM配方量 = subtotal`），但這是「每單位產品」的快照，不含實際生產量；`ProductionBatch.quantity` 有批次數量但不含物料明細；兩者需要在報表層合併才能算出「這個物料在某段期間實際貢獻了多少範疇三類別一排放量」。

## Goals / Non-Goals

**Goals：**
- 以物料為主視角彙總範疇三類別一排放量，支援依申報期間（如季度/年度）篩選
- 呈現資料品質分布（`data_quality`: primary/secondary/estimated/missing 佔比），讓使用者知道這個彙總數字的可信度
- 可從物料下鑽看到用到這個物料的產品/批次/供應商

**Non-Goals：**
- 不做範疇三其他類別（商務差旅、資本財等）
- 不追蹤實際物料耗用量（維持 BOM 配方量 × 批次數量的估算方式，若未來 ERP 有實耗數據再接入）
- 不做外部第三方查證流程

## Decisions

**1. 彙總邏輯：產品最新 PCF 快照 × 期間內批次數量**

對每個在申報期間內有生產批次的產品，取其**最新一筆** `PcfSnapshot`（PCF 快照是 append-only，用最新版本代表當前最佳估算），把 `lines` 裡每個物料的 `subtotal`（每單位排放）乘上該產品在期間內的批次數量加總，逐物料累加。型態 B（子產品 BOM，無 `material_item_id`）的 line 略過不列入物料彙總（不在這次範圍內處理巢狀 BOM 遞迴）。

**2. 報表為唯讀查詢彙總，不落地存表**

即時查詢計算，不建彙總表快照——資料量級（物料項目數 × 期間批次數）在可接受範圍內即時查詢，且能保證數字永遠反映最新 PCF 快照，不會有「彙總表沒同步」的問題。若未來效能有問題再考慮加彙總快取層。

**3. 明確標示估算性質**

畫面與 API 回應都要清楚標註「此為 ESG-Chain 依 BOM 配方量與批次數量估算，非實際物料耗用量，亦非經第三方查證數字」，避免使用者誤用於正式法規揭露。

## Migration Plan

1. 新增 Service：依期間彙總 `PcfSnapshot.lines` × `ProductionBatch.quantity`，依 `material_item_id`/`material_group_id` 分組
2. 新增 API 端點：彙總清單（依物料群組/物料項目）+ 下鑽明細（產品/批次/供應商）
3. 前端新增報表頁面
4. 部署後以真實資料驗證：挑一個物料，手動核算其在某期間的批次數量 × 該物料在對應產品 PCF 快照的 subtotal，確認跟報表數字一致

## Open Questions

（無）
