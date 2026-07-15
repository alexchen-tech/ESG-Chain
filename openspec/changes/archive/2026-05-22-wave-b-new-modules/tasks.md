# Tasks: Wave B — New Modules

## B1 M3 Risk 完整重設計

- [x] B1.1 Migration：drop 舊 risk_assessments/risk_factors 表，建立新 risk_assessments 表（E/S/G/GP probability/impact 欄位）
- [x] B1.2 RiskAssessment Model：新欄位定義、scoreToLevel() helper、buildDimension() helper
- [x] B1.3 RiskAssessmentController：GET /risk/assessments（列表）+ POST /risk/assessments（建立）
- [x] B1.4 RiskMatrixController：GET /risk/matrix（5×5 矩陣）+ GET /risk/matrix/suppliers（格子下鑽）
- [x] B1.5 SupplierController@riskSummary：完整實作（取代 Wave A 的空回應）
- [x] B1.6 更新 routes/api.php：新增 /risk/* 路由
- [x] B1.7 Seeder：植入 3 筆測試風險評估資料（台灣鋼鐵/VGE/上海精密）

## B2a 問卷範本管理（Settings）

- [x] B2a.1 Migration：saq_templates 表新增 created_by_id 欄位
- [x] B2a.2 QuestionnaireTemplateController：完整 CRUD（GET list/GET detail/POST/PUT/DELETE）
- [x] B2a.3 支援 is_active、sasb_industry_id 篩選

## B2b 供應商分組管理（Settings）

- [x] B2b.1 SupplierGroupController：完整 CRUD（GET list/POST/PUT/DELETE）

## B2c SASB 產業分類（Settings）

- [x] B2c.1 Migration：確認 sasb_industries 表存在（已有？確認欄位：id/sector/industry/code）
- [x] B2c.2 SasbIndustryController：GET /settings/sasb-industries（唯讀，支援 sector 篩選）
- [x] B2c.3 SasbIndustrySeeder：植入完整 SASB 產業分類（11 sectors，約 77 industries）

## B3 Settings 路由整合

- [x] B3.1 更新 routes/api.php：新增 /settings/* 路由（admin RBAC 保護寫入端點）
