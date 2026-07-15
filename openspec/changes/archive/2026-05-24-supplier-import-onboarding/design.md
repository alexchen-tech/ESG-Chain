## Context

現有 `suppliers` 表直接接受手動輸入，沒有 VAT Number、spend_amount、tags、profile_completed 欄位。`supplier_contacts` 表儲存 Email，但匯入流程需要在暫存階段就能做 Email 驗證，因此 MVD 的 Primary Email 在暫存表中獨立存放，放行後寫入 supplier_contacts。現有 PortalView 供應商登入後直接顯示問卷列表，無主檔補齊守衛。

## Goals / Non-Goals

**Goals:**
- `supplier_imports` 完整暫存 + 清洗 + 狀態流轉
- `suppliers` 補 5 個新欄位（vat_number/erp_vendor_codes/spend_amount/tags/profile_completed）
- CSV 上傳頁：解析、預覽、提交匯入
- 清洗服務：L1 Email regex + L2 VAT 去重（同 VAT 合併 erp_vendor_codes）
- 採購員儀表板：篩選 staged/rejected、補齊 email、豁免（exempt）、放行（approve）
- 放行後寫主表 + 建立 SupplierContact（primary email）+ 觸發邀請信
- Portal profile_completed 守衛 + SupplierProfileView

**Non-Goals:**
- 真實 ERP API 對接（本次只做 CSV，ERP API 介接留待後期）
- Sanctions Check（黑名單聯查，留後期整合外部 API）
- ERP 回寫（SSOT 閉環，留後期）
- GPS 廠區座標地圖（Portal 補齊只做 text 地址，GPS 地圖另排）

## Decisions

**D1：supplier_imports 表設計**
```sql
id UUID PK
batch_id VARCHAR(36)        -- 同一次匯入的批次 ID（UUID）
vendor_code VARCHAR(50)
vat_number VARCHAR(50)       -- 核心去重鍵
vendor_name VARCHAR(255)
spend_amount DECIMAL(15,2) nullable
country_code CHAR(2) nullable
material_group VARCHAR(100) nullable
primary_email VARCHAR(255) nullable
cleanse_status ENUM(staged, cleansed, rejected, approved, exempt)
failure_codes JSON nullable  -- ["email_invalid","duplicate_vat"]
notes TEXT nullable          -- 採購員手動備註 / 豁免原因
erp_vendor_codes JSON nullable -- 合併後的所有 ERP Vendor Code
created_at / updated_at
```

**D2：L2 VAT 去重邏輯**
- 同一 `vat_number` 的多筆 import 記錄：
  - 第一筆設為 cleansed，`erp_vendor_codes` 收集所有代碼
  - 其餘筆設為 rejected，`failure_codes = ["duplicate_vat_merged"]`
- `vat_number` 已存在 `suppliers` 主表：`failure_codes = ["vat_exists_in_master"]`，採購員可決定更新主表或豁免

**D3：清洗服務執行時機**
- CSV 上傳後立即非同步執行（同步亦可，資料量不大）
- 清洗完成後前端可輪詢批次狀態

**D4：放行（approve）流程**
1. `supplier_imports.cleanse_status → approved`
2. 建立 `suppliers` 記錄（name/code/vat_number/erp_vendor_codes/spend_amount/country_code/tags/industry/profile_completed=false/status=inactive/onboarding_stage=potential）
3. 建立 `supplier_contacts`（primary email，is_primary=true）
4. 觸發邀請信（暫時 log，郵件功能留後期）

**D5：Portal 主檔覆核卡（SupplierProfileView）**
- 供應商登入後，PortalView 的 `created()` / `mounted()` 檢查 `supplier.profile_completed`
- `false` → router.push('/supplier/profile')
- 覆核卡欄位：永續/安衛主管 Email（更新 supplier_contacts）、實體廠區地址（更新 suppliers.address）
- 送出後 API 設 `profile_completed=true` + `onboarding_stage=invited`，觸發 ESG 問卷發送（呼叫 questionnaire send API）

**D6：CSV 格式規範**
Header 列必須包含（不分順序）：
`vendor_code, vat_number, vendor_name, spend_amount, country_code, material_group, primary_email`
允許中文 header 別名：廠商代碼/統編VAT/廠商名稱/年採購額/國家碼/採購類別/主要信箱

## Risks / Trade-offs

- **VAT 格式多樣**：台灣 8 碼、EU VAT 以 GB/DE 開頭等，本次只做 non-empty 驗證，不做格式 regex（避免誤擋）
- **大 CSV**：5,000 筆約 500KB，同步清洗 < 2 秒，不需要 Celery queue
- **邀請信暫不寄出**：後期接 Mail 服務（SMTP/SES），本次只寫入 DB 記錄
