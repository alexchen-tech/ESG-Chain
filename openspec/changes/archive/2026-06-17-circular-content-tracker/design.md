## Context

`espr-dpp-readiness` Change 已定義 DPP 就緒度評分框架，包含多個維度（供應商合規、碳足跡、物質透明度等）。PCR 比率是其中缺失的一個維度，因為 `MaterialItem` 沒有 `net_weight` 和 `pcr_percentage` 欄位。

PCR 比率計算公式（ISO 14021 基礎）：

```
R_PCR = Σ(Wi × Pi) / W_Total

Wi     = BomLine i 的淨重（quantity × net_weight per unit）
Pi     = MaterialItem i 的 pcr_percentage（0–100）
W_Total = 所有 BomLine 淨重總和
```

此公式需要每條 BomLine 的 `quantity`（已有）與 MaterialItem 的 `net_weight`（待新增）及 `pcr_percentage`（待新增）。

## Goals / Non-Goals

**Goals:**
- `MaterialItem` 新增 `net_weight` 與 `pcr_percentage` 欄位（migration + model + API）
- `SupplierComplianceDoc` doc_type enum 加入 `GRS`（買方核對 GRS 認證文件）
- 新增 `PcrCalculationService::calcForProduct(BuyerProduct)` 實作加權平均公式
- DPP 就緒度評分新增 `pcr` 維度：`pcr_percentage` 已填寫且 GRS 文件存在 → 20 分
- 前端 MaterialItem 詳情頁補充 net_weight / pcr_percentage 輸入欄位

**Non-Goals:**
- LCA（生命週期評估）全流程
- 再生料來源追蹤（batch level）
- ESPR DPP 完整格式輸出（已由 espr-dpp-readiness 處理）

## Decisions

### D1：net_weight 與 pcr_percentage nullable，不強制填寫

ERP 同步時不一定有重量資料。`net_weight = null` 時，該 BomLine 在 PCR 計算中排除（分母也不計入），並在結果中標記 `incomplete_lines` 數量。

### D2：PCR 計算結果不另存表，存入 PcfSnapshot.lines

每條 BomLine 的 PCR 貢獻值（pcr_contribution）加入現有 `PcfSnapshot.lines JSON` 結構，作為新欄位。產品層級 PCR 比率存入 `PcfSnapshot` 的新欄位 `pcr_ratio`。避免新增獨立的快照表。

### D3：GRS 文件類型加入現有 enum，不另立模型

`SupplierComplianceDoc.doc_type` 已有 EUDR_DDS、UFLPA、CMRT、SDS、CE 等值。`GRS` 直接加入 enum，不改變資料模型結構，遷移簡單。

### D4：DPP 就緒度 PCR 維度得分邏輯

- primary supplier 有 GRS 文件（status=valid）且 `MaterialItem.pcr_percentage > 0` → 該 BomLine 計入 PCR 就緒
- 產品 PCR 就緒比率（有 PCR 資料的 BomLine 數 / 總 BomLine 數）≥ 80% → pcr 維度滿分

## Risks / Trade-offs

- **net_weight 資料蒐集難度**：許多供應商不提供單位淨重；nullable 設計避免強制，但 PCR 計算可能不完整
- **BomLine quantity 與 net_weight 單位一致性**：需在 UI 標示 net_weight 的單位（kg/unit），與 BomLine.unit 區分
