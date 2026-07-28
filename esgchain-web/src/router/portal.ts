import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/auth/PortalLoginView.vue'),
      meta: { requiresAuth: false },
    },
    {
      path: '/',
      redirect: '/supplier/portal',
      meta: { requiresAuth: true },
    },
    {
      path: '/supplier/profile',
      name: 'supplier-profile',
      component: () => import('@/views/portal/SupplierProfileView.vue'),
      meta: { requiresAuth: true, roles: ['supplier', 'sup_esg'] },
    },
    {
      path: '/supplier/portal',
      name: 'supplier-portal',
      component: () => import('@/views/portal/PortalView.vue'),
      meta: { requiresAuth: true, roles: ['supplier', 'sup_esg'] },
    },
    {
      path: '/supplier/survey/:id',
      name: 'supplier-survey',
      component: () => import('@/views/portal/SupplierSurveyView.vue'),
      meta: { requiresAuth: true, roles: ['supplier', 'sup_esg'] },
    },
    {
      path: '/supplier/compliance',
      name: 'supplier-compliance',
      component: () => import('@/views/portal/SupplierCompliancePortalView.vue'),
      meta: { requiresAuth: true, roles: ['supplier', 'sup_esg'] },
    },
    {
      path: '/supplier/portal/procurement',
      name: 'supplier-procurement',
      component: () => import('@/views/portal/PortalProcurementView.vue'),
      meta: { requiresAuth: true, roles: ['supplier', 'sup_esg'] },
    },
    {
      path: '/supplier/portal/pcf',
      name: 'portal-pcf',
      component: () => import('@/views/portal/PortalPcfView.vue'),
      meta: { requiresAuth: true, roles: ['supplier', 'sup_esg'] },
    },
    {
      path: '/supplier/portal/disclosures',
      name: 'portal-disclosures',
      component: () => import('@/views/portal/PortalDisclosureView.vue'),
      meta: { requiresAuth: true, roles: ['supplier', 'sup_esg'] },
    },
    {
      path: '/supplier/portal/caps',
      name: 'portal-caps',
      component: () => import('@/views/portal/PortalCapView.vue'),
      meta: { requiresAuth: true, roles: ['supplier', 'sup_esg'] },
    },
    {
      path: '/supplier/portal/activity-reports',
      name: 'portal-activity-reports',
      component: () => import('@/views/portal/PortalActivityReportsView.vue'),
      meta: { requiresAuth: true, roles: ['supplier', 'sup_esg'] },
    },
    // 任何不屬於供應商 Portal 的路徑（例如殘留在網址列的中心廠路徑 /dashboard）
    // 一律導回入口，交由上面 '/' 的 redirect 與 beforeEach 依登入狀態決定去處，
    // 不留空白頁
    {
      path: '/:pathMatch(.*)*',
      redirect: '/',
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
      // 非供應商角色的 token 混入供應商 build：登出並回登入頁
      auth.clearAuth()
      return { name: 'login' }
    }
  }
})

export default router
