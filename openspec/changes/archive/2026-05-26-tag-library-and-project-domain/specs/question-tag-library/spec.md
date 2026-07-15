# Spec: question-tag-library

## 定義

標籤庫是題目分類的 source of truth，採三層階層結構：

- **L1 領域（Domain）**：宏觀合規框架，對應 SaqProject.domain 的計分過濾鍵
- **L2 支柱（Pillar）**：該框架的核心管理維度，驅動 ESG 雷達圖與前端跨部門分工
- **L3 主題（Topic）**：具體衡量變數，為 esgchain-ai 計分演算法的識別鍵（slug）

## Slug 規則

- 格式：`{l1_key}.{l2_key}.{l3_key}`，全小寫 snake_case，以 `.` 分隔
- 唯一性：系統層級全域唯一（UNIQUE index）
- 不可變性：建立後不得 UPDATE，廢棄時設 `deprecated_at`，新建替代 slug
- 計分引擎依賴：esgchain-ai 的 `scoring_router` 以 slug 作為 handler 映射鍵，slug 變更必須搭配 AI 服務重新部署

## 標籤操作規則

### 新增 L3
- `slug` 由後端根據 `l1_domain + l2_pillar + label_en` 自動生成，前端可在建立前手動覆寫
- 建立成功後，`slug` 欄位在後續所有 PUT 請求中被忽略（後端強制保護）
- `scoring_engine_key` 選填，留空表示此 L3 僅做分類用途，不驅動計分

### 停用 L3
- 設 `deprecated_at = NOW()`，不做 hard delete
- 停用後：TagSelector 不顯示該標籤、QuestionBankFilter 不列出、已有 assignments 不受影響
- 停用條件：不需要確認底下有無題目使用（assignment 繼續存在，只是無法新選）

### 停用 L1 / L2
- 級聯停用邏輯：L1/L2 停用時，底下所有 L3 視為一同停用（前端不顯示）
- 資料不刪除，`deprecated_at` 設於 L1/L2 節點本身（L3 記錄不動）

## 管理員 UI 驗收條件

- [ ] 左側樹狀顯示所有 active 的 L1/L2，點選 L2 後右側列表顯示該 L2 底下的 L3
- [ ] 新增 L3 Modal：slug 自動生成、建立前可手動修改、顯示不可變警告
- [ ] 編輯 L3 Modal：slug input disabled（🔒）、可修改 label 與 scoring_engine_key
- [ ] 修改 `scoring_engine_key` 時顯示「需同步更新 esgchain-ai」警告
- [ ] 停用 L3：確認 Modal 後設 deprecated_at，列表移除該列
- [ ] deprecated 標籤不出現在 TagSelector 與 QuestionBankFilter 的選項中
