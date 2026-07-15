## Context

ESG·Chain 現有 `trade_goods` 表記錄供應商的貿易商品（HS Code + CBAM 判斷），但對於 UFLPA / EUDR / CMRT / SDS / CE 等物料合規需求毫無支援。隨著 EUDR 2025 對大企業生效，採購商需要一個能夠管理合規文件、追蹤效期、提供清關佐證的系統能力。

此外，平台缺乏採購商自身「產品清單」的概念，使合規狀態只停留在供應商層級，無法回答「我的哪些產品面臨 EUDR 風險」。本設計引入輕量的 `buyer_products` 層，以「產品 → 供應商 + 物料群組」的中等粒度關聯，建立產品視角的合規追蹤，同時避免完整 BOM 的維護複雜度。

## Goals / Non-Goals

**Goals:**
- 建立 `buyer_products` 與 `buyer_product_suppliers` 主檔，支援手動建立與 CSV 匯入
- 建立 `material_groups` 主檔，定義物料類別與對應的合規文件需求
- 建立 `supplier_compliance_docs` 文件管理，含效期、狀態、審核
- `trade_goods` 綁定物料群組，自動推導所需文件清單
- 採購商看板同時支援產品視角與供應商視角的合規健康度
- 供應商 Portal 可上傳合規文件
- 側邊欄新增「物料合規」群組

**Non-Goals:**
- 完整 BOM（产品↔trade_good SKU 層級關聯）
- 文件 AI 內容驗證（OCR、CAS 比對）—— Phase 2
- GPS 廠區多邊形座標（子項目 B）
- N-tier 供應鏈溯源（子項目 C）
- ERP RFQ/PO 阻斷整合
- 文件實際儲存後端（初期存路徑字串，不建 object storage）

## Decisions

### D0：採購商產品清單的關聯粒度選擇

**決定**：`buyer_product_suppliers` 採用「product → supplier + material_group」中等粒度，不直達 trade_good SKU 層級。

**理由**：完整 BOM 要求採購商維護每個 SKU 對應的原料清單，導入摩擦極高。以供應商+物料群組為單位的關聯，已足夠判斷「哪些法規適用」以及「該供應商的合規文件是否齊備」，符合 V3.0 護欄「以物料群組宣告，不直上單一 SKU」的原則。

**資料模型**：
```
buyer_products
├── id, name, product_code (nullable), description, applicable_regulations (json)
└── timestamps

buyer_product_suppliers（樞紐表）
├── id, buyer_product_id, supplier_id, material_group_id (nullable)
└── timestamps
```

**替代方案**：product → trade_good（SKU 層級）→ 維護成本過高，排除。

### D0b：CSV 匯入格式

**決定**：CSV 欄位為 `name, product_code, description, supplier_tax_id_or_name, material_group_name`。系統依 `supplier_tax_id_or_name` 比對現有供應商，找不到則跳過該行並在匯入結果中列出警告，不阻斷整批匯入。

**理由**：採購商的產品資料通常來自 ERP 匯出，需支援批量建立關聯；寬容模式（警告不阻斷）減少匯入失敗率。

### D1：物料群組為「系統預設 + 可擴充」模式

**決定**：預載 5 個標準物料群組（棉紡/木質/電子五金/化工塑料/機電終端），各群組預設需要哪些文件類型，管理員可新增自訂群組。

**理由**：客戶不需要從零設定即可開始使用；90% 的 UFLPA/EUDR 案例落在這 5 個群組內。

**替代方案**：完全客製 → 導入摩擦太高，客戶無法快速 demo。

### D2：文件類型為枚舉字串，不獨立建表

**決定**：`doc_type` 用 `varchar` 存儲固定集合（`UFLPA_DECLARATION`, `EUDR_DDS`, `CMRT`, `SDS`, `CE_DOC`, `ORIGIN_CERT`, `OTHER`），不建立獨立 `doc_types` 表。

**理由**：法規類型短期內不會劇烈變動；避免不必要的 JOIN；初期 7 種類型已足夠覆蓋主流需求。

### D3：文件上傳路徑存字串，不整合 object storage

**決定**：`file_path` 欄位初期存相對路徑或 URL 字串，實際檔案由 Laravel storage（本地 disk）處理，不接 S3/MinIO。

**理由**：object storage 整合是基礎設施決策，不應卡住業務功能開發；未來遷移只需改 storage driver，不影響資料模型。

### D4：合規狀態計算在讀取時動態計算，不儲存

**決定**：`status`（valid/expiring_soon/expired/pending）由 `expires_at` 與當前時間動態計算（`expiring_soon` = 30 天內到期），不持久化狀態欄位。

**理由**：效期是客觀事實，持久化狀態需要排程更新，增加複雜度；動態計算在查詢量不大時效能足夠。

**例外**：`verified_at` 審核狀態儲存（null = 未審核，有值 = 已審核），因為這是人工行為。

### D5：供應商 Portal 文件上傳限制

**決定**：供應商只能上傳自己所屬供應商的文件；採購商（admin/buyer/sustain/comply）可以審核（設定 verified_at）；只有 admin 可修改物料群組設定。

## Risks / Trade-offs

- **效期計算時區**：`expires_at` 用 UTC 儲存，前端顯示時需轉換當地時區。→ 統一使用 UTC，前端負責顯示轉換。
- **文件真實性**：初期僅儲存文件，不驗證內容。→ 明確標示「未驗證/已審核」狀態，讓採購商知道需人工核實。
- **物料群組與 HS Code 對應規則**：自動推導邏輯基於 HS Code prefix 字串比對，邊界案例可能誤判。→ 提供手動覆蓋（直接在 trade_good 上指定 material_group_id）。

## Migration Plan

1. 執行新 migrations（material_groups, supplier_compliance_docs, alter trade_goods）
2. Seeder 填入 5 個預設物料群組與 HS Code 前綴規則
3. 現有 `trade_goods` 記錄的 `material_group_id` 初始為 null（不強制），等使用者手動綁定或觸發自動推導

**Rollback**：`material_group_id` 為 nullable，刪除新 migrations 即可回滾，不影響既有資料。

## Open Questions

- 合規文件上傳大小限制？（建議 10MB）
- `expiring_soon` 閾值：30 天是否合適，或應設定為可配置？
- EUDR 所需的 GPS 多邊形資料是否應在此版本預留欄位，或等子項目 B？（建議此版本不加，保持邊界清晰）
