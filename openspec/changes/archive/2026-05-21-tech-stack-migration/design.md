# Design: 技術棧轉換

## 雙後端切割原則

### esgchain-api（Laravel + MySQL）— 業務流程層

負責所有「狀態機、生命週期、CRUD、RBAC、通知」：

- Auth（JWT RS256 發行）
- Supplier MDM + 狀態流轉（潛在→邀請中→審核中→已認證→暫停→終止）
- SAQ 生命週期（send → submit → review）
- CAP 矯正行動計畫
- Trade Goods + CBAM 判定
- Reports 報告驗證
- Notifications
- Audit Logs

### esgchain-ai（FastAPI + PostgreSQL + Celery）— 計算引擎層

負責所有「數值計算、評分模型、未來 AI」：

- SAQ 計分引擎（E/S/G 加權 → SGS 五級評核）
- PCF 碳足跡計算（Scope 1/2/3，totalKgCO2e）
- Emission Factor 查找服務
- 風險評分模型（RiskAssessment）
- LCC 生命週期分析
- EPD 申請處理
- Scope3 報告計算

### 資料模型歸屬

**MySQL（Laravel）**
OrganizationUnit, User, UserPermission, SupplierGroup, Supplier, SupplierContact,
SupplierExternalData, SupplierStatusHistory, SupplierCertification,
SAQTemplate, SAQQuestion, SaqProject, SaqRound, SaqGroupRoundTemplate,
SaqProjectMilestone, SAQ, SAQResponse, SaqReviewHistory,
CAP, CAPFinding, TradeGood, Report, Notification, AuditLog,
SupplyChainLink, DecarbPlan, DecarbMilestone

**PostgreSQL（FastAPI）**
EmissionFactor, ScoringModel, SasbIndustry, SasbStandard,
PCFRecord, RiskAssessment, RiskDimensionScore, RiskFactor,
LCCAnalysis, EPDRequest, Scope3Report

## Laravel ↔ FastAPI 橋接設計

SAQ submit → 觸發計分流程：

```
Vue 3 → POST /saq/{id}/submit（Laravel）
  → 更新 SAQ status = submitted
  → POST /internal/scoring（FastAPI，Guzzle，timeout > 60s）
  → FastAPI 返回 job_id
  → Laravel 儲存 job_id 到 SAQ 欄位

Celery 任務完成 → POST /internal/callback/scoring（Laravel）
  → 更新 SAQ score + review status
```

## JWT 認證規範

- 演算法：RS256（非對稱）
- Laravel 持有私鑰 → 負責發行 + 換發
- FastAPI 使用公鑰 → 只驗證，不發行
- Token payload：`{ sub, roles, supplierId, exp, iat, jti }`
- Access Token TTL：60 分鐘
- Refresh Token TTL：7 天，換發後舊 token 立即失效（Rotation）

## RBAC 角色對應（維持現有設計）

| 角色 | 可存取模組 |
|------|-----------|
| admin | 全部 |
| buyer | dashboard, suppliers, tradegoods, cap |
| sustain | dashboard, suppliers, saq, cap, pcf, decarb, reports |
| comply | dashboard, suppliers, saq, cap, tradegoods, reports |
| analyst | dashboard, suppliers, saq, pcf, decarb, reports |
| supplier | portal |
| sup_esg | portal |

## 設計系統（維持 Warm Paper Light）

Vue 3 前端繼承現有設計語言，以 CSS 變數方式移植：

```css
--bg: #f5f3ee; --surface: #ffffff; --surface-2: #f0ede6;
--border: #e2ddd6; --text-primary: #1c1917; --text-secondary: #78716c;
--accent: #1a4d3e; --sidebar-bg: #1a1714;
```

字型：Syne（標題）、Fira Code（數字/等寬）、Noto Sans TC（中文內文）
