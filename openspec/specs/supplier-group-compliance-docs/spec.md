### Requirement: SupplierGroup 綁定廠商資格文件類型
SupplierGroup 記錄 SHALL 包含 `required_doc_types` 欄位（JSON 陣列），儲存該分組供應商需提交的長效廠商資格文件類型。值域為獨立於 MaterialGroup 的廠商文件類型枚舉：`SMETA_AUDIT`、`ISO_9001`、`FACTORY_AUDIT`、`HIGG_FEM`、`OEKO_TEX`、`BSCI`、`ZDHC_MRSL`。

#### Scenario: 建立分組時可指定廠商文件要求
- **WHEN** 管理員建立或編輯 SupplierGroup，並選取一或多個廠商文件類型
- **THEN** 系統儲存 required_doc_types 陣列，API 回傳包含該欄位

#### Scenario: required_doc_types 為空時合法
- **WHEN** 管理員建立 SupplierGroup 但未選取任何廠商文件類型
- **THEN** 系統儲存空陣列 `[]`，不回傳錯誤

#### Scenario: 設定頁顯示廠商文件類型多選
- **WHEN** 管理員開啟供應商分組的編輯介面
- **THEN** 顯示廠商文件類型的多選元件，已選項目以標籤（chip）呈現，可逐一移除
