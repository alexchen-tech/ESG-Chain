## Context

現有合規看板有三個視角（供應商、產品、矩陣）。ESPR/DPP 是以「產品」為核心的 EU 法規，要求每件產品建立 Digital Product Passport（DPP），揭露材料組成、材料來源合規聲明、碳足跡等資訊。DPP 就緒度可由既有資料計算，無需新增 DB 欄位。

現有資料鏈：
```
BuyerProduct
  ├── applicable_regulations[]      → 判斷是否須 ESPR
  ├── BomLine[]
  │     ├── material_name / hs_code → 材料組成
  │     ├── MaterialGroup → required_doc_types → 需要哪些供應商文件
  │     └── BomLineSupplier → Supplier → SupplierComplianceDoc[]
  └── (未來) dpp_* 欄位             → 本次不新增
```

DPP 就緒度的三個區塊：
1. **材料清單完整性**：BomLine 都有 material_group_id + hs_code
2. **供應商合規覆蓋率**：primary 供應商的 required_doc_types 都有對應 valid/expiring_soon 文件
3. **產品基本資訊**：BuyerProduct.applicable_regulations 有包含 ESPR

## Goals / Non-Goals

**Goals:**
- 新增 `GET /api/v1/compliance/dpp-readiness`，回傳所有產品的 DPP 就緒度評估
- 前端第四個 Tab「ESPR/DPP」顯示產品列表，含就緒分數與狀態
- 點擊產品展開右側 Drawer，顯示三個區塊的詳細完整性
- 整體設計與矩陣視角的 Drawer 風格一致

**Non-Goals:**
- 不新增 DPP 專用 DB 欄位（如 `dpp_submitted_at`、`dpp_id`）
- 不對接外部 DPP 登記系統
- 不計算碳足跡（PCF 模組另外處理）
- 不修改 BuyerProduct 的建立/編輯流程

## Decisions

**DPP 就緒度計算邏輯**

對每個 BuyerProduct：

| 區塊 | 資料來源 | 判斷方式 |
|------|---------|---------|
| 材料清單完整性 | BomLine | 有 material_group_id 且 bom_line_type = 'material' 的比率 |
| 供應商合規覆蓋率 | BomLineSupplier + SupplierComplianceDoc | primary 供應商已提交 required_doc_types 的比率（valid 或 expiring_soon 計為合規） |
| 法規標記 | BuyerProduct.applicable_regulations | 是否包含 'ESPR' |

整體就緒狀態：
- `ready`：三區塊全部滿足（法規有標記、材料 100%、供應商 ≥ 80%）
- `partial`：任一區塊部分完成
- `not_started`：BomLine 為空或無 primary 供應商

**API 回應格式**

```
GET /api/v1/compliance/dpp-readiness

{
  "products": [
    {
      "product_id": "uuid",
      "product_name": "Spring Cotton T-Shirt",
      "has_espr_regulation": true,
      "readiness_status": "partial",
      "material_completeness_pct": 75,
      "supplier_compliance_pct": 60,
      "bom_line_count": 4,
      "issues": [
        "2 BomLine 缺少物料群組",
        "供應商 台灣紡織A 缺少 EUDR_DDS 文件"
      ]
    }
  ]
}
```

**Drawer 詳細格式**

```
GET /api/v1/compliance/dpp-readiness/{productId}

{
  "product_id": "...",
  "product_name": "...",
  "has_espr_regulation": true,
  "sections": {
    "material_list": {
      "status": "partial",
      "total": 4,
      "complete": 3,
      "items": [ { "material_name": "...", "hs_code": "...", "has_group": true/false } ]
    },
    "supplier_compliance": {
      "status": "issues",
      "total_required": 8,
      "compliant": 5,
      "items": [ { "supplier_name": "...", "doc_type": "EUDR_DDS", "status": "missing" } ]
    },
    "regulations": {
      "has_espr": true,
      "all_regulations": ["EUDR", "ESPR"]
    }
  }
}
```

**前端 Drawer 共用設計**

沿用矩陣視角的 `.drawer` / `.drawer-overlay` scoped 樣式，DPP Drawer 使用相同定位與動畫。Drawer 內分三個 section card 呈現三個區塊，每個 section 有標題、進度條（completeness %）、條目清單。

## Risks / Trade-offs

- **ESPR 判斷只依 applicable_regulations 欄位**：若採購商未標記，就緒度會低估。設計上應提示使用者手動標記，不自動推斷。
- **供應商合規覆蓋率計算與矩陣視角邏輯相似，未抽取共用**：兩段邏輯略有不同（DPP 視角看所有 doc_types，不限特定 doc type），短期接受重複，未來可提取 `SupplierComplianceCalculator` service class。
