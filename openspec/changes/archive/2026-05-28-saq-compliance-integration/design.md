## Context

三個現有結構是本次整合的基礎：

1. `SAQQuestion`：有 `tags`（QuestionTag 標籤體系）與 `sasb_topic_id`，但沒有直接的合規範疇欄位。題庫題目與範本題目共用同一表（`template_id = null` 為題庫題）。
2. `SupplierGroup`：只有 `name / description`，無任何物料或合規語意。成員供應商透過 `Supplier.group_id` FK 連結。
3. `SaqProject`（建立 Modal）：目前選擇範本後直接發給指定供應商，無群組選擇與合規推薦邏輯。

相關程式碼入口：
- `app/Models/SAQQuestion.php` — fillable, casts
- `app/Models/SupplierGroup.php` — 目前無業務方法
- `esgchain-web/src/views/settings/QuestionBankView.vue`
- `esgchain-web/src/views/questionnaires/SaqProjectsView.vue`

## Goals / Non-Goals

**Goals:**
- `SAQQuestion` 加 `compliance_domains: string[]` 欄位（UFLPA / EUDR / CMRT / SDS / CE）
- Admin 可在題庫管理頁為每道題打標 `compliance_domains`（多選 chip）
- `SupplierGroup` 提供 `inferredMaterialGroups()` 計算屬性（不存 DB）
- 問卷專案建立 Modal 加供應商群組選擇，推薦匹配題目（highlight + 可篩選）
- 題目庫篩選支援 `?compliance_domain=CMRT` 過濾

**Non-Goals:**
- 不強制要求問卷必須包含特定合規題目（推薦不等於強制）
- 不修改計分引擎（compliance_domains 只用於推薦，不影響 E/S/G 加權）
- 不實作「合規狀態雙向回寫問卷分數」（本次只做推薦方向）
- 不處理 SupplierGroup 跨多個 MaterialGroup 時的衝突排解

## Decisions

### D1：compliance_domains 打標位置 — 題庫層級（不在範本層）

**選擇**：`compliance_domains` 欄位加在 `SAQQuestion` 本身（`template_id = null` 的題庫題），複製進範本時一起帶入（現有 clone 邏輯已複製所有 fillable 欄位）。

**理由**：「這道題是否屬於 CMRT 合規範疇」是題目本身的語意屬性，跟放在哪個範本無關。在題庫打一次標，所有引用這道題的範本自動繼承。若在範本層打標，每次建範本都要重標，且難以跨範本查詢。

**排除**：範本層獨立打標 — 靈活性代價是維護成本翻倍，且查詢「哪些題屬於 UFLPA」需要跨範本聚合。

---

### D2：SupplierGroup → MaterialGroup 推導方式 — 計算屬性，不存 DB

**選擇**：`SupplierGroup::inferredMaterialGroups()` 方法動態計算：

```
SupplierGroup
  → suppliers（has many）
    → tradeGoods（has many, whereNotNull material_group_id）
      → materialGroup（belongs to）
  → unique MaterialGroups（by id）
```

回傳 `{ material_groups: [...], required_doc_types: [...], compliance_domains: [...] }`

**理由**：供應商的 TradeGoods 是 source of truth，推導結果是派生資料。存 DB 會產生同步問題（TradeGoods 變更時要更新 pivot）。群組規模有限（群組內供應商通常 < 100），即時計算效能可接受。可加 Laravel Cache（TTL 10 分鐘）優化重複呼叫。

**API 端點**：`GET /api/v1/supplier-groups/{id}/inferred-material-groups`
回傳格式：
```json
{
  "material_groups": [{ "id": "...", "name": "電子五金", "required_doc_types": ["CMRT"] }],
  "compliance_domains": ["CMRT"]
}
```

**排除**：新增 `supplier_group_material_groups` pivot table — 需要 UI 讓 admin 手動設定，且與 TradeGoods 資料可能不一致。

---

### D3：問卷建立 Modal 的推薦機制 — 軟推薦（highlight，非強制）

**選擇**：Modal 新增「供應商群組」下拉（選填），選擇群組後：
1. 呼叫推導 API 取得 `compliance_domains`（如 `["CMRT", "EUDR"]`）
2. 在「選擇範本」確認後，跳至題目預覽時，含匹配 `compliance_domains` 的題目加上 `⚠ 合規相關` badge
3. 新增篩選 toggle：「僅顯示合規相關題目」

**理由**：強制要求會破壞現有彈性（不同群組可能有非合規的問卷需求）。軟推薦讓採購商保持決策權，同時獲得上下文資訊。

**範本已有題目的情況**：若採購商選的是「已建好的範本」，推薦機制在範本題目列表上 highlight，不改變範本內容本身。

---

### D4：compliance_domains 的值域 — 固定 Enum，不走標籤體系

**選擇**：固定 5 個值：`UFLPA / EUDR / CMRT / SDS / CE`，後端 validation 為 `in:UFLPA,EUDR,CMRT,SDS,CE`，前端 chip 選擇器硬碼。

**理由**：這 5 個對應現有 `MaterialGroup.required_doc_types` 與 `SupplierComplianceDoc.doc_type` 的值域，是系統內部的合規框架識別碼，不需要動態擴充。用 QuestionTag 標籤體系反而太重，且 `compliance_domains` 的用途是機器可讀的過濾鍵，不是顯示用標籤。

## Risks / Trade-offs

| 風險 | 緩解 |
|------|------|
| 現有題庫題目 compliance_domains 全為空，推薦功能初期無效用 | 提供 `?compliance_domain=` 篩選 API 讓 admin 快速找到待標記題目；可搭配 SAQ seeder 補充示範資料 |
| 推導計算在供應商 TradeGoods 稀疏時回傳空結果 | UI 顯示「群組內尚無物料記錄，無法推薦合規題目」提示，不阻斷建立流程 |
| compliance_domains 複製進範本題目後，原題庫更新標籤不會同步 | 設計如此（快照語意）；題庫標籤更新屬於新版本範本的事，不做反向同步 |
| SupplierGroup 內供應商很多時推導效能 | Laravel Cache 10 分鐘，群組內供應商變動時 cache 失效 |

## Migration Plan

1. Migration：`saq_questions` 加 `compliance_domains` JSON nullable 欄位
2. 後端：`SAQQuestion` model、`SupplierGroup` model、新 API endpoint
3. 前端：題庫管理頁、問卷專案建立 Modal
4. 選擇性：為現有題庫題目補標 `compliance_domains`（可透過 Admin UI 逐步操作，無資料遷移強制要求）
