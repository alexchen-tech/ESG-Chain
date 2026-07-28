## Context

稽核發現 EU 紡織品 DPP 六大強制類別中，`SalesProduct`/`MaterialItem`/`TradeGoodSupplier`/`SupplierFacility`/`RawMaterialOrigin` 現有欄位只覆蓋「識別碼、供應商層級國別、原料 GPS 產地、既有的再生料/可回收性快照」，缺口集中在：有害物質彙總、微纖維、包材、製程級地點、運輸方式距離。`market_compliance_rules` 的規則原語只有 `doc_type`（文件存在性），無法表達「欄位是否具備/是否達門檻」。`BatchExportReviewService` 已有 `checkEudrOrigins()`/`checkUflpaOrigins()` 這類「明確寫死的檢查方法」慣例，本次沿用同一寫法，不引入規則引擎。

## Goals / Non-Goals

**Goals:**
- 補齊六大類別中完全缺漏的三項（微纖維、包材、製程級地點+運輸）之最小可用資料模型
- 把已存在但未接入審查的兩項（有害物質衍生判定、再生料/可回收性）實際掛進 `BatchExportReviewService` 與 `BatchPassportService`
- `checkDppFields()` 更名前先確認其呼叫端（前端/測試）不依賴舊行為，重寫為真正對應 DPP 六大類別的檢查

**Non-Goals:**
- 不建立通用「欄位門檻規則引擎」，不擴充 `market_compliance_rules` 的 `doc_type` 之外規則型別
- 不做微纖維釋放風險的實驗室數據自動導入（僅提供人工填報欄位）
- 不做外部 DPP API 認證/對外開放存取
- 不重新設計 `SupplierFacility`/`TradeGoodSupplier` 的既有關聯結構，只做欄位擴充

## Decisions

**1. 有害物質揭露彙總 — 即時計算，不落地新欄位**
`SalesProduct` 不新增 `has_hazardous_substance` 實體欄位；改在 `TradeGoodService`（或新增小型 `HazardDisclosureService`）新增一個方法，即時查詢該產品所有 BOM 物料的 `ChemicalComplianceAlert`（`status != 'resolved'` 視為未解除的風險），回傳布林 + 清單。理由：這個判定會隨 `chemical_compliance_alerts` 的稽核狀態變動而變，落地成靜態欄位會產生跟既有 `PcfSnapshot`/`ProductCircularitySnapshot` 不同調的「二次真相」；查詢成本低（單一 product 的 alert 數量小），比照 `MarketComplianceChecker` 現有即時查詢模式。

**2. 微纖維釋放風險 — MaterialItem 新增 nullable enum**
新增 `material_items.microfiber_release_risk` enum(`low`,`medium`,`high`,`not_rated`)，比照既有 `recyclability_rating` 的 enum 寫法與預設值慣例（`not_rated`）。理由：規範文件明確指出微纖維風險屬於「成分」層級揭露，跟 `pcr_percentage`/`recyclability_rating` 同屬 MaterialItem 既有欄位群，不另開資料表。

**3. 包材資訊 — 新增獨立 `product_packagings` 表，一對一掛 SalesProduct**
新增資料表 `product_packagings`（`sales_product_id` unique FK、`recycled_content_ratio` float、`recyclable` boolean、`reusable` boolean、`material_description` string、`notes` text）。理由：包材是全新概念、無既有欄位可擴充；用獨立表而非塞進 `sales_products` 是因為包材屬於選填的補充資訊（多數既有 18 筆產品資料不會馬上有值），獨立表可用 `LEFT JOIN`/`hasOne` 表達「尚未填寫」而不需要在主表塞一堆 nullable 欄位。一對一（非一對多）：目前規範只要求單一包材揭露，不支援多層包裝分別揭露（非目標）。

**4. 供應鏈製程級地點 — 擴充既有兩張表，不新增第三張**
- `supplier_facilities.facility_type` enum 擴充加入紡織關鍵製程值：`weaving`（織布）、`knitting`（針織）、`dyeing`（染整）、`printing`（印花）、`wet_processing`（濕製程）、`garment_assembly`（成衣製造），原有 `manufacturing`/`warehouse`/`office`/`other` 保留（`manufacturing` 作為未分類製造的預設值，向後相容既有資料）。
- `trade_good_suppliers` 新增 `supplier_facility_id`（nullable FK → `supplier_facilities.id`），讓「這個產品的這個供應商關聯」可以進一步指到「哪個廠區（=哪個製程+哪個地點）」。
理由：不新增第三張橋接表，是因為 `trade_good_suppliers` 本來就是「產品 × 供應商」的關聯點，加一個可選的 facility 指標即可表達「這個產品在這個供應商的染整廠生產」，符合現有一個供應商可能有多個廠區（`supplier_facilities` 本來就是一對多）的既有設計，不需要新建模型。同一產品若有多個製程分屬不同供應商/廠區，本來就會有多筆 `trade_good_suppliers` 列，天然支援「同產品不同製程不同地點」。

