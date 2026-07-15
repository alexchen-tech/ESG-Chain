## ADDED Requirements

### Requirement: 採購員異常儀表板
`/suppliers/import/review` 頁面 SHALL 列出所有 cleanse_status=rejected 的記錄，顯示廠商名稱、VAT Number、primary_email、failure_codes（中文說明），提供三個操作：補齊 Email、申請豁免（exempt）、整批放行已 cleansed 記錄。

#### Scenario: 查看異常清單
- **WHEN** 採購員進入異常儀表板
- **THEN** 列出所有 rejected 記錄及失敗原因，已 cleansed 的記錄以不同顏色區分

#### Scenario: 手動補齊 Email 後重新驗證
- **WHEN** 採購員填入正確 Email 後點擊「重新驗證」
- **THEN** 該記錄重新跑 L1 清洗，通過後 cleanse_status=cleansed

### Requirement: 豁免（Exempt）操作
採購員 SHALL 能對特定記錄申請豁免，需填寫豁免原因（如「對方為台電，無主要聯絡信箱」），豁免後 cleanse_status=exempt，計入放行範圍。

#### Scenario: 申請豁免
- **WHEN** 採購員填寫原因後確認豁免
- **THEN** cleanse_status=exempt，notes 記錄原因，可參與放行

### Requirement: 批次放行 API
`POST /api/v1/suppliers/import/{batchId}/approve` SHALL 將所有 cleansed 與 exempt 的記錄批次寫入 `suppliers` 主表，建立 supplier_contacts（primary email），設 profile_completed=false、status=inactive、onboarding_stage=potential，回傳放行成功筆數。

#### Scenario: 批次放行
- **WHEN** admin 點擊「放行已清洗供應商」
- **THEN** cleansed + exempt 記錄全部寫入 suppliers，supplier_imports.cleanse_status=approved，回傳 { approved_count: N }

#### Scenario: 重複放行保護
- **WHEN** 同一 vat_number 已在 suppliers 主表但非豁免，嘗試放行
- **THEN** 跳過此筆，在回應中列出 skipped_count
