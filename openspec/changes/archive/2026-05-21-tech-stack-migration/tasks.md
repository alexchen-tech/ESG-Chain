# Tasks: 技術棧轉換 — Phase 0（基礎設施）

## Phase 0: 基礎設施建立

- [x] T01 更新 CLAUDE.md：將技術棧說明從 Next.js/NestJS 改為 Vue 3/Laravel/FastAPI，更新目錄結構、開發指令、環境變數說明
- [x] T02 建立 esgchain-api（Laravel 骨架）：composer create-project、安裝 jwt-auth + spatie/laravel-permission、設定 MySQL 連線、CORS、JWT RS256 金鑰對、建立 Services/ Repositories/ 目錄結構
- [x] T03 建立 esgchain-ai（FastAPI 骨架）：uv 初始化 Python 專案、安裝 fastapi/uvicorn/sqlalchemy/celery/redis/PyJWT、建立 app/ 目錄結構、設定 PostgreSQL 連線、JWT RS256 公鑰驗證、Celery 初始化
- [x] T04 建立 esgchain-web（Vue 3 骨架）：npm create vue@latest、設定 Vite + TypeScript + Vue Router + Pinia、移植 Warm Paper Light CSS 變數、建立 axios 實例與攔截器、建立 auth store、建立基礎 Layout（Sidebar + Topbar）
- [x] T05 Docker Compose 整合：撰寫 docker-compose.yml（mysql/postgres/redis/api/ai/web/nginx）、撰寫 nginx/default.conf 路由設定
- [x] T06 JWT 認證流程端對端驗證：Laravel 實作 /api/auth/login + refresh + me、FastAPI 實作受保護測試端點、Vue 3 登入頁、seed 測試帳號
- [x] T07 退場現有 apps/ 目錄：確認 T01–T06 完成後移除 apps/web、apps/api、packages/types、Turborepo 設定

## Phase 1（後續）: 核心業務 — Laravel

- [x] T10 Supplier MDM + RBAC（Spatie roles/permissions）
- [x] T11 SAQ 生命週期（send → submit → review）
- [x] T12 CAP 矯正行動計畫
- [x] T13 Trade Goods + CBAM 判定

## Phase 2（後續）: 計算引擎 — FastAPI

- [x] T20 SAQ 計分引擎（E/S/G 加權 → SGS 五級）
- [x] T21 PCF 碳足跡計算（Scope 1/2/3）
- [x] T22 Emission Factor 查找服務
- [x] T23 風險評分模型

## Phase 3（後續）: 前端模組 — Vue 3

- [x] T30 Dashboard
- [x] T31 Supplier 管理頁
- [x] T32 SAQ 問卷管理
- [x] T33 CAP 矯正行動
