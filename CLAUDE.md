# ESG·Chain — Claude Code 專案設定

## 專案簡介
永續供應鏈管理平台（Sustainable Supply Chain Management Platform），永創數智開發。
整合供應商ESG管理、碳足跡追蹤（PCF）、CBAM貿易合規、減碳路徑（SBTi）、報告驗證五大核心功能。
商業模式：提供品牌採購商（Buyer）、永續團隊、法遵部門訂閱使用，並提供供應商入口（Portal）讓上游廠商填寫問卷與查閱矯正行動

## 技術棧
- **前端**：Next.js 14 (App Router) + TypeScript + Tailwind CSS（`apps/web`）
- **後端**：NestJS + TypeScript + Prisma ORM（`apps/api`）
- **資料庫**：PostgreSQL + Prisma
- **設計系統**：Warm Paper Light（暖紙米色調）
- **套件管理**：pnpm + Turborepo monorepo

## 設計系統 — Warm Paper Light
```css
--bg: #f5f3ee;          /* 主背景 */
--surface: #ffffff;      /* 卡片/元件背景 */
--surface-2: #f0ede6;   /* 次要背景 */
--border: #e2ddd6;       /* 邊框 */
--text-primary: #1c1917; /* 主要文字 */
--text-secondary: #78716c; /* 次要文字 */
--accent: #1a4d3e;       /* 強調色（深綠） */
--sidebar-bg: #1a1714;   /* 側邊欄背景 */
```
字型：Syne（標題）、Fira Code（數字/等寬）、Noto Sans TC（中文內文）

## 目錄結構
```
apps/web/src/
  app/                         # Next.js App Router 頁面
    (dashboard)/               # 主應用佈局
      dashboard/page.tsx       # 儀表板
      suppliers/page.tsx       # 供應商管理
      saq/page.tsx             # 問卷管理
      cap/page.tsx             # 矯正行動
      tradegoods/page.tsx      # 貿易商品
      pcf/page.tsx             # 碳足跡
      decarb/page.tsx          # 減碳路徑
      reports/page.tsx         # 報告驗證
      settings/page.tsx        # 系統設定
    login/page.tsx             # 登入頁
    api/auth/[...nextauth]/    # NextAuth handler
  components/
    layout/                    # Sidebar, Topbar
    modules/                   # 各模組 View 元件
  contexts/LangContext.tsx     # 多語系 Context
  lib/
    api.ts                     # Axios API client
    i18n.ts                    # 翻譯字典
    utils.ts                   # 工具函式

apps/api/src/
  modules/                     # NestJS 功能模組
    auth/                      # 認證（JWT）
    users/                     # 使用者管理
    suppliers/                 # 供應商主檔
    saq/                       # 問卷管理
    cap/                       # 矯正行動計畫
    trade-goods/               # 貿易商品
    pcf/                       # 碳足跡記錄
    decarb/                    # 減碳路徑
    reports/                   # 報告驗證
    dashboard/                 # 儀表板聚合
    notifications/             # 通知
  prisma/                      # PrismaService
  common/                      # Guards, Decorators

packages/types/src/index.ts    # 共用 TypeScript 型別與 RBAC 定義
```

## 開發指令
```bash
# 後端（apps/api）
npx nest start --watch
# 或
pnpm dev  # 從根目錄

# 前端（apps/web）— 執行在 port 3001
pnpm dev  # 從 apps/web 目錄

# 資料庫
npx prisma migrate dev        # 執行 migration
npx prisma studio             # 開啟 Prisma Studio
npx ts-node prisma/seed.ts    # 植入種子資料

# 全專案（從根目錄）
pnpm dev  # 同時啟動前後端
```

## 測試帳號
| 角色 | Email | 密碼 |
|------|-------|------|
| 管理員 | admin@esgchain.com | demo1234 |
| 採購商 | buyer@esgchain.com | demo1234 |
| 永續長 | sustain@esgchain.com | demo1234 |
| 分析師 | analyst@esgchain.com | demo1234 |

## 程式碼慣例
- 使用繁體中文撰寫註解與 UI 文字
- 所有功能產生並異動皆需要多語系（`i18n.ts` 中同時維護 zh/en）
- API 路由無 global prefix，直接使用 `/auth`、`/suppliers` 等
- 前端互動元件使用 `'use client'` 標記
- 共用型別統一放在 `@esg-chain/types`
- DTO 類別屬性使用 `!` 確定性賦值（NestJS + strict TS）
- 數字欄位用 `font-mono` 顯示
- 沒有要求修改程式碼指令。需完成保留既有程式

## RBAC 角色權限
| 角色 | 可存取模組 |
|------|-----------|
| `admin` | 全部（含 settings, portal） |
| `buyer` | dashboard, suppliers, tradegoods, cap |
| `sustain` | dashboard, suppliers, saq, cap, pcf, decarb, reports |
| `comply` | dashboard, suppliers, saq, cap, tradegoods, reports |
| `analyst` | dashboard, suppliers, saq, pcf, decarb, reports |
| `supplier` | portal |
| `sup_esg` | portal |

## 核心領域模組
1. **Supplier MDM** — 供應商主檔：層級（Tier 1/2/3）、國家、產業、風險評分
2. **SAQ** — 問卷管理：發送→填寫→提交→審查生命週期
3. **CAP** — 矯正行動計畫：與 SAQ/稽核連結，逾期自動更新狀態
4. **Trade Goods** — 貿易商品：HS Code、CBAM 適用判斷（鋼鐵/水泥/鋁/化肥/電力/氫）
5. **PCF** — 碳足跡：範疇一/二/三，totalKgCO2e 自動計算
6. **Decarb** — 減碳路徑：基準年→目標年，SBTi 對齊，里程碑追蹤
7. **Reports** — 報告驗證：CSRD/CBAM/CDP/Custom，保證等級
8. **Portal** — 供應商入口：供應商填寫問卷、查閱 CAP

## 環境變數
- `apps/api/.env`（從 `.env.example` 複製）
- `apps/web/.env.local`（從 `.env.local.example` 複製）

```bash
# apps/api/.env
DATABASE_URL="postgresql://..."
JWT_SECRET="your-secret"
PORT=3000

# apps/web/.env.local
NEXT_PUBLIC_API_URL=http://localhost:3000
NEXTAUTH_URL=http://localhost:3001
NEXTAUTH_SECRET="your-nextauth-secret"
```
