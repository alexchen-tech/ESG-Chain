## Context

使用者確認「製程導向盡職調查」要串接既有 SAQ 六維風險評分機制，而非新建一套平行的文件類型檢查（避免跟 SAQ/風險矩陣/CAP 既有機制重複建置）。研究確認：
- 六維評分 `dim_e1`（環境管理）/`dim_e3`（社會責任）已是最接近「環境」「勞動」的既有維度，分數存於 `RiskAssessment` model（`Supplier::latestRiskAssessment()` 可取得最新一筆）
- 分數轉風險等級用既有 `RiskAssessment::dimToLevel(?float $score): string`
- 問卷本身有更細的合規領域標籤（`esg.e.water`/`esg.s.child_labor` 等），但目前沒有現成的「依領域篩選題目作答完整度」查詢方法，這次先用構面分數（有/無、等級），不做到細顆粒領域比對

## Goals / Non-Goals

**Goals：**
- 批次選定某製程供應商後，可以立即看到「這個供應商在對應風險構面（環境/社會責任）目前的評分狀態」，不需要另外跳去供應商頁面查
- 沒有評分紀錄時明確標示「尚未完成評分」，不是顯示錯誤或空白
- 完全重用既有 `RiskAssessment`/`dimToLevel()`，不新增評分邏輯或資料表

**Non-Goals：**
- 不新增文件類型（ZDHC/SA8000 等）——維持稽核所有平行方案討論後選定的方向：串接既有 SAQ，不建平行文件檢查
- 不做細顆粒問卷領域比對（如「這題有沒有回答廢水排放」）——現有 `question_tags` 雖有細顆粒標籤，但沒有現成查詢方法，且六維構面分數已是聚合後的結果，足以支撐「這個供應商環境/社會責任風險高不高」的呈現需求；細顆粒比對留待有實際需求時再做
- 不把這個資訊納入 `gateCheck()`/出口審查的 blocked 判斷——這次是唯讀資訊呈現，是否要讓「製程供應商風險過高」阻擋出貨，屬於業務規則的重大決定，需要另外跟使用者確認，這次不擅自納入

## Decisions

**1. 製程類型 → 六維構面對應，用簡單常數，不做成資料庫可配置規則**

```php
const PROCESS_RISK_DIM_MAP = [
    'dyeing'         => 'dim_e1', // 染整：環境管理
    'wet_processing' => 'dim_e1', // 濕製程：環境管理
    'printing'       => 'dim_e1', // 印花：環境管理
    'garment_assembly' => 'dim_e3', // 成衣縫製：社會責任
];
// weaving/knitting/manufacturing/warehouse/office/other 不在此對應表內，不觸發檢查
```
不做成 `MarketComplianceRule` 那樣資料庫可配置的規則，理由：這只是「製程類型→既有六維構面」的固定語意對應（染整本來就是環境議題、成衣縫製本來就是勞動議題），不是會頻繁變動的業務規則，用常數更直接、可讀性更高；如果未來要讓使用者自訂對應關係，再抽成設定頁不遲。

**2. 新增獨立 `BatchProcessDueDiligenceService`，不塞進 `BatchExportReviewService`**