**5. 運輸方式與距離 — 掛在 `raw_material_origins`，不新增獨立運輸表**
`raw_material_origins` 新增 `transport_mode` enum(`sea`,`air`,`road`,`rail`,`multimodal`,`unknown`) 與 `transport_distance_km` float，皆 nullable。理由：`raw_material_origins` 已經是「這批原料從哪裡來」的紀錄粒度，運輸資訊本質上跟「這批原料的產地」是同一個實體，不需要另建表格造成 join 複雜度；EU 規範文件裡運輸資訊本來就是補充性質（藍色/非強制），用簡單欄位即可。

**6. `checkDppFields()` 重寫，不改方法名**
維持 `checkDppFields()` 這個既有方法名（避免影響呼叫端 `review()` 組裝 `findings[]` 的既有結構與前端解析邏輯），只重寫方法內部邏輯：改為逐項檢查「有害物質揭露是否已判定（無論結果，只要不是完全沒資料）」、「微纖維風險是否已填報」、「包材資訊是否存在」、「BOM 物料是否至少有一筆製程級地點（`supplier_facility_id` 非空）」、「再生料比例/可回收性快照是否 `data_ready`」、「原料溯源是否有運輸資訊」，每項獨立成一個 finding（沿用本次工作階段稍早 `structured-export-review-findings` 建立的逐項 finding 結構，不走回頭路變回單一字串）。

## Risks / Trade-offs

- [風險] 即時查詢 `ChemicalComplianceAlert` 判定有害物質，若某產品掛的供應商/BOM 很多，`review()` 呼叫時查詢次數增加 → 緩解：比照 `MarketComplianceChecker` 既有作法，用 `whereIn` 一次查完該產品所有相關 BOM 物料的 alert，不逐筆查詢
- [風險] `supplier_facilities.facility_type` enum 擴充後，既有依賴這個欄位篩選 `manufacturing` 的查詢（若有）語意不變，但新製程值不會被舊查詢涵蓋 → 緩解：檢查現有程式碼是否有 `where('facility_type', 'manufacturing')` 這類篩選，若有則評估是否要放寬成 `whereIn` 涵蓋新製程值（實作階段查證）
- [風險] `trade_good_suppliers.supplier_facility_id` 為 nullable，既有 46 筆歷史資料不會自動回填 → 屬於預期行為，比照專案既有「新增欄位不強制回填歷史資料」慣例，`checkDppFields()` 的對應 finding 在缺資料時回報 `missing` 而非報錯
- [取捨] 包材、微纖維、運輸皆為選填欄位，短期內大部分產品仍會是「未填寫」狀態，`checkDppFields()` 的檢查結果初期會大量顯示缺失 → 這是設計目的（讓使用者知道要補），不是缺陷

## Migration Plan

1. 新增 4 個 migration：`material_items` 加 `microfiber_release_risk`；新建 `product_packagings`；`supplier_facilities.facility_type` enum 擴充 + `trade_good_suppliers` 加 `supplier_facility_id`；`raw_material_origins` 加 `transport_mode`/`transport_distance_km`
2. 更新對應 Model（`MaterialItem`、新增 `ProductPackaging`、`SupplierFacility`、`TradeGoodSupplier`、`RawMaterialOrigin`）的 `$fillable`/`$casts`/關聯
3. 新增 `HazardDisclosureService`（或掛在 `TradeGoodService` 內的方法）
4. 重寫 `BatchExportReviewService::checkDppFields()`，新增對應私有方法
5. `BatchPassportService` 新增輸出區塊（包材、製程地點、運輸、有害物質、微纖維）
6. 前端：`MaterialItemController`/`SalesProductController` 相關表單新增輸入欄位；`SupplierFacility` 表單新增製程選項；`ProductionBatchDetailView.vue` 顯示新 finding 類型
7. Docker 部署：`esgchain-api` + `esgchain-queue-worker` 同步（沿用專案既有雙容器同步紀律）

## Open Questions

- 包材資訊是否需要供應商填報入口（Portal），或先僅開放內部人員（buyer/comply）填寫？本次先做內部填寫，Portal 開放留待下次迭代（需求方確認後補）
