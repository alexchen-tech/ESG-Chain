## Context

現有 `SupplierComplianceStatusService` 已有 `getSupplierDashboard()` 和 `getProductDashboard()`。`SupplierGroup` Model 已有 `inferredMaterialGroups()` 方法透過 TradeGood 推斷覆蓋的物料群組，但未被任何 API 使用。矩陣計算的資料鏈：

```
SupplierGroup → Supplier → BomLineSupplier → ProductBomLine
  → material_group_id → MaterialGroup.required_doc_types
  + Supplier → SupplierComplianceDoc (status per doc_type)
```

## Goals / Non-Goals

**Goals:**
- 矩陣 API 回傳所有 MaterialGroup × required DocType 的供應商合規覆蓋統計
- 可依 `supplier_group_id` 篩選只看特定供應商群組
- Drill API 回傳特定格子的供應商明細（含文件狀態與到期日）
- 前端矩陣格子依覆蓋率上色：≥90% 綠、50-89% 黃、<50% 紅、不適用 灰
- 點擊格子右側展開 Drawer，顯示供應商清單

**Non-Goals:**
- 不修改現有供應商視角與產品視角的邏輯
- 不將 SupplierGroup.required_doc_types 納入合規計算（維持以 MaterialGroup 為準）
- 不在此 change 實作「發送提醒」功能（Drawer 底部按鈕留 placeholder）

## Decisions

**矩陣資料計算邏輯**

對每個 MaterialGroup：
1. 取得 `required_doc_types`（若為空則略過）
2. 查詢所有在 BomLineSupplier 中有該 MaterialGroup 料件的供應商（可依 supplier_group_id 過濾）
3. 對每個 required_doc_type：統計此供應商集合中有效（valid）/ 即將到期（expiring_soon）/ 已過期或缺件的數量

**API 回應格式**

```
GET /api/v1/compliance/matrix?supplier_group_id=xxx

{
  "doc_types": ["EUDR_DDS","UFLPA_DECLARATION","CMRT","SDS","CE_DOC"],
  "rows": [
    {
      "material_group_id": "uuid",
      "material_group_name": "棉紡原料",
      "cells": {
        "EUDR_DDS":           { "total": 12, "compliant": 8, "expiring": 2, "issues": 2, "pct": 67 },
        "UFLPA_DECLARATION":  { "total": 12, "compliant": 7, "expiring": 1, "issues": 4, "pct": 58 },
        "CMRT":               null
      }
    }
  ]
}

GET /api/v1/compliance/matrix/drill?material_group_id=X&doc_type=EUDR_DDS&supplier_group_id=xxx

{
  "material_group_name": "棉紡原料",
  "doc_type": "EUDR_DDS",
  "suppliers": [
    { "supplier_id":"...", "supplier_name":"台灣紡織 A", "supplier_group":"Tier 1", "status":"valid", "expires_at":"2025-12-01" },
    { "supplier_id":"...", "supplier_name":"孟加拉紡 D", "supplier_group":"Tier 2", "status":"missing", "expires_at":null }
  ]
}
```

**前端 Drawer 實作**

使用 `v-if` + 右側固定定位 div（`position:fixed; right:0; top:0; height:100vh`），不引入新元件庫。寬度 360px，點擊 overlay 或關閉鈕關閉。

**格子顏色 CSS class**

```
cell-green  → pct >= 90
cell-yellow → pct >= 50
cell-red    → pct < 50
cell-na     → null（不適用）
```

## Risks / Trade-offs

- **N+1 查詢風險**：`getMatrixData()` 需對每個 MaterialGroup 各做一次供應商文件查詢。以 eager loading + 批次 pluck 避免，預估供應商 <500 家時效能可接受（< 500ms）
- **矩陣欄位固定**：DocType 目前固定 5 種（EUDR/UFLPA/CMRT/SDS/CE），未來如新增類型需更新前端常數與後端查詢
