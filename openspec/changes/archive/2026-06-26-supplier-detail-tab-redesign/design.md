## Context

`SupplierDetailView.vue` 目前有 6 個 Tab：基本資訊、產業分類、管理歸屬、永續績效、合規管理、設施管理。Tab 系統只控制頁面上半部；供應材料清單、Disclosure Profile、合規文件、生產設施等 section 無論 Tab 為何均 always-visible，造成 Tab 切換幾乎無效。Disclosure Profile 的 DOM 位置夾在「供應材料清單」與「合規文件」兩個 always-visible section 之間，`v-show` 邏輯失效。

主要使用者工作情境：
- **sustain / analyst**：打開明細 → 先看風險評估，再看問卷歷程
- **comply**：打開明細 → 先看風險評估，再看哪些文件缺漏（需要知道「為什麼需要這份文件」= BOM 來源）
- **buyer**：打開明細 → 看風險評估 + 設施資訊

## Goals / Non-Goals

**Goals:**
- 4 個工作情境 Tab，每個 Tab 完整封裝一種工作流
- 風險評估（E/S/G/GP）移至概況 Tab 首位，為所有角色的預設起點
- 合規管理 Tab：供應材料清單（BOM 需求）與合規文件並列，明確因果關係
- 修正所有 always-visible section 的 DOM 問題，讓 Tab 切換真正有效
- 純前端重排，不需要後端 API 變更

**Non-Goals:**
- 不新增 API endpoint
- 不改動風險評估計算邏輯
- 不改動合規文件上傳 / CRUD 功能
- 不改動其他頁面（SuppliersView 列表頁、合規明細頁）

## Decisions

### D1：Tab 結構從「資料分類」改為「工作情境」

**選擇**：4 個 Tab（概況、永續績效、合規管理、設施 & 聯絡）

**捨棄方案**：保留 6 Tab 並修正 DOM — Tab 數量多但內容稀疏（管理歸屬只有 2 個欄位），認知負擔高。

**理由**：使用者開啟明細頁有明確的工作目的，Tab 應封裝一個完整的工作流，而非按資料表欄位分類。

---

### D2：概況 Tab 合併識別資訊 + 產業分類 + 管理歸屬

**選擇**：三個原本各自為一 Tab 的稀疏資訊，合併進概況 Tab 的 detail-grid。

**理由**：產業分類（2 個欄位）、管理歸屬（2 個欄位）資訊量不足以支撐獨立 Tab；合併後風險評估 scorecard 可作為概況 Tab 的首要視覺焦點。

---

### D3：合規管理 Tab — BOM 清單置於合規文件上方

**選擇**：上方顯示「供應材料清單」（BOM → required doc types），下方緊接「合規文件」（actual doc upload status）。

**理由**：因果鏈 — 使用者需要先知道「因為供應了這個物料群組，所以需要這份文件」，再看「這份文件目前的狀態」。兩個 section 視覺上毗鄰，強化這個認知連結。

---

### D4：Disclosure Profile DOM 修正

**選擇**：將 Disclosure Profile section 的 `v-show` 邏輯從 `activeTab === 'disclosure'` 改為 `activeTab === 'sustain'`，並將其 DOM 位置移入永續績效 Tab 的 `<div v-show="activeTab === 'sustain'">` 區塊內。

**理由**：原本 Disclosure Profile 的 DOM 節點插在兩個 always-visible section 之間，頁面 flow 無法被 `v-show` 控制；移入正確的 Tab 容器後問題自動消除。

---

### D5：Lazy load 維持現有邏輯

**選擇**：Tab 首次切換時才載入該 Tab 的資料（`loadedTabs` Set 控制），與 SalesProductDetailView 相同模式。

**理由**：避免頁面初始化同時打 6 個 API，減少不必要的 loading。

## Risks / Trade-offs

- **[風險] 重構量大**：SupplierDetailView 是最複雜的頁面，DOM 重組容易引入 regression → 緩解：逐 Tab 實作，每個 Tab 完成後在瀏覽器驗證，最後做完整 smoke test
- **[Trade-off] 合規管理 Tab 資訊密度較高**：BOM 清單 + 合規文件並列，若供應商 BOM 條目多，頁面會很長 → 接受，後續可考慮折疊面板，但不在本次範圍內

## Migration Plan

1. 備份現有 SupplierDetailView.vue（git commit 為還原點）
2. 重構 `tabs` 陣列定義為 4 個新 Tab
3. 逐 Tab 移動 DOM 內容，同步修正 `v-show` 條件
4. 修正 Disclosure Profile DOM 位置
5. 調整 `mounted()` 初始載入邏輯
6. 瀏覽器 smoke test：每個 Tab、每個角色

**Rollback**：`git revert` 單一 commit 即可還原。
