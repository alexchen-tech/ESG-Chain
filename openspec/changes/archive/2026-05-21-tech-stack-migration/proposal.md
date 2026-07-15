# Change Proposal: 技術棧轉換（Tech Stack Migration）

## 動機

統一公司技術棧標準（Tech_Arch_CLAUDE_v02.md）。現有 ESG-Chain 使用 Next.js 14 + NestJS + Prisma，與公司標準的 Vue 3 + Laravel + FastAPI 架構不一致。現有後端仍處於骨架階段，適合直接重建而非 port。

## 範圍

**這是 Rebuild（重建），不是 Migration（遷移）。**

- 移除現有 `apps/web`（Next.js）、`apps/api`（NestJS）目錄
- 建立全新三層服務架構：`esgchain-web`、`esgchain-api`、`esgchain-ai`
- 保留 Prisma schema 作為資料模型設計參考
- 保留 FRS v2.0 作為業務邏輯規格
- 更新 CLAUDE.md 反映新技術棧

## 目標架構

```
ESG-Chain/
├── esgchain-web/          # Vue 3 + Vite + Pinia + TypeScript
├── esgchain-api/          # Laravel 12 (PHP 8.5) + MySQL 8.4
├── esgchain-ai/           # FastAPI (Python 3.12) + PostgreSQL + Celery + Redis
├── nginx/
│   └── default.conf
├── docker-compose.yml
├── docker-compose.prod.yml
└── README.md
```

## 不在範圍內（本次）

- NebulaGraph（供應鏈圖關係）— MySQL adjacency list 先行
- pgvector / RAG — 未來 AI 功能再引入
- LangGraph Agent — Phase 3+ 才需要

## 成功條件

- [ ] 三個服務可透過 Docker Compose 一鍵啟動
- [ ] JWT RS256 認證流程跑通（Laravel 發行 → Vue 3 前端 → FastAPI 驗證）
- [ ] CLAUDE.md 反映新架構，開發指令正確
- [ ] 現有 `apps/` 目錄退場
