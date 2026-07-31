import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/auth/LoginView.vue'),
      meta: { requiresAuth: false },
    },
    {
      path: '/',
      redirect: '/dashboard',
      meta: { requiresAuth: true },
    },
    {
      path: '/dashboard',
      name: 'dashboard',
      component: () => import('@/views/dashboard/DashboardView.vue'),
      meta: { requiresAuth: true, roles: ['admin', 'buyer', 'sustain', 'comply', 'analyst'] },
    },
    {
      path: '/dashboard/activity',
      name: 'dashboard-activity',
      component: () => import('@/views/dashboard/SupplierActivityView.vue'),
      meta: { requiresAuth: true, roles: ['admin', 'buyer', 'sustain', 'comply', 'analyst'] },
    },
    {
      path: '/suppliers',
      name: 'suppliers',
      component: () => import('@/views/suppliers/SuppliersView.vue'),
      meta: { requiresAuth: true, roles: ['admin', 'buyer', 'sustain', 'comply', 'analyst'] },
    },
    {
      path: '/suppliers/network',
      name: 'supplier-network',
      component: () => import('@/views/suppliers/SupplierNetworkView.vue'),
      meta: { requiresAuth: true, roles: ['admin', 'buyer', 'sustain', 'comply', 'analyst'] },
    },
    {
      path: '/suppliers/import',
      name: 'supplier-import',
      component: () => import('@/views/suppliers/SupplierImportView.vue'),
      meta: { requiresAuth: true, roles: ['admin', 'buyer'] },
    },
    {
      path: '/suppliers/import/review',
      name: 'supplier-import-review',
      component: () => import('@/views/suppliers/SupplierImportReviewView.vue'),
      meta: { requiresAuth: true, roles: ['admin', 'buyer'] },
    },
    {
      path: '/suppliers/ghg-coverage',
      name: 'supplier-ghg-coverage',
      component: () => import('@/views/suppliers/SupplierGhgCoverageView.vue'),
      meta: { requiresAuth: true, roles: ['admin', 'sustain', 'comply'] },
    },
    {
      path: '/suppliers/:id',
      name: 'supplier-detail',
      component: () => import('@/views/suppliers/SupplierDetailView.vue'),
      meta: { requiresAuth: true, roles: ['admin', 'buyer', 'sustain', 'comply', 'analyst'] },
    },
    {
      path: '/questionnaires',
      redirect: '/questionnaires/review',
    },
    {
      path: '/questionnaires/send',
      redirect: '/questionnaires/projects',
    },
    {
      path: '/questionnaires/review',
      name: 'questionnaires-review',
      component: () => import('@/views/questionnaires/QuestionnaireView.vue'),
      meta: { requiresAuth: true, roles: ['admin', 'sustain', 'comply', 'analyst'] },
    },
    {
      path: '/questionnaires/review/:id',
      name: 'questionnaires-review-detail',
      component: () => import('@/views/questionnaires/ReviewDetailView.vue'),
      meta: { requiresAuth: true, roles: ['admin', 'sustain', 'comply', 'analyst'] },
    },
    {
      path: '/questionnaires/templates',
      name: 'questionnaires-templates',
      component: () => import('@/views/questionnaires/QuestionnaireTemplatesView.vue'),
      meta: { requiresAuth: true, roles: ['admin'] },
    },
    {
      path: '/questionnaires/projects',
      name: 'questionnaires-projects',
      component: () => import('@/views/questionnaires/SaqProjectsView.vue'),
      meta: { requiresAuth: true, roles: ['admin', 'sustain', 'comply', 'analyst'] },
    },
    {
      path: '/questionnaires/projects/:id',
      name: 'questionnaires-project-detail',
      component: () => import('@/views/questionnaires/SaqProjectDetailView.vue'),
      meta: { requiresAuth: true, roles: ['admin', 'sustain', 'comply', 'analyst'] },
    },
    {
      path: '/questionnaires/series',
      name: 'questionnaires-series',
      component: () => import('@/views/questionnaires/SeriesListView.vue'),
      meta: { requiresAuth: true, roles: ['admin', 'sustain', 'comply', 'analyst'] },
    },
    {
      path: '/questionnaires/series/:id',
      name: 'questionnaires-series-detail',
      component: () => import('@/views/questionnaires/SeriesDetailView.vue'),
      meta: { requiresAuth: true, roles: ['admin', 'sustain', 'comply', 'analyst'] },
    },
    {
      path: '/cap',
      name: 'cap',
      component: () => import('@/views/cap/CAPView.vue'),
      meta: { requiresAuth: true, roles: ['admin', 'buyer', 'sustain', 'comply'] },
    },
    {
      path: '/risk',
      name: 'risk',
      component: () => import('@/views/risk/SixDimHeatmapView.vue'),
      meta: { requiresAuth: true, roles: ['admin', 'buyer', 'sustain', 'comply', 'analyst'] },
    },
    { path: '/risk/matrix', redirect: '/risk' },
    {
      path: '/risk/geo-events',
      name: 'risk-geo-events',
      component: () => import('@/views/risk/GeoEventView.vue'),
      meta: { requiresAuth: true, roles: ['admin', 'sustain'] },
    },
    {
      path: '/risk/geo-events/:id',
      name: 'risk-geo-event-detail',
      component: () => import('@/views/risk/GeoEventDetailView.vue'),
      meta: { requiresAuth: true, roles: ['admin', 'sustain'] },
    },
    {
      path: '/reports',
      name: 'reports',
      component: () => import('@/views/reports/ReportsView.vue'),
      meta: { requiresAuth: true, roles: ['admin', 'sustain', 'analyst'] },
    },
    {
      path: '/settings',
      name: 'settings',
      component: () => import('@/views/settings/SettingsView.vue'),
      meta: { requiresAuth: true, roles: ['admin'] },
    },
    {
      path: '/settings/users',
      name: 'settings-users',
      component: () => import('@/views/settings/UsersView.vue'),
      meta: { requiresAuth: true, roles: ['admin'] },
    },
    {
      path: '/settings/question-bank',
      name: 'question-bank',
      component: () => import('@/views/settings/QuestionBankView.vue'),
      meta: { requiresAuth: true, roles: ['admin'] },
    },
    {
      path: '/settings/scoring-models',
      name: 'scoring-models',
      component: () => import('@/views/settings/ScoringModelView.vue'),
      meta: { requiresAuth: true, roles: ['admin'] },
    },
    {
      path: '/settings/tag-library',
      name: 'tag-library',
      component: () => import('@/views/settings/TagLibraryView.vue'),
      meta: { requiresAuth: true, roles: ['admin'] },
    },
    {
      path: '/settings/classification-scoring',
      name: 'classification-scoring',
      component: () => import('@/views/settings/ClassificationScoringHubView.vue'),
      meta: { requiresAuth: true, roles: ['admin'] },
    },
    {
      path: '/settings/questionnaire-templates/:id',
      name: 'template-detail',
      component: () => import('@/views/settings/TemplateDetailView.vue'),
      meta: { requiresAuth: true, roles: ['admin'] },
    },
    {
      path: '/settings/material-settings',
      name: 'material-settings',
      redirect: '/materials/items',
    },
    {
      path: '/materials',
      redirect: '/materials/items',
    },
    {
      path: '/materials/items',
      name: 'material-items',
      component: () => import('@/views/settings/MaterialItemsView.vue'),
      meta: { requiresAuth: true, roles: ['admin', 'buyer', 'sustain', 'comply', 'analyst'] },
    },
    {
      path: '/materials/items/import',
      name: 'material-item-import',
      component: () => import('@/views/settings/MaterialItemImportView.vue'),
      meta: { requiresAuth: true, roles: ['admin', 'buyer', 'comply'] },
    },
    {
      path: '/materials/items/:id',
      name: 'material-item-detail',
      component: () => import('@/views/settings/MaterialItemDetailView.vue'),
      meta: { requiresAuth: true, roles: ['admin', 'buyer', 'sustain', 'comply', 'analyst'] },
    },
    {
      path: '/materials/groups',
      name: 'material-groups',
      component: () => import('@/views/settings/MaterialGroupsView.vue'),
      meta: { requiresAuth: true, roles: ['admin', 'buyer', 'sustain', 'comply'] },
    },
    {
      path: '/settings/customers',
      name: 'customers',
      component: () => import('@/views/settings/CustomerMdmView.vue'),
      meta: { requiresAuth: true, roles: ['admin'] },
    },
    {
      path: '/settings/market-rules',
      name: 'market-rules',
      component: () => import('@/views/settings/MarketComplianceRulesView.vue'),
      meta: { requiresAuth: true, roles: ['admin'] },
    },
    {
      path: '/settings/country-risk',
      name: 'country-risk-settings',
      component: () => import('@/views/settings/CountryRiskView.vue'),
      meta: { requiresAuth: true, roles: ['admin', 'sustain'] },
    },
    {
      path: '/trade-goods',
      name: 'trade-goods',
      component: () => import('@/views/trade-goods/TradeGoodsView.vue'),
      meta: { requiresAuth: true, roles: ['admin', 'buyer', 'sustain', 'comply'] },
    },
    {
      path: '/trade-goods/export-risk',
      name: 'export-risk-dashboard',
      component: () => import('@/views/trade-goods/ExportRiskDashboardView.vue'),
      meta: { requiresAuth: true, roles: ['admin', 'buyer', 'comply'] },
    },
    {
      path: '/sales-products',
      name: 'sales-products',
      component: () => import('@/views/sales-products/SalesProductsView.vue'),
      meta: { requiresAuth: true, roles: ['admin', 'buyer', 'sustain', 'comply'] },
    },
    {
      path: '/sales-products/import',
      name: 'sales-product-import',
      component: () => import('@/views/sales-products/SalesProductImportView.vue'),
      meta: { requiresAuth: true, roles: ['admin', 'buyer', 'comply'] },
    },
    {
      path: '/sales-products/:id',
      name: 'sales-product-detail',
      component: () => import('@/views/sales-products/SalesProductDetailView.vue'),
      meta: { requiresAuth: true, roles: ['admin', 'buyer', 'sustain', 'comply'] },
    },
    {
      path: '/compliance',
      name: 'compliance-dashboard',
      component: () => import('@/views/compliance/MaterialComplianceView.vue'),
      meta: { requiresAuth: true, roles: ['admin', 'buyer', 'sustain', 'comply'] },
    },
    {
      path: '/compliance/products',
      redirect: '/sales-products',
    },
    {
      path: '/compliance/production-batches',
      name: 'production-batches',
      component: () => import('@/views/compliance/ProductionBatchesView.vue'),
      meta: { requiresAuth: true, roles: ['admin', 'buyer', 'comply'] },
    },
    {
      path: '/compliance/production-batches/:id',
      name: 'production-batch-detail',
      component: () => import('@/views/compliance/ProductionBatchDetailView.vue'),
      meta: { requiresAuth: true, roles: ['admin', 'buyer', 'comply'] },
    },
    {
      path: '/compliance/export-reviews',
      name: 'export-reviews',
      component: () => import('@/views/compliance/ExportReviewsView.vue'),
      meta: { requiresAuth: true, roles: ['admin', 'buyer', 'comply'] },
    },
    {
      path: '/compliance/suppliers/:id',
      name: 'compliance-supplier-detail',
      component: () => import('@/views/compliance/SupplierComplianceDetailView.vue'),
      meta: { requiresAuth: true, roles: ['admin', 'buyer', 'sustain', 'comply'] },
    },
    {
      path: '/compliance/scope3-category1',
      name: 'scope3-category1',
      component: () => import('@/views/compliance/Scope3Category1View.vue'),
      meta: { requiresAuth: true, roles: ['admin', 'buyer', 'sustain', 'comply'] },
    },
    {
      path: '/materials/pcf-requests',
      name: 'pcf-requests',
      component: () => import('@/views/compliance/PcfRequestsView.vue'),
      meta: { requiresAuth: true, roles: ['admin', 'buyer', 'sustain'] },
    },
    {
      path: '/:pathMatch(.*)*',
      redirect: '/dashboard',
    },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login' }
  }

  if (to.meta.requiresAuth && auth.isAuthenticated && !auth.user) {
    try {
      await auth.fetchMe()
    } catch {
      auth.clearAuth()
      return { name: 'login' }
    }
  }

  if (to.meta.roles && auth.user) {
    const roles = to.meta.roles as string[]
    if (!roles.includes(auth.user.role)) {
      return { name: 'dashboard' }
    }
  }
})

export default router