這個檢查不屬於「市場審查」（不因市場不同而有不同結果，是供應商本身的風險狀態），塞進 `BatchExportReviewService::review()` 會讓語意混淆（審查結果變成綁 market，但這個資訊跟 market 無關）。獨立成一個新 Service，方法簽名 `build(ProductionBatch $batch): array`，邏輯：
1. 呼叫 `ProductUpstreamResolver::batchProcessTypes($batch, $product)`（`batch-process-facility-selection` 已建立）取得批次製程清單與已選定供應商
2. 對每個 `confirmed:true` 且製程類型在 `PROCESS_RISK_DIM_MAP` 內的項目，查該供應商 `latestRiskAssessment`，取對應 `dim_eN` 分數，呼叫 `dimToLevel()` 轉等級
3. 沒有對應構面的製程類型（如一般製造）不列入結果；`confirmed:false`（尚未選定）的製程類型列入但標記 `status: 'pending_selection'`
4. 輸出範例：
```php
[
    ['process_type' => 'dyeing', 'dimension' => 'dim_e1', 'dimension_label' => '環境管理',
     'status' => 'assessed', 'risk_level' => 'medium', 'score' => 62.5,
     'supplier_id' => '...', 'supplier_name' => '...'],
    ['process_type' => 'garment_assembly', 'dimension' => 'dim_e3', 'dimension_label' => '社會責任',
     'status' => 'not_assessed', 'risk_level' => null, 'score' => null,
     'supplier_id' => '...', 'supplier_name' => '...'],
]
```
`status` 三種值：`assessed`（有分數）/`not_assessed`（供應商存在但無 RiskAssessment 或該構面分數為 null）/`pending_selection`（製程尚未選定供應商，無從評估）。

**3. API 獨立曝露，同時併入批次護照**

比照 `gate-check` 的模式：獨立唯讀端點 `GET production-batches/{batchId}/process-due-diligence`，同時在 `BatchPassportService::build()` 加一個 `process_due_diligence` 區塊（呼叫同一個 Service），維持「passport 是完整彙總快照」的既有設計原則。

**4. 前端呈現：徽章 + 連結，不做完整風險矩陣搬過來**

在既有製程卡片（`batch-process-facility-selection` 剛做的）已選定供應商旁邊，加一個小徽章顯示風險等級（比照現有 badge 樣式，顏色沿用 `components.css` 既有的 risk-level 相關 class，不新增顏色語彙），點擊/旁邊連結導向該供應商詳情頁（`/compliance/suppliers/{id}`，比照既有出口審查頁「前往補件」連結模式）查看完整 SAQ 與風險矩陣資料，不在批次詳情頁重複呈現完整風險矩陣。

## Risks / Trade-offs

- [取捨] 用構面分數（`dim_e1`/`dim_e3`）而非細顆粒問卷領域比對，代表「染整供應商環境分數不錯，但廢水排放那幾題剛好沒填」這種細節看不出來，只能看到聚合後的構面分數。這次先求「有個大致判斷依據」，細節缺口留給使用者自行點進供應商頁查看
- [風險] `dim_e3`（社會責任）是比「勞動」更廣的構面（可能還包含社群關係、多元共融等非勞動子項），用它代表「成衣縫製的勞動盡職調查」是近似對應，不是完全精確；如果未來需要更精確的「勞動專屬」分數，需要問卷評分機制本身先拆分出獨立的勞動子構面，屬於更大的 SAQ 評分模型調整，不在這次範圍

## Migration Plan

1. `BatchProcessDueDiligenceService` 新增（含 `PROCESS_RISK_DIM_MAP` 常數）
2. Controller action + 路由：`GET production-batches/{batchId}/process-due-diligence`
3. `BatchPassportService::build()` 新增 `process_due_diligence` 區塊
4. 前端：製程卡片加風險徽章 + 供應商連結
5. 部署驗證：挑一個有染整/成衣縫製製程選定供應商、且該供應商有 `RiskAssessment` 紀錄的批次，確認徽章正確顯示風險等級；挑一個供應商完全沒有評分紀錄的情境，確認顯示「尚未完成評分」而非錯誤

## Open Questions

（無。以下兩點已與使用者確認：(1) 風險等級過高**不**納入出貨關卡（`gateCheck`）判斷，維持純資訊呈現；(2) `question_tags` L3 子領域標籤與 `dim_eN` 六維構面之間沒有資料庫層級的強制對應關係——`dim_eN` 是外部 esgchain-ai 服務算好後直接寫回 `risk_assessments`/`saqs` 表，完全跳過 tag 系統，若自建 tag→dim 對照表等於發明一套沒有官方保證的映射，故這次維持只用 `dim_e1`/`dim_e3` 構面聚合分數，L3 子領域細節列為 Non-Goal，待 esgchain-ai 端未來真正定義 `scoring_engine_key` 對應關係後再接）
