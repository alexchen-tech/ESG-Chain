## Tasks

### A. 來源供應商面板（後端）
- [x] **A1** Migration：`add_recycled_fields_to_material_items`（pir_percentage、bio_based_percentage、recyclability_rating）
- [x] **A2** MaterialItem model：$fillable 加三個新欄位，$casts 補上 float/string
- [x] **A3** MaterialItemController::update() validate 加三個新欄位
- [x] **A4** MaterialItemController 新增 `bomSuppliers()` 方法
- [x] **A5** api.php 新增路由 `GET material-items/{materialItem}/bom-suppliers`

### B. 前端實作
- [x] **B1** compliance.ts MaterialItem interface 補三個新欄位 + BomSupplier interface
- [x] **B2** compliance.ts materialItemApi 新增 `bomSuppliers(id)` 方法
- [x] **B3** MaterialItemsView：data() 加 `bomSuppliersMap`, `bomSuppliersLoading`, `chemicalAlerts`, `chemicalAlertsLoading`, `recycledExpandedId`, `recycledForm`
- [x] **B4** MaterialItemsView：row 新增 PCR badge、新增「供應商」「回收」兩個展開按鈕
- [x] **B5** MaterialItemsView：`toggleBomSuppliers()` 方法 + 來源供應商展開列 template
- [x] **B6** MaterialItemsView：化學展開列底部加合規掃描結果（fetch chemicalAlerts + 顯示）
- [x] **B7** MaterialItemsView：新增「可回收材料」展開列 + 儲存邏輯
- [x] **B8** MaterialItemsView：編輯 modal 加三個新欄位
