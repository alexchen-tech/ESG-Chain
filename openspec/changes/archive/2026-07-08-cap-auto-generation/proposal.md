## Why

問卷計分完成後，ESG-Chain 已能自動推導供應商三軸風險評估（axis1 = ISO 26000 ESG 暴露、axis2 = ISO 20400 治理成熟度），但後續的矯正行動計畫（CAP）完全依賴人工建立。這造成兩個問題：

1. **時效性缺口**：高風險供應商完成問卷後，責任人員需人工察覺並手動開立 CAP，容易延誤。
2. **語意貧乏**：現有 CAP finding 只有 E/S/G 三個分類，無法精確指向是哪個 ISO 26000 主題（如 `iso26k.labor.conditions`）或哪個 ISO 20400 採購管理面向低分，稽核發現缺乏可追溯的框架依據。

ISO 26000 / ISO 20400 雙軌問卷設計的核心價值在於「識別缺口後驅動矯正」，但目前 CAP 模組與問卷計分結果的連結斷裂，導致問卷只是「評分存檔」，而非「驅動行動」的工具。

## What Changes

1. **CAPFinding 加入 ISO 框架欄位**：新增 `framework`、`topic_slug`、`source_score`、`threshold`，讓每條稽核發現可精確對應到 ISO 26000（`iso26k.*`）或 ISO 20400（`iso20400.*`）的 tag slug。

2. **CAP 加入 axis 歸因與自動產生標記**：新增 `triggered_by_axis`（axis1/axis2/axis3）與 `auto_generated`（boolean），區分自動觸發與人工建立的 CAP。

3. **CapAutoGenerationService**：在 `scoreCallback` 完成 `RiskAutoDerivationService::deriveFromSaq()` 後，依據 axis level 決定是否自動建立 CAP：
   - **extreme（≥80）**：自動建立 CAP，finding 依 ISO 框架逐主題列出低分缺失
   - **high（60–79）**：發送系統通知（Notification），不自動開 CAP
   - **low / very_low（< 60）**：不動作

4. **Finding 自動組成邏輯**：
   - **axis1（ISO 26000）**：從 `category_scores`（AI 已回傳 `iso26k.*` key）中篩出分數 < 60 的主題
   - **axis2（ISO 20400）**：從 SAQResponse JOIN question_tag_assignments 中聚合 `l1_domain='iso20400'` 題目的平均分，篩出 < 60 的 tag

## Capabilities

### New Capabilities

- `cap-auto-generation`: 問卷計分 extreme 時自動建立 CAP，包含 axis 歸因與 ISO 框架 finding 明細
- `cap-finding-iso-taxonomy`: CAPFinding 支援 framework/topic_slug/source_score/threshold 欄位，對映 ISO 26000/20400 tag 分類體系

### Modified Capabilities

- `cap-management`: CAP 模型新增 `triggered_by_axis`、`auto_generated` 欄位，source_type 確認支援 `risk_assessment`

## Impact

- **esgchain-api**：
  - Migration：`caps` 加欄位、`cap_findings` 加欄位
  - 新增 `CapAutoGenerationService`
  - 修改 `SAQController::scoreCallback()`：在 RiskAutoDerivation 後接入 auto-generation
  - 修改 `CAPController::store()`：驗證新欄位
- **esgchain-ai**：無需變動（category_scores 已回傳 iso26k.* key）
- **esgchain-web**：CAP finding 顯示欄位需補充 topic_slug / framework 的中文標籤對映
