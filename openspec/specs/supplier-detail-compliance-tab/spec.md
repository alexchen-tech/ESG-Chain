## Purpose

定義供應商明細頁「合規管理」Tab 的內容結構，包含供應材料清單（BOM → required_doc_types）與合規文件（actual doc status）因果鏈並列展示，以及 Disclosure Profile 移至永續績效 Tab 的 DOM 位置修正。
## Requirements
### Requirement: 合規管理 Tab 呈現 BOM 需求與合規文件的因果鏈

合規管理 Tab SHALL 在同一頁面依序顯示：（1）「供應材料清單」section — 列出此供應商供應的 BomLine 及各物料群組的 required_doc_types；（2）「合規文件」section — 列出實際上傳的文件與狀態。兩個 section 毗鄰排列，讓使用者可一眼看出「需要哪些文件」與「目前提交了哪些文件」的缺口。

#### Scenario: 合規管理 Tab 顯示 BOM 需求清單在上

- **WHEN** 使用者切換至合規管理 Tab
- **THEN** 頁面 SHALL 先顯示「供應材料清單」section，列出每條 BomLine 的物料名稱、物料群組及該群組的 required_doc_types

#### Scenario: 合規文件緊接 BOM 清單之下

- **WHEN** 使用者切換至合規管理 Tab
- **THEN** 「合規文件」section SHALL 緊接在「供應材料清單」section 下方，同在合規管理 Tab 的 v-show 容器內

#### Scenario: BOM 需求與文件狀態不在其他 Tab 顯示

- **WHEN** 使用者切換至概況、永續績效、設施 & 聯絡任一 Tab
- **THEN** 供應材料清單與合規文件 SHALL 不可見（非 always-visible）

### Requirement: Disclosure Profile 移至永續績效 Tab

Disclosure Profile KPI 時間序列 section SHALL 置於永續績效 Tab 的 `v-show` 容器內，與問卷記錄 section 同屬該 Tab。

#### Scenario: Disclosure Profile 在永續績效 Tab 可見

- **WHEN** 使用者切換至永續績效 Tab
- **THEN** Disclosure Profile section SHALL 出現於該 Tab 的可見範圍內

#### Scenario: Disclosure Profile 在其他 Tab 不可見

- **WHEN** 使用者切換至概況、合規管理、設施 & 聯絡任一 Tab
- **THEN** Disclosure Profile section SHALL 被 v-show 隱藏，不佔版面

