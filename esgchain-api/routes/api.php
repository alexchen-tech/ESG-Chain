<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\CAP\CAPController;
use App\Http\Controllers\Api\Questionnaire\ImportFromBankController;
use App\Http\Controllers\Api\Questionnaire\QuestionnaireController;
use App\Http\Controllers\Api\Questionnaire\RecommendTemplatesController;
use App\Http\Controllers\Api\Reports\ReportController;
use App\Http\Controllers\Api\Risk\RiskAssessmentController;
use App\Http\Controllers\Api\Risk\AiRiskSuggestionController;
use App\Http\Controllers\Api\Risk\RiskMatrixController;
use App\Http\Controllers\Api\Risk\RiskHeatmapController;
use App\Http\Controllers\Api\Risk\GeoEventController;
use App\Http\Controllers\Api\SAQ\SAQController;
use App\Http\Controllers\Api\Settings\OrganizationUnitController;
use App\Http\Controllers\Api\Settings\QuestionnaireTemplateController;
use App\Http\Controllers\Api\Settings\SAQQuestionController;
use App\Http\Controllers\Api\Settings\QuestionBankController;
use App\Http\Controllers\Api\Settings\DimWeightDefaultsController;
use App\Http\Controllers\Api\Settings\QuestionTagWeightController;
use App\Http\Controllers\Api\Settings\TagLibraryController;
use App\Http\Controllers\Api\SAQ\SaqProjectController;
use App\Http\Controllers\Api\SAQ\AssessmentSeriesController;
use App\Http\Controllers\Api\SAQ\AssessmentSeriesWeightController;
use App\Http\Controllers\Api\SAQ\AssessmentSeriesComparisonController;
use App\Http\Controllers\Api\Settings\SasbDisclosureTopicController;
use App\Http\Controllers\Api\Settings\SasbIndustryController;
use App\Http\Controllers\Api\Settings\ScoringModelProxyController;
use App\Http\Controllers\Api\Settings\SasbRequiredTopicController;
use App\Http\Controllers\Api\Settings\SupplierGroupController;
use App\Http\Controllers\Api\Suppliers\SupplierController;
use App\Http\Controllers\Api\Suppliers\SupplierContactController;
use App\Http\Controllers\Api\Suppliers\SupplierImportController;
use App\Http\Controllers\Api\Suppliers\SupplierProfileController;
use App\Http\Controllers\Api\TradeGoods\TradeGoodController;
use App\Http\Controllers\Api\Compliance\MaterialGroupController;
use App\Http\Controllers\Api\Compliance\BuyerProductImportController;
use App\Http\Controllers\Api\Compliance\SupplierComplianceDocController;
use App\Http\Controllers\Api\Compliance\ComplianceDashboardController;
use App\Http\Controllers\Api\Compliance\Scope3Category1Controller;
use App\Http\Controllers\Api\Compliance\ProductBomLineController;
use App\Http\Controllers\Api\Compliance\BomLineSupplierController;
use App\Http\Controllers\Api\Compliance\MaterialItemSupplierController;
use App\Http\Controllers\Api\Compliance\MaterialItemController;
use App\Http\Controllers\Api\Settings\MarketDefinitionController;
use App\Http\Controllers\Api\Portal\PortalController;
use App\Http\Controllers\Api\Portal\PortalPcfController;
use App\Http\Controllers\Api\Portal\PortalTradeGoodController;
use App\Http\Controllers\Api\PCF\MaterialEmissionController;
use App\Http\Controllers\Api\PCF\PcfRequestController;
use App\Http\Controllers\Api\PCF\PcfSnapshotController;
use App\Http\Controllers\Api\PCF\PcfRecalcController;
use App\Http\Controllers\Api\Portal\PortalMaterialEmissionController;
use App\Http\Controllers\Api\ProductionBatch\ProductionBatchController;
use App\Http\Controllers\Api\ProductionBatch\RawMaterialOriginController;
use App\Http\Controllers\Api\ProductionBatch\ProductionBatchImportController;
use App\Http\Controllers\Api\Erp\ErpWebhookController;
use App\Http\Controllers\Api\Customers\CustomerController;
use App\Http\Controllers\Api\Customers\CustomerContactController;
use App\Http\Controllers\Api\Compliance\MarketComplianceRuleController;
use App\Http\Controllers\Api\TradeGoods\TradeGoodMarketComplianceController;
use App\Http\Controllers\Api\Settings\CarbonPriceController;
use App\Http\Controllers\Api\Settings\CountryRiskRatingController;
use App\Http\Controllers\Api\Dashboard\DashboardController;
use App\Http\Controllers\Api\Suppliers\SupplierFacilityController;
use App\Http\Controllers\Api\Suppliers\ActivityDataReportController;
use App\Http\Controllers\Api\Suppliers\SupplierDisclosureController;
use App\Http\Controllers\Api\Portal\PortalFacilityController;
use App\Http\Controllers\Api\Chemical\MaterialChemicalController;
use App\Http\Controllers\Api\ExportRiskMatrixController;
use App\Http\Controllers\Api\SupplierReplacementController;
use App\Http\Controllers\Api\Chemical\ChemicalRegistryController;
use App\Http\Controllers\Api\Chemical\ChemicalComplianceAlertController;
use App\Http\Controllers\Api\SalesProducts\SalesProductController;
use App\Http\Controllers\Api\SalesProducts\ProductPackagingController;
use App\Http\Controllers\Api\SalesProducts\ProductBatterySpecController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // 認證（不需要 JWT）
    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
    });

    // ERP Webhook（不使用 JWT，改用 HMAC / API Key 驗證）
    Route::post('erp/webhook/production-batches', [ErpWebhookController::class, 'productionBatch']);
    Route::post('erp/webhook/{entity}', [ErpWebhookController::class, 'receive'])->middleware('erp.hmac');

    // 批次護照對外 API（DPP / 出口合規系統對接；X-Api-Key 認證）
    Route::get('export/batch-package/{erpBatchNo}', [\App\Http\Controllers\Api\Export\BatchPackageController::class, 'show'])->middleware('export.apikey');

    // 內部回寫端點（Celery 呼叫，無 JWT）
    Route::patch('internal/activity-reports/{report}/push-result', function (\Illuminate\Http\Request $req, \App\Models\ActivityDataReport $report) {
        $report->update(['push_log' => $req->input('push_log')]);
        return response()->json(['success' => true]);
    });

    // Celery 地緣事件 E4 重算回調（無 JWT，內部呼叫）
    Route::post('risk/geo-events/{geoEvent}/review-callback', [GeoEventController::class, 'reviewCallback']);

    Route::post('internal/chemicals/sync', function (\Illuminate\Http\Request $req) {
        $chemicals = $req->input('chemicals', []);
        $count = 0;
        foreach ($chemicals as $c) {
            if (empty($c['cas_no'])) continue;
            \App\Models\Chemical::updateOrCreate(
                ['cas_no' => $c['cas_no']],
                array_filter([
                    'substance_name'    => $c['substance_name'] ?? null,
                    'iupac_name'        => $c['iupac_name'] ?? null,
                    'regulated_lists'   => $c['regulated_lists'] ?? null,
                    'restriction_notes' => $c['restriction_notes'] ?? null,
                    'svhc_date'         => $c['svhc_date'] ?? null,
                    'synced_at'         => $c['synced_at'] ?? now(),
                ], fn ($v) => $v !== null)
            );
            $count++;
        }
        return response()->json(['success' => true, 'upserted' => $count]);
    });

    // SAQ 計分回寫（AI Celery 呼叫，無 JWT）
    Route::post('internal/saqs/{saq}/score-callback', [\App\Http\Controllers\Api\SAQ\SAQController::class, 'scoreCallback']);
    Route::post('internal/saqs/{saq}/llm-score-callback', [\App\Http\Controllers\Api\SAQ\SAQController::class, 'llmScoreCallback']);

    // 需要 JWT 認證的路由
    Route::middleware(['auth:api', 'supplier.scope'])->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        // Supplier MDM
        // 需排在 apiResource 之前，否則會先被 GET suppliers/{supplier} 吃掉，
        // 誤把 network-graph 當成 supplier id 去查找
        Route::get('suppliers/network-graph', [\App\Http\Controllers\Api\Suppliers\SupplierNetworkController::class, 'graph']);
        Route::get('suppliers/ghg-coverage/by-group', [\App\Http\Controllers\Api\Suppliers\SupplierGhgCoverageController::class, 'byGroup']);
        Route::get('suppliers/ghg-coverage/trend', [\App\Http\Controllers\Api\Suppliers\SupplierGhgCoverageController::class, 'trend']);
        Route::get('suppliers/ghg-coverage', [\App\Http\Controllers\Api\Suppliers\SupplierGhgCoverageController::class, 'index']);
        // store/transition 刻意不開放：Supplier 是 ERP 主檔（透過 ErpSyncService 匯入/同步），
        // 不可由一般 API 手動建立；status 亦僅 ERP sync 可變更，只留 onboarding-transition 給 ESG-Chain 自有狀態機
        Route::apiResource('suppliers', SupplierController::class)->except(['store']);
        Route::post('suppliers/{supplier}/onboarding-transition', [SupplierController::class, 'transitionOnboarding']);
        Route::get('suppliers/{supplier}/risk-summary', [SupplierController::class, 'riskSummary']);
        Route::get('suppliers/{supplier}/risk-timeline', [SupplierController::class, 'timeline']);
        Route::post('suppliers/{supplier}/contacts', [SupplierContactController::class, 'store']);
        Route::put('suppliers/{supplier}/contacts/{contact}', [SupplierContactController::class, 'update']);
        Route::delete('suppliers/{supplier}/contacts/{contact}', [SupplierContactController::class, 'destroy']);
        Route::put('suppliers/{supplier}/profile', [SupplierProfileController::class, 'update']);
        Route::get('suppliers/{supplier}/disclosure-profile', [SupplierDisclosureController::class, 'profile']);
        Route::post('suppliers/{supplier}/risk-axis3', [SupplierController::class, 'updateAxis3Risk']);
        Route::post('suppliers/{supplier}/risk-suggestion', [AiRiskSuggestionController::class, 'generate']);
        // Supplier Import (CSV Batch)
        Route::post('suppliers/import-avl', [SupplierImportController::class, 'importAvl']);
        Route::post('suppliers/import', [SupplierImportController::class, 'upload']);
        Route::get('suppliers/import/{batchId}/status', [SupplierImportController::class, 'status']);
        Route::get('suppliers/import/{batchId}/items', [SupplierImportController::class, 'list']);
        Route::put('suppliers/import/{batchId}/items/{import}', [SupplierImportController::class, 'update']);
        Route::post('suppliers/import/{batchId}/items/{import}/exempt', [SupplierImportController::class, 'exempt']);
        Route::post('suppliers/import/{batchId}/approve', [SupplierImportController::class, 'approve']);

        // M2 Questionnaires（spec v2.1.0）
        Route::get('questionnaires/counts', [QuestionnaireController::class, 'counts']);
        Route::post('questionnaires/recommend-templates', RecommendTemplatesController::class);
        Route::get('questionnaires', [QuestionnaireController::class, 'index']);
        Route::post('questionnaires/send', [QuestionnaireController::class, 'send']);
        Route::get('questionnaires/{questionnaire}', [QuestionnaireController::class, 'show']);
        Route::put('questionnaires/{questionnaire}', [QuestionnaireController::class, 'update']);
        Route::post('questionnaires/{questionnaire}/submit', [QuestionnaireController::class, 'submit']);
        Route::post('questionnaires/{questionnaire}/start-review', [QuestionnaireController::class, 'startReview']);
        Route::post('questionnaires/{questionnaire}/complete-review', [QuestionnaireController::class, 'completeReview']);
        Route::post('questionnaires/{questionnaire}/return-review', [QuestionnaireController::class, 'returnReview']);
        Route::post('questionnaires/{questionnaire}/mark-reviewed', [QuestionnaireController::class, 'markReviewed']);
        Route::post('questionnaires/{questionnaire}/dispute', [QuestionnaireController::class, 'dispute']);

        // /saqs 審核路由（統一命名空間，寫入動作依 CLAUDE.md RBAC 表排除 buyer——buyer 無 saq 權限）
        Route::post('saqs/{saq}/start-review', [SAQController::class, 'startReview'])->middleware('role.any:admin,sustain,comply,analyst');
        Route::post('saqs/{saq}/complete-review', [SAQController::class, 'completeReview'])->middleware('role.any:admin,sustain,comply,analyst');
        Route::post('saqs/{saq}/return-review', [SAQController::class, 'returnReview'])->middleware('role.any:admin,sustain,comply,analyst');
        Route::post('saqs/{saq}/mark-reviewed', [SAQController::class, 'markReviewed'])->middleware('role.any:admin,sustain,comply,analyst');
        // score-callback 為 esgchain-ai 計分完成後的伺服器對伺服器回呼，不加使用者角色檢查
        Route::post('saqs/{saq}/score-callback', [SAQController::class, 'scoreCallback']);

        // saq-scoring-v2：題目層覆核 & 計分快照 & 申訴流程（寫入動作排除 buyer）
        Route::post('saqs/{saq}/response-reviews', [SAQController::class, 'submitResponseReviews'])->middleware('role.any:admin,sustain,comply,analyst');
        Route::get('saqs/{saq}/response-reviews', [SAQController::class, 'getResponseReviews']);
        Route::get('saqs/{saq}/score-snapshots', [SAQController::class, 'getScoreSnapshots']);
        Route::post('saqs/{saq}/re-review', [SAQController::class, 'startReReview'])->middleware('role.any:admin,sustain,comply,analyst');
        Route::post('saqs/{saq}/finalize', [SAQController::class, 'finalize'])->middleware('role.any:admin,sustain,comply,analyst');
        Route::get('saqs/{saq}/prefill-hints', [SAQController::class, 'prefillHints']);

        // M3 Risk（spec v2.1.0）
        Route::get('risk/matrix', [RiskMatrixController::class, 'matrix']);
        Route::get('risk/matrix/suppliers', [RiskMatrixController::class, 'matrixSuppliers']);
        Route::get('risk/assessments', [RiskAssessmentController::class, 'index']);
        Route::post('risk/assessments', [RiskAssessmentController::class, 'store']);
        Route::patch('risk/assessments/{riskAssessment}', [RiskAssessmentController::class, 'update']);

        // 六維熱圖
        Route::get('risk/six-dim-heatmap', [RiskHeatmapController::class, 'index']);

        // 地緣事件複查
        Route::get('risk/geo-events', [GeoEventController::class, 'index']);
        Route::post('risk/geo-events', [GeoEventController::class, 'store']);
        Route::get('risk/geo-events/{geoEvent}', [GeoEventController::class, 'show']);
        Route::get('risk/geo-events/{geoEvent}/reviews', [GeoEventController::class, 'reviews']);
        Route::post('risk/geo-events/{geoEvent}/recalculate', [GeoEventController::class, 'recalculate']);

        // M9 Settings — Organization Units（寫入動作限 admin）
        Route::get('settings/org-units', [OrganizationUnitController::class, 'index']);
        Route::get('settings/org-units/tree', [OrganizationUnitController::class, 'tree']);
        Route::post('settings/org-units', [OrganizationUnitController::class, 'store'])->middleware('role.admin');
        Route::put('settings/org-units/{unit}', [OrganizationUnitController::class, 'update'])->middleware('role.admin');
        Route::delete('settings/org-units/{unit}', [OrganizationUnitController::class, 'destroy'])->middleware('role.admin');
        Route::get('settings/questionnaire-templates', [QuestionnaireTemplateController::class, 'index']);
        Route::post('settings/questionnaire-templates', [QuestionnaireTemplateController::class, 'store'])->middleware('role.admin');
        Route::get('settings/questionnaire-templates/{template}', [QuestionnaireTemplateController::class, 'show']);
        Route::put('settings/questionnaire-templates/{template}', [QuestionnaireTemplateController::class, 'update'])->middleware('role.admin');
        Route::delete('settings/questionnaire-templates/{template}', [QuestionnaireTemplateController::class, 'destroy'])->middleware('role.admin');
        Route::post('settings/questionnaire-templates/{template}/clone', [QuestionnaireTemplateController::class, 'clone'])->middleware('role.admin');
        Route::post('settings/questionnaire-templates/{template}/publish', [QuestionnaireTemplateController::class, 'publish'])->middleware('role.admin');
        Route::post('settings/questionnaire-templates/{template}/archive', [QuestionnaireTemplateController::class, 'archive'])->middleware('role.admin');
        Route::post('settings/questionnaire-templates/{template}/unarchive', [QuestionnaireTemplateController::class, 'unarchive'])->middleware('role.admin');
        // 題目 CRUD（巢狀在範本下，寫入動作限 admin）
        Route::get('settings/questionnaire-templates/{template}/questions', [SAQQuestionController::class, 'index']);
        Route::post('settings/questionnaire-templates/{template}/questions', [SAQQuestionController::class, 'store'])->middleware('role.admin');
        Route::put('settings/questionnaire-templates/{template}/questions/{question}', [SAQQuestionController::class, 'update'])->middleware('role.admin');
        Route::delete('settings/questionnaire-templates/{template}/questions/{question}', [SAQQuestionController::class, 'destroy'])->middleware('role.admin');
        Route::post('settings/questionnaire-templates/{template}/import-from-bank', ImportFromBankController::class)->middleware('role.admin');
        Route::patch('settings/questionnaire-templates/{template}/questions/reorder', [QuestionnaireTemplateController::class, 'reorder'])->middleware('role.admin');
        Route::get('settings/supplier-groups', [SupplierGroupController::class, 'index']);
        Route::post('settings/supplier-groups', [SupplierGroupController::class, 'store'])->middleware('role.admin');
        Route::put('settings/supplier-groups/{group}', [SupplierGroupController::class, 'update'])->middleware('role.admin');
        Route::delete('settings/supplier-groups/{group}', [SupplierGroupController::class, 'destroy'])->middleware('role.admin');
        Route::get('supplier-groups', [SupplierGroupController::class, 'index']);
        Route::get('supplier-groups/{group}/inferred-material-groups', [SupplierGroupController::class, 'inferredMaterialGroups']);
        Route::get('settings/sasb-industries', [SasbIndustryController::class, 'index']);
        Route::get('settings/sasb-topics', [SasbDisclosureTopicController::class, 'index']);
        Route::get('settings/question-bank', [QuestionBankController::class, 'index']);
        Route::post('settings/question-bank', [QuestionBankController::class, 'store'])->middleware('role.admin');
        Route::put('settings/question-bank/{question}', [QuestionBankController::class, 'update'])->middleware('role.admin');
        Route::delete('settings/question-bank/{question}', [QuestionBankController::class, 'destroy'])->middleware('role.admin');
        // 題目 tag assignments（巢狀，寫入動作限 admin）
        Route::get('settings/question-bank/{question}/tags', [TagLibraryController::class, 'questionTags']);
        Route::post('settings/question-bank/{question}/tags', [TagLibraryController::class, 'addQuestionTag'])->middleware('role.admin');
        Route::delete('settings/question-bank/{question}/tags/{tag}', [TagLibraryController::class, 'removeQuestionTag'])->middleware('role.admin');
        // 標籤庫管理（寫入動作限 admin）
        Route::get('settings/dim-weight-defaults', [DimWeightDefaultsController::class, 'show']);
        Route::put('settings/dim-weight-defaults', [DimWeightDefaultsController::class, 'update'])->middleware('role.admin');
        Route::get('settings/tag-library/l2-nodes', [QuestionTagWeightController::class, 'index']);
        Route::put('settings/tag-library/l2-nodes/{tag}/weight', [QuestionTagWeightController::class, 'updateWeight'])->middleware('role.admin');
        Route::get('settings/tag-library/tree', [TagLibraryController::class, 'tree']);
        Route::get('settings/tag-library', [TagLibraryController::class, 'index']);
        Route::post('settings/tag-library', [TagLibraryController::class, 'store'])->middleware('role.admin');
        Route::put('settings/tag-library/{tag}', [TagLibraryController::class, 'update'])->middleware('role.admin');
        Route::post('settings/tag-library/{tag}/deprecate', [TagLibraryController::class, 'deprecate'])->middleware('role.admin');
        Route::post('settings/tag-library/{tag}/restore', [TagLibraryController::class, 'restore'])->middleware('role.admin');
        // SAQ 問卷專案（含 domain）
        // Assessment Series
        // assessment-series 寫入動作排除 buyer（buyer 無 saq 權限）
        Route::get('assessment-series', [AssessmentSeriesController::class, 'index']);
        Route::post('assessment-series', [AssessmentSeriesController::class, 'store'])->middleware('role.any:admin,sustain,comply,analyst');
        Route::get('assessment-series/{series}', [AssessmentSeriesController::class, 'show']);
        Route::put('assessment-series/{series}', [AssessmentSeriesController::class, 'update'])->middleware('role.any:admin,sustain,comply,analyst');
        Route::post('assessment-series/{series}/archive', [AssessmentSeriesController::class, 'archive'])->middleware('role.any:admin,sustain,comply,analyst');
        Route::get('assessment-series/{series}/weights', [AssessmentSeriesWeightController::class, 'index']);
        Route::put('assessment-series/{series}/weights', [AssessmentSeriesWeightController::class, 'update'])->middleware('role.any:admin,sustain,comply,analyst');
        Route::get('assessment-series/{series}/projects', [AssessmentSeriesController::class, 'getProjects']);
        Route::get('assessment-series/{series}/comparison', [AssessmentSeriesComparisonController::class, 'show']);
        Route::get('assessment-series/{series}/scoring-config', [AssessmentSeriesController::class, 'scoringConfig']);
        Route::put('assessment-series/{series}/scoring-config', [AssessmentSeriesController::class, 'updateScoringConfig'])->middleware('role.any:admin,sustain,comply,analyst');

        // saq-projects 寫入動作排除 buyer（buyer 無 saq 權限）
        Route::get('saq-projects', [SaqProjectController::class, 'index']);
        Route::post('saq-projects', [SaqProjectController::class, 'store'])->middleware('role.any:admin,sustain,comply,analyst');
        Route::get('saq-projects/{project}', [SaqProjectController::class, 'show']);
        Route::put('saq-projects/{project}', [SaqProjectController::class, 'update'])->middleware('role.any:admin,sustain,comply,analyst');
        Route::delete('saq-projects/{project}', [SaqProjectController::class, 'destroy'])->middleware('role.any:admin,sustain,comply,analyst');
        Route::post('saq-projects/{project}/send', [SaqProjectController::class, 'send'])->middleware('role.any:admin,sustain,comply,analyst');
        Route::post('saq-projects/{project}/close', [SaqProjectController::class, 'close'])->middleware('role.any:admin,sustain,comply,analyst');
        Route::get('saq-projects/{project}/questions', [SaqProjectController::class, 'questions']);
        Route::patch('saq-projects/{project}/question-weights', [SaqProjectController::class, 'updateWeights'])->middleware('role.any:admin,sustain,comply,analyst');
        Route::get('saq-projects/{project}/saqs', [SAQController::class, 'indexByProject']);
        Route::post('saq-projects/{project}/saqs/send', [SAQController::class, 'send'])->middleware('role.any:admin,sustain,comply,analyst');
        Route::get('settings/scoring-models', [ScoringModelProxyController::class, 'index']);
        Route::post('settings/scoring-models', [ScoringModelProxyController::class, 'store'])->middleware('role.admin');
        Route::put('settings/scoring-models/{id}', [ScoringModelProxyController::class, 'update'])->middleware('role.admin');
        Route::delete('settings/scoring-models/{id}', [ScoringModelProxyController::class, 'destroy'])->middleware('role.admin');
        Route::get('settings/sasb-required-topics', [SasbRequiredTopicController::class, 'index']);
        Route::post('settings/sasb-required-topics', [SasbRequiredTopicController::class, 'store']);
        Route::delete('settings/sasb-required-topics/{id}', [SasbRequiredTopicController::class, 'destroy']);

        // M7 Reports（spec v2.1.0）
        Route::get('reports/scope3', [ReportController::class, 'scope3']);
        Route::get('reports/scope3/export', [ReportController::class, 'exportScope3']);

        // 範疇三類別一物料維度彙總
        Route::get('scope3/category1/material-rollup', [Scope3Category1Controller::class, 'materialRollup']);
        Route::get('scope3/category1/material-rollup/{materialItemId}/drill', [Scope3Category1Controller::class, 'drill']);

        // CAP 矯正行動計畫
        // caps 寫入動作依 CLAUDE.md RBAC 表僅 admin/buyer/sustain/comply 可用，analyst 無 cap 權限
        Route::apiResource('caps', CAPController::class)
            ->middlewareFor(['store', 'update', 'destroy'], 'role.any:admin,buyer,sustain,comply');
        Route::post('caps/{cap}/attachments', [\App\Http\Controllers\Api\CAP\CAPAttachmentController::class, 'store'])
            ->middleware('role.any:admin,buyer,sustain,comply');
        Route::get('cap-attachments/{attachment}/download', [\App\Http\Controllers\Api\CAP\CAPAttachmentController::class, 'download']);
        Route::delete('cap-attachments/{attachment}', [\App\Http\Controllers\Api\CAP\CAPAttachmentController::class, 'destroy']);

        // Customer MDM
        Route::apiResource('customers', CustomerController::class);
        Route::post('customers/{customer}/contacts', [CustomerContactController::class, 'store']);
        Route::delete('customers/{customer}/contacts/{contact}', [CustomerContactController::class, 'destroy']);

        // Trade Goods + CBAM
        Route::get('trade-goods/export-risk-matrix', [ExportRiskMatrixController::class, 'index']);
        Route::post('supplier-replacement/candidates', [SupplierReplacementController::class, 'candidates']);
        Route::post('trade-goods/market-compliance-batch', [TradeGoodMarketComplianceController::class, 'batch']);
        Route::get('trade-goods/{tradeGood}/compliance-gap', [TradeGoodMarketComplianceController::class, 'gap']);
        Route::get('trade-goods/cbam-summary', [TradeGoodController::class, 'cbamSummary']);
        Route::get('trade-goods/search', [TradeGoodController::class, 'search']);
        Route::apiResource('trade-goods', TradeGoodController::class);
        Route::get('trade-goods/{tradeGood}/suppliers', [TradeGoodController::class, 'suppliers']);
        Route::post('trade-goods/{tradeGood}/suppliers', [TradeGoodController::class, 'addSupplier']);
        Route::delete('trade-goods/{tradeGood}/suppliers/{tradeGoodSupplier}', [TradeGoodController::class, 'removeSupplier']);
        Route::get('trade-goods/{tradeGood}/emission-reports', [TradeGoodController::class, 'emissionReports']);
        Route::post('trade-goods/{tradeGood}/emission-reports/{emission}/confirm', [TradeGoodController::class, 'confirmEmission']);

        // 銷售產品（SalesProduct）— 新路由，取代 trade-goods + buyer-products
        Route::get('sales-products/cbam-summary', [SalesProductController::class, 'cbamSummary']);
        Route::get('sales-products/search', [SalesProductController::class, 'search']);
        Route::post('sales-products/import', [BuyerProductImportController::class, 'store']);
        Route::apiResource('sales-products', SalesProductController::class);
        Route::get('sales-products/{salesProduct}/packaging', [ProductPackagingController::class, 'show']);
        Route::put('sales-products/{salesProduct}/packaging', [ProductPackagingController::class, 'upsert']);
        Route::get('sales-products/{salesProduct}/battery-spec', [ProductBatterySpecController::class, 'show']);
        Route::put('sales-products/{salesProduct}/battery-spec', [ProductBatterySpecController::class, 'upsert']);
        Route::get('sales-products/{salesProduct}/suppliers', [SalesProductController::class, 'suppliers']);
        Route::post('sales-products/{salesProduct}/suppliers', [SalesProductController::class, 'addSupplier']);
        Route::delete('sales-products/{salesProduct}/suppliers/{tradeGoodSupplier}', [SalesProductController::class, 'removeSupplier']);
        Route::get('sales-products/{salesProduct}/emission-reports', [SalesProductController::class, 'emissionReports']);
        Route::post('sales-products/{salesProduct}/emission-reports/{emission}/confirm', [SalesProductController::class, 'confirmEmission']);
        Route::get('sales-products/{salesProduct}/bom-lines', [ProductBomLineController::class, 'index']);
        Route::post('sales-products/{salesProduct}/bom-lines', [ProductBomLineController::class, 'store']);
        Route::patch('sales-products/{salesProduct}/bom-lines/{bomLine}', [ProductBomLineController::class, 'update']);
        Route::delete('sales-products/{salesProduct}/bom-lines/{bomLine}', [ProductBomLineController::class, 'destroy']);
        Route::post('sales-products/{salesProduct}/bom-lines/import', [ProductBomLineController::class, 'import']);
        Route::post('sales-products/{salesProduct}/bom-lines/{bomLine}/request-emission', [ProductBomLineController::class, 'requestEmission']);
        Route::post('sales-products/{salesProduct}/bom-lines/{bomLine}/suppliers', [BomLineSupplierController::class, 'store']);
        Route::delete('sales-products/{salesProduct}/bom-lines/{bomLine}/suppliers/{bomLineSupplier}', [BomLineSupplierController::class, 'destroy']);
        Route::patch('sales-products/{salesProduct}/bom-lines/{bomLine}/suppliers/{bomLineSupplier}/role', [BomLineSupplierController::class, 'setRole']);
        Route::get('sales-products/{salesProductId}/pcf-latest', [PcfSnapshotController::class, 'latest']);
        Route::post('sales-products/{salesProductId}/pcf-recalc', [PcfRecalcController::class, 'recalc']);
        Route::get('sales-products/{salesProductId}/circularity-latest', [\App\Http\Controllers\Api\Compliance\ProductCircularityController::class, 'latest']);
        Route::post('sales-products/{salesProductId}/circularity-recalc', [\App\Http\Controllers\Api\Compliance\ProductCircularityController::class, 'recalc']);

        // Material Compliance
        Route::get('material-groups', [MaterialGroupController::class, 'index']);
        Route::post('material-groups', [MaterialGroupController::class, 'store']);
        Route::put('material-groups/{materialGroup}', [MaterialGroupController::class, 'update']);
        Route::delete('material-groups/{materialGroup}', [MaterialGroupController::class, 'destroy']);

        // Material Items（料號主檔）
        Route::get('material-items', [MaterialItemController::class, 'index']);
        Route::get('material-items/{materialItem}', [MaterialItemController::class, 'show']);
        Route::post('material-items', [MaterialItemController::class, 'store']);
        Route::post('material-items/import', [MaterialItemController::class, 'import']);
        Route::put('material-items/{materialItem}', [MaterialItemController::class, 'update']);
        Route::delete('material-items/{materialItem}', [MaterialItemController::class, 'destroy']);
        Route::get('material-items/{materialItem}/bom-suppliers', [MaterialItemController::class, 'bomSuppliers']);
        Route::post('material-items/{materialItem}/bom-lines/{bomLine}/switch-primary-supplier', [MaterialItemController::class, 'switchPrimarySupplier']);
        // 物料層級核可供應商清單（主/備），取代逐產品重複登記
        Route::get('material-items/{materialItem}/suppliers', [MaterialItemSupplierController::class, 'index']);
        Route::post('material-items/{materialItem}/suppliers', [MaterialItemSupplierController::class, 'store']);
        Route::patch('material-items/{materialItem}/suppliers/{approvedSupplier}/role', [MaterialItemSupplierController::class, 'setRole']);
        Route::delete('material-items/{materialItem}/suppliers/{approvedSupplier}', [MaterialItemSupplierController::class, 'destroy']);
        Route::post('material-items/{materialItem}/suppliers/apply-to-bom-line', [MaterialItemSupplierController::class, 'applyToBomLine']);

        // Market Definitions（目標市場定義，寫入動作限 admin）
        Route::apiResource('market-compliance-rules', MarketComplianceRuleController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->middlewareFor(['store', 'update', 'destroy'], 'role.admin');

        // Dashboard
        Route::prefix('dashboard')->group(function () {
            Route::get('summary', [DashboardController::class, 'summary']);
            Route::get('recent-activity', [DashboardController::class, 'recentActivity']);
            Route::get('expiring-docs', [DashboardController::class, 'expiringDocs']);
            Route::get('compliance-risk', [DashboardController::class, 'complianceRisk']);
            Route::get('esg-scores', [DashboardController::class, 'esgScores']);
        });

        // System Settings — Carbon Price
        Route::get('settings/carbon-price', [CarbonPriceController::class, 'show']);
        Route::put('settings/carbon-price', [CarbonPriceController::class, 'update']);

        // 國家風險評等（admin / sustain，Controller 內驗證角色）
        Route::get('settings/country-risk-ratings', [CountryRiskRatingController::class, 'index']);
        Route::post('settings/country-risk-ratings', [CountryRiskRatingController::class, 'store']);
        Route::patch('settings/country-risk-ratings/{countryRiskRating}', [CountryRiskRatingController::class, 'update']);

        Route::get('market-definitions', [MarketDefinitionController::class, 'index']);
        Route::post('market-definitions', [MarketDefinitionController::class, 'store'])->middleware('role.admin');
        Route::put('market-definitions/{marketDefinition}', [MarketDefinitionController::class, 'update'])->middleware('role.admin');
        Route::delete('market-definitions/{marketDefinition}', [MarketDefinitionController::class, 'destroy'])->middleware('role.admin');

        // Production Batches
        Route::get('production-batches', [ProductionBatchController::class, 'index']);
        Route::get('production-batches/{id}', [ProductionBatchController::class, 'show']);
        Route::put('production-batches/{id}', [ProductionBatchController::class, 'update']);
        Route::delete('production-batches/{id}', [ProductionBatchController::class, 'destroy']);
        Route::post('production-batches/{id}/origins', [RawMaterialOriginController::class, 'store']);
        Route::put('production-batches/{batchId}/origins/{id}', [RawMaterialOriginController::class, 'update']);
        Route::delete('production-batches/{batchId}/origins/{id}', [RawMaterialOriginController::class, 'destroy']);
        // 批號×市場出口合規審查
        Route::get('production-batches/{batchId}/export-reviews', [\App\Http\Controllers\Api\ProductionBatch\BatchExportReviewController::class, 'index']);
        Route::post('production-batches/{batchId}/export-reviews', [\App\Http\Controllers\Api\ProductionBatch\BatchExportReviewController::class, 'store']);
        Route::delete('production-batches/{batchId}/export-reviews/{reviewId}', [\App\Http\Controllers\Api\ProductionBatch\BatchExportReviewController::class, 'destroy']);
        Route::get('production-batches/{batchId}/dds-draft', [\App\Http\Controllers\Api\ProductionBatch\BatchExportReviewController::class, 'ddsDraft']);
        Route::get('production-batches/{batchId}/gate-check', [\App\Http\Controllers\Api\ProductionBatch\BatchExportReviewController::class, 'gateCheck']);
        Route::get('production-batches/{batchId}/passport', [\App\Http\Controllers\Api\ProductionBatch\BatchExportReviewController::class, 'passport']);
        // 批次×製程類型→實際供應商/廠區選定
        Route::get('production-batches/{batchId}/process-facilities', [\App\Http\Controllers\Api\ProductionBatch\BatchProcessFacilityController::class, 'index']);
        Route::post('production-batches/{batchId}/process-facilities', [\App\Http\Controllers\Api\ProductionBatch\BatchProcessFacilityController::class, 'store']);
        Route::delete('production-batches/{batchId}/process-facilities/{id}', [\App\Http\Controllers\Api\ProductionBatch\BatchProcessFacilityController::class, 'destroy']);
        Route::get('production-batches/{batchId}/process-due-diligence', [\App\Http\Controllers\Api\ProductionBatch\BatchProcessFacilityController::class, 'dueDiligence']);
        // 跨批號出口審查清單（含「未審查」批號）
        Route::get('export-reviews', [\App\Http\Controllers\Api\ProductionBatch\ExportReviewQueueController::class, 'index']);
        Route::post('erp/import/production-batches', [ProductionBatchImportController::class, 'store']);

        Route::get('suppliers/{supplier}/compliance-docs', [SupplierComplianceDocController::class, 'index']);
        Route::post('suppliers/{supplier}/compliance-docs', [SupplierComplianceDocController::class, 'store']);
        Route::get('suppliers/{supplier}/compliance-docs/summary', [ComplianceDashboardController::class, 'supplierSummary']);
        Route::delete('compliance-docs/{complianceDoc}', [SupplierComplianceDocController::class, 'destroy']);
        Route::get('compliance-docs/{complianceDoc}/download', [SupplierComplianceDocController::class, 'download']);
        Route::post('compliance-docs/{complianceDoc}/verify', [SupplierComplianceDocController::class, 'verify']);
        Route::delete('compliance-docs/{complianceDoc}/verify', [SupplierComplianceDocController::class, 'unverify']);

        Route::get('compliance/dashboard', [ComplianceDashboardController::class, 'supplierDashboard']);
        Route::get('compliance/product-dashboard', [ComplianceDashboardController::class, 'productDashboard']);
        Route::get('compliance/pending-verifications', [ComplianceDashboardController::class, 'pendingVerifications']);
        Route::get('compliance/matrix', [ComplianceDashboardController::class, 'matrixData']);
        Route::get('compliance/matrix/drill', [ComplianceDashboardController::class, 'matrixDrill']);
        Route::get('compliance/dpp-readiness', [ComplianceDashboardController::class, 'dppReadiness']);
        Route::get('compliance/dpp-readiness/{salesProduct}', [ComplianceDashboardController::class, 'dppReadinessDetail']);
        Route::post('sales-products/{salesProduct}/sync-regulations', [ComplianceDashboardController::class, 'syncProductRegulations']);
        Route::get('suppliers/{supplier}/bom-requirements', [ComplianceDashboardController::class, 'supplierBomRequirements']);

        // Material Emissions（物料碳排 MDM）
        Route::get('material-items/{materialItemId}/emissions', [MaterialEmissionController::class, 'index']);
        Route::post('material-items/{materialItemId}/emissions', [MaterialEmissionController::class, 'store']);
        Route::post('material-emissions/{emissionId}/flag', [MaterialEmissionController::class, 'flag']);
        Route::post('material-emissions/{emissionId}/unflag', [MaterialEmissionController::class, 'unflag']);

        // PCF Requests（碳排請求管理）
        Route::get('pcf-requests', [PcfRequestController::class, 'index']);
        Route::post('pcf-requests/batch', [PcfRequestController::class, 'batchCreate']);

        // PCF Snapshots
        Route::get('products/{buyerProductId}/pcf-latest', [PcfSnapshotController::class, 'latest']);
        Route::get('pcf-snapshots/{snapshotId}', [PcfSnapshotController::class, 'show']);
        Route::post('products/{buyerProductId}/pcf-recalc', [PcfRecalcController::class, 'recalc']);

        // Portal（供應商入口）
        Route::get('portal/procurement-requirements', [PortalController::class, 'procurementRequirements']);
        Route::get('portal/trade-goods', [PortalTradeGoodController::class, 'index']);
        Route::post('portal/trade-goods/{tradeGood}/emissions', [PortalTradeGoodController::class, 'reportEmission']);
        Route::get('portal/material-emissions', [PortalMaterialEmissionController::class, 'index']);
        Route::post('portal/material-emissions', [PortalMaterialEmissionController::class, 'store']);
        Route::get('portal/material-items', [PortalMaterialEmissionController::class, 'searchMaterials']);

        // Portal PCF 碳排申報
        Route::get('portal/pcf-request-lines', [PortalPcfController::class, 'requestLines']);
        Route::get('portal/pcf-requests', [PortalPcfController::class, 'index']);
        Route::put('portal/pcf-requests/{pcfRequestId}/lines/{lineId}', [PortalPcfController::class, 'updateLine']);
        Route::post('portal/pcf-requests/{pcfRequestId}/submit', [PortalPcfController::class, 'submit']);

        // 活動資料層 - Portal
        Route::get('portal/facilities', [PortalFacilityController::class, 'index']);
        Route::post('portal/facilities/{facility}/activity-reports', [PortalFacilityController::class, 'storeReport']);
        Route::post('portal/facilities/{facility}/activity-reports/{report}/submit', [PortalFacilityController::class, 'submitReport']);

        // 永續 KPI 填報 - Portal
        Route::get('portal/disclosures', [\App\Http\Controllers\Api\Portal\PortalDisclosureController::class, 'index']);
        Route::post('portal/disclosures', [\App\Http\Controllers\Api\Portal\PortalDisclosureController::class, 'store']);

        // 矯正行動（CAP）- Portal
        Route::get('portal/caps', [\App\Http\Controllers\Api\Portal\PortalCapController::class, 'index']);
        Route::get('portal/caps/{cap}', [\App\Http\Controllers\Api\Portal\PortalCapController::class, 'show']);
        Route::post('portal/caps/{cap}/update', [\App\Http\Controllers\Api\Portal\PortalCapController::class, 'addUpdate']);

        // 站內通知 - Portal
        Route::get('portal/notifications/unread-count', [\App\Http\Controllers\Api\Portal\PortalNotificationController::class, 'unreadCount']);
        Route::post('portal/notifications/mark-read', [\App\Http\Controllers\Api\Portal\PortalNotificationController::class, 'markRead']);


        // 活動資料層 - 中心廠端跨供應商彙總儀表板
        Route::get('activity-reports/summary', [\App\Http\Controllers\Api\Suppliers\ActivityReportDashboardController::class, 'summary']);
        Route::get('activity-reports', [\App\Http\Controllers\Api\Suppliers\ActivityReportDashboardController::class, 'index']);
        Route::post('activity-reports/{report}/verify', [\App\Http\Controllers\Api\Suppliers\ActivityReportDashboardController::class, 'verify']);
        Route::post('activity-reports/{report}/push', [\App\Http\Controllers\Api\Suppliers\ActivityReportDashboardController::class, 'push']);

        // 活動資料層 - 買方端
        Route::get('suppliers/{supplier}/facilities', [SupplierFacilityController::class, 'index']);
        Route::post('suppliers/{supplier}/facilities', [SupplierFacilityController::class, 'store']);
        Route::patch('suppliers/{supplier}/facilities/{facility}', [SupplierFacilityController::class, 'update']);
        Route::get('suppliers/{supplier}/activity-reports', [ActivityDataReportController::class, 'index']);
        Route::post('suppliers/{supplier}/activity-reports/{report}/verify', [ActivityDataReportController::class, 'verify']);
        Route::post('suppliers/{supplier}/activity-reports/{report}/push', [ActivityDataReportController::class, 'push']);

        // 化學合規 - 物料化學組成
        Route::get('material-items/{materialItemId}/chemicals', [MaterialChemicalController::class, 'index']);
        Route::post('material-items/{materialItemId}/chemicals', [MaterialChemicalController::class, 'store']);
        Route::delete('material-items/{materialItemId}/chemicals/{chemical}', [MaterialChemicalController::class, 'destroy']);

        // 化學合規 - 化學品登錄查詢
        Route::get('chemicals/lookup', [ChemicalRegistryController::class, 'lookup']);
        Route::get('chemicals/search', [ChemicalRegistryController::class, 'search']);

        // 化學合規 - Alert 管理
        Route::get('chemical-compliance-alerts', [ChemicalComplianceAlertController::class, 'index']);
        Route::post('chemical-compliance-alerts/{alert}/acknowledge', [ChemicalComplianceAlertController::class, 'acknowledge']);
        Route::post('material-items/{materialItemId}/chemical-compliance-scan', [ChemicalComplianceAlertController::class, 'scan']);
    });
});
