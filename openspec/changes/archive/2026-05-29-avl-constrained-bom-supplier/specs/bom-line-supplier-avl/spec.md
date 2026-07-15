## MODIFIED Requirements

### Requirement: AVL 管理移至 BOM Panel 底部
產品層級的已認可供應商清單（AVL）管理介面 SHALL 位於 BOM Panel 底部，以分隔線與 BOM 表格區隔，標題為「已認可供應商（AVL）」，並顯示說明文字「AVL 廠商需在 BOM 明細中指定為供應商，才會納入合規計算」。AVL 同時作為手動指定 BomLine 供應商的候選池，AVL 成員資格是手動 BomLine 供應商指派的前提條件。

#### Scenario: AVL 區塊可見
- **WHEN** BOM Panel 展開
- **THEN** 使用者 SHALL 在 BOM 表格下方看到 AVL 供應商清單，可新增或移除

#### Scenario: AVL 供應商說明
- **WHEN** 使用者查看 AVL 清單
- **THEN** SHALL 顯示說明文字，引導使用者理解 AVL 與合規計算及 BomLine 指派的關係

#### Scenario: AVL 作為 BomLine 指派候選池
- **WHEN** 使用者在 BomLine sub-row 手動新增供應商
- **THEN** 供應商下拉 SHALL 只列出此產品 AVL 中的成員，不顯示 MDM 中其他供應商
