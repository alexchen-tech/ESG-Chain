## ADDED Requirements

### Requirement: BOM 線供應商開放 Combobox 選取

BOM 線供應商選取 UI SHALL 改為 Combobox 元件，候選池為全系統 `status=certified` 的供應商，支援關鍵字即時搜尋，不受產品 AVL 限制。

#### Scenario: 開啟 Combobox 無關鍵字

- **WHEN** 使用者在 BomLine sub-row 點擊供應商選取欄位，尚未輸入關鍵字
- **THEN** Combobox SHALL 顯示最近 20 筆 certified 供應商（依 name 排序），並顯示「輸入供應商名稱或代碼搜尋」提示文字

#### Scenario: 關鍵字即時搜尋

- **WHEN** 使用者在 Combobox 輸入關鍵字（≥1 字元）
- **THEN** 系統 SHALL 呼叫 `GET /api/v1/suppliers?status=certified&q={keyword}&per_page=20`，將結果顯示為下拉清單；每筆顯示供應商名稱、代碼、Tier badge

#### Scenario: Tier 篩選

- **WHEN** 使用者在 Combobox 旁選擇 Tier 篩選（Tier 1 / Tier 2 / Tier 3 / 全部）
- **THEN** 系統 SHALL 在搜尋 API 加入 `tier={value}` 參數，縮小候選池範圍

#### Scenario: 搜尋無結果

- **WHEN** 使用者輸入關鍵字後 API 回傳空陣列
- **THEN** Combobox SHALL 顯示「找不到符合的認證供應商」空狀態提示，不顯示任何選項

#### Scenario: 選取供應商後送出

- **WHEN** 使用者從 Combobox 選取一筆供應商並點擊確認
- **THEN** 系統 SHALL 以選取的 supplier_id 呼叫 BomLineSupplier store API；Combobox 關閉，sub-row 更新供應商清單

### Requirement: 合規文件需求說明標籤

Combobox 下拉候選列表中，每筆供應商條目 SHALL 顯示該供應商所屬物料群組對應的合規文件需求提示（若可推論），協助採購人員快速判斷合規適配性。

#### Scenario: 顯示合規文件需求提示

- **WHEN** 使用者選取 Combobox 中的某供應商，且 BomLine 的 material_group_id 有對應 required_doc_types
- **THEN** 供應商條目下方 SHALL 顯示「需提供：UFLPA / CMRT」等標籤，來源為 MaterialGroup.required_doc_types

#### Scenario: 無法推論合規需求

- **WHEN** BomLine 的 material_group_id 為 null 或 MaterialGroup 無 required_doc_types
- **THEN** 不顯示合規文件需求標籤，供應商條目僅顯示名稱、代碼、Tier
