## Why

物料主檔（MaterialItem）目前只儲存靜態屬性，沒有供應商維度的碳排強度資料，導致產品碳足跡（PCF）無法自動計算。品牌採購商必須手動彙整多個供應商的碳排數值，且無法追蹤歷史版本或支援 ISO 14067 合規出口。

## What Changes

- **新增** `material_item_emissions` 表：每筆記錄為「供應商 S 生產物料 M 的碳排強度」，支援多版本追蹤（append-only）
- **新增** `pcf_snapshots` 表：BuyerProduct 的 PCF 快照，含完整 BOM 明細、資料來源、ISO 14067 準備度標記
- **新增** MaterialItem 物料主檔頁「碳排資料庫」分頁：顯示所有供應商的提報值（含 AI 估算），支援買方代填與異常標記
- **新增** BomLine 主供應商 UI：明確選定計入 PCF 的主供應商，切換時自動觸發 PCF 重算
- **新增** BuyerProduct 清單 PCF 欄位：顯示最新快照值、iso14067_ready 狀態、查看明細
- **新增** Portal 物料碳排提報頁籤：供應商查看「需提報的物料清單」（含 AI 估算值提示），支援無 BomLine 指定的主動提報
- **新增** AI 估算服務（esgchain-ai）：BomLine 加入且無碳排記錄時，依 HS Code 查 EmissionFactor 自動估算
- **新增** Celery 觸發鏈：供應商提報 → PCF 重算 → 快照寫入

## Capabilities

### New Capabilities

- `material-emission-mdm`: 物料排放強度主數據管理——供應商提報、買方代填、版本歷史、異常標記
- `pcf-auto-calculation`: PCF 自動計算引擎——BomLine × 主供應商碳排 → 加總 → pcf_snapshots，含 ISO 14067 準備度
- `supplier-portal-material-reporting`: 供應商 Portal 物料碳排提報——指定物料清單 + AI 估算值顯示 + 主動提報
- `material-emission-ai-estimation`: AI 估算服務——HS Code → EmissionFactor 推算，標記 source=ai-estimated

### Modified Capabilities

- `bom-management`: BomLineSupplier 主供應商（role=primary）選取 UI 優化，PCF 計算時取 primary 供應商碳排

## Impact

- **後端（Laravel）**：新增 2 個 migration、4 個 Model、MaterialEmissionService、PcfCalculationService、新增 API routes（物料碳排 CRUD、PCF 快照查詢、Portal 提報）
- **後端（FastAPI / esgchain-ai）**：新增 `/ai/v1/material-emission-estimate` 端點、Celery Task `estimate_material_emission`
- **前端（Vue 3）**：MaterialItemsView 新增碳排分頁、BuyerProductsView 新增 PCF 欄位與快照 drawer、BOM 明細主供應商切換、SupplierCompliancePortalView 新增物料提報分頁
- **資料庫**：新增 `material_item_emissions`、`pcf_snapshots` 兩張表
- **隊列**：新增 3 個 Celery tasks（estimate、recalc_pcf、snapshot_write）
