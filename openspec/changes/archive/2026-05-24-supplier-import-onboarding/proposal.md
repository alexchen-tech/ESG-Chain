## Why

ESG-Chain 目前只能手動逐筆新增供應商，且直接寫入主表，無任何資料品質閘門。實際企業導入時，ERP 匯出的供應商主檔往往有「Email 空白/錯誤、同一廠商多個 Vendor Code、缺少 VAT 號碼」等問題，若直接匯入將造成重複問卷、退信災難與計分錯誤。本次建立完整的「ERP 匯出 → 暫存清洗 → 採購員補救 → 放行 → Portal 主檔補齊」Onboarding 流程，以資料閘門保護系統資料品質。

## What Changes

**DB 結構：**
- 新增 `supplier_imports` 暫存表（staged/cleansed/rejected/approved，含完整 7 個 MVD 欄位與清洗狀態）
- `suppliers` 表補欄位：`vat_number`、`erp_vendor_codes`（JSON）、`spend_amount`、`tags`（JSON）、`profile_completed`（boolean）

**後端：**
- CSV 批次匯入 API：解析 7 個 MVD 欄位，寫入 `supplier_imports`
- 清洗服務：L1 Email 格式防呆、L2 VAT Number 去重（同 VAT 自動合併 erp_vendor_codes）
- 放行 API：將審核通過的暫存記錄寫入 `suppliers` 主表，觸發 Portal 邀請信
- 異常清單 API：列出 staged/rejected 待補救記錄

**前端：**
- `/suppliers/import`：CSV 上傳頁面，顯示匯入預覽與清洗結果
- `/suppliers/import/review`：採購員異常儀表板（補齊 Email/申請豁免/放行）
- `PortalView`：首次登入偵測 `profile_completed=false` → 強制顯示主檔覆核卡
- 新增 `SupplierProfileView.vue`：供應商補充永續主管 Email 與廠區地址

## Capabilities

### New Capabilities
- `supplier-csv-import`: CSV 批次上傳 7 個 MVD 欄位，寫入 staging area
- `supplier-import-cleanse`: L1 Email 防呆 + L2 VAT 去重，產生清洗報告
- `supplier-import-review`: 採購員異常儀表板，補齊/豁免/放行操作
- `supplier-profile-card`: Portal 主檔覆核卡（供應商首次登入強制填寫，profile_completed 閘門）

### Modified Capabilities
- `supplier-portal-gate`: PortalView 加入 profile_completed 守衛，未補齊前鎖問卷入口

## Impact

- **DB**：2 個 migration（新增 supplier_imports 表 + 更新 suppliers 欄位）
- **後端**：新 SupplierImportController、SupplierImportService、4 條新路由
- **前端**：2 個新頁面（匯入上傳、異常儀表板）、1 個新 Portal 子頁、修改 PortalView 守衛
- **無破壞性**：現有 suppliers CRUD 完全不受影響
