## Architecture

### 資料庫 Schema 變動

#### `sales_products`（原 `trade_goods` 改名 + 新增欄位）

```sql
-- 新增欄位（從 buyer_products 搬入）
applicable_regulations  JSON    NULL   -- ESG-Chain owned
inferred_regulations    JSON    NULL   -- ESG-Chain owned

-- 既有欄位保留（ERP owned）
item_code, name, hs_code, unit, customer_id, ...

-- 既有欄位保留（ESG-Chain owned）
is_cbam_applicable, cbam_category, embedded_emissions, ...
```

#### `product_bom_lines`（新增 `child_sales_product_id`）

```sql
-- 改名
buyer_product_id  → sales_product_id   UUID NOT NULL

-- 新增
child_sales_product_id  UUID NULL REFERENCES sales_products(id)

-- 既有保留
material_item_id  UUID NULL REFERENCES material_items(id)

-- 互斥約束（應用層強制，非 DB constraint）
-- child_sales_product_id IS NULL XOR material_item_id IS NULL
```

BomLine 型態語意：
- **型態 A（原料行）**：`material_item_id` 填入，`child_sales_product_id` 為 NULL
- **型態 B（子產品行）**：`child_sales_product_id` 填入，`material_item_id` 為 NULL

#### `pcf_snapshots`

```sql
buyer_product_id → sales_product_id   UUID NOT NULL
```

#### `shipment_lines`

```sql
-- 移除
buyer_product_id  (廢棄欄位)

-- 改名
trade_good_id → sales_product_id
```

#### 廢棄的表

```
buyer_products
buyer_product_trade_goods
buyer_product_suppliers
```

---

### 服務層設計

#### PCF 計算（PcfCalculationService）

```
calcForProduct(SalesProduct $product): array

BomLine 型態 B 的 emission_per_unit 來源：
  child_sales_product.latestPcfSnapshot()?.total_pcf
  若 child 無快照 → emission_per_unit = null，iso14067_ready = false
```

#### 循環參照保護（ProductBomLineService）

```
createBomLine(SalesProduct $parent, array $data): void
  if data['child_sales_product_id']:
    assertNoCycle($parent->id, $data['child_sales_product_id'])

assertNoCycle(string $parentId, string $childId): void
  if parentId === childId → abort(422)
  遞迴取 child 的所有 BomLine.child_sales_product_id
  若任一層出現 parentId → abort(422, '循環參照')
```

#### ExportLinkSyncService — 廢棄

移除整個 Service。PCF 結果直接寫入 `sales_products.embedded_emissions`，無需 push。

#### inferred_regulations 推算

移至 SalesProduct model method（原在 BuyerProduct），邏輯不變：
從 BomLine → materialGroup.required_doc_types → 推算法規清單。

---

### API 路由調整

| 舊路由 | 新路由 |
|--------|--------|
| `GET /buyer-products` | 廢棄 |
| `POST /buyer-products` | 廢棄 |
| `GET /buyer-products/{id}/bom-lines` | `GET /sales-products/{id}/bom-lines` |
| `POST /buyer-products/{id}/bom-lines` | `POST /sales-products/{id}/bom-lines` |
| `POST /products/{id}/pcf-recalc` | `POST /sales-products/{id}/pcf-recalc` |
| `GET /products/{id}/pcf-latest` | `GET /sales-products/{id}/pcf-latest` |
| `GET /trade-goods` | `GET /sales-products` |
| `POST /trade-goods` | `POST /sales-products` |
| `GET /trade-goods/{id}/suppliers` | `GET /sales-products/{id}/suppliers` |

---

### 前端調整

#### 廢棄
- `views/compliance/BuyerProductsView.vue`
- `api/modules/` 內 buyer-products 相關 endpoints
- Sidebar：移除「採購品合規」項目

#### 改名 / 擴充
- `views/trade-goods/TradeGoodsView.vue` → `views/sales-products/SalesProductsView.vue`
  - 新增 BOM 面板（原在 BuyerProductsView 的 BOM 展開行）
  - 新增 PCF 顯示與「重新計算」按鈕
  - 新增 `applicable_regulations` 與 `inferred_regulations` 顯示
  - BomLine 型態 B 新增「選擇子產品」選項

- Sidebar：「出口商品合規」→ 改為「銷售產品」，路由 `/sales-products`

---

### 欄位歸屬（對應 CLAUDE.md）

| 擁有者 | 欄位 |
|--------|------|
| ERP | `item_code`, `name`, `hs_code`, `unit`, `customer_id` |
| ESG-Chain | `applicable_regulations`, `inferred_regulations`, `is_cbam_applicable`, `cbam_category`, `embedded_emissions`, `emissions_source` |
