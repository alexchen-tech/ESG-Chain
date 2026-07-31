# supplier-contact-invitation Specification

## Purpose
TBD - created by archiving change user-role-management. Update Purpose after archive.
## Requirements
### Requirement: 供應商多聯絡人邀請
系統 SHALL 允許 admin 為既有供應商新增額外的 Portal 登入帳號（角色限定 supplier/sup_esg），不需等待 ERP 同步。

#### Scenario: Admin 邀請供應商第二聯絡人
- **WHEN** admin 對某供應商送出新聯絡人的姓名、email、角色（supplier 或 sup_esg）
- **THEN** 系統 SHALL 建立對應 `User` 記錄，`supplier_id` 綁定該供應商，角色僅限 supplier/sup_esg

#### Scenario: 角色白名單限制
- **WHEN** 邀請請求指定的角色不是 supplier 或 sup_esg
- **THEN** 系統 SHALL 拒絕建立並回傳驗證錯誤

### Requirement: 供應商帳號清單查詢
系統 SHALL 提供查詢指定供應商底下所有登入帳號的 API，admin 可查任一供應商，supplier/sup_esg 角色僅能查詢自己所屬供應商。

#### Scenario: Admin 查詢供應商帳號清單
- **WHEN** admin 呼叫某供應商的帳號清單 API
- **THEN** 系統 SHALL 回傳該供應商底下所有使用者帳號（姓名、email、角色、啟用狀態）

#### Scenario: 供應商角色查詢其他供應商帳號被拒絕
- **WHEN** supplier 或 sup_esg 角色的使用者嘗試查詢非自己所屬供應商的帳號清單
- **THEN** 系統 SHALL 回傳 403，不揭露該供應商是否存在帳號資料

### Requirement: 供應商詳情頁登入帳號區塊
系統 SHALL 在供應商詳情頁提供登入帳號清單顯示與邀請新聯絡人的操作入口。

#### Scenario: 檢視供應商登入帳號
- **WHEN** admin 開啟某供應商的詳情頁
- **THEN** 系統 SHALL 顯示該供應商底下所有登入帳號清單，並提供「邀請新聯絡人」操作

