<script lang="ts">
import { defineComponent, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import NotificationBell from '@/components/layout/NotificationBell.vue'
import { portalNotificationsApi } from '@/api/modules/portalNotifications'

const NAV_ITEMS = [
  { to: '/supplier/portal', label: '供應商入口' },
  { to: '/supplier/compliance', label: '合規文件' },
  { to: '/supplier/portal/procurement', label: '採購需求' },
  { to: '/supplier/portal/pcf', label: '碳排申報' },
  { to: '/supplier/portal/disclosures', label: '永續 KPI 填報' },
  { to: '/supplier/portal/activity-reports', label: '活動資料申報' },
  { to: '/supplier/portal/caps', label: 'CAP 矯正' },
]

const ROLE_LABELS: Record<string, string> = {
  supplier: '供應商',
  sup_esg: '供應商 ESG',
}

export default defineComponent({
  name: 'AppPortal',
  components: { NotificationBell },
  setup() {
    const route = useRoute()
    const router = useRouter()
    const auth = useAuthStore()

    const showNav = computed(() => auth.isAuthenticated && route.path !== '/login')
    const roleLabel = computed(() => ROLE_LABELS[auth.user?.role ?? ''] ?? auth.user?.role ?? '')

    function logout() {
      auth.logout()
      router.push('/login')
    }

    return { NAV_ITEMS, showNav, roleLabel, auth, logout, portalNotificationsApi }
  },
})
</script>

<template>
  <div class="app-layout">
    <header v-if="showNav" class="portal-topbar">
      <div class="portal-topbar-brand">ESG·Chain 供應商 Portal</div>
      <nav class="portal-topbar-nav">
        <router-link
          v-for="item in NAV_ITEMS"
          :key="item.to"
          :to="item.to"
          class="portal-topbar-link"
          active-class="portal-topbar-link-active"
        >{{ item.label }}</router-link>
      </nav>
      <div class="portal-topbar-user">
        <NotificationBell :api="portalNotificationsApi" cap-list-path="/supplier/portal/caps" dark />
        <span class="portal-topbar-username">{{ auth.user?.name }}</span>
        <span class="portal-topbar-role">{{ roleLabel }}</span>
        <button class="portal-topbar-logout" @click="logout">登出</button>
      </div>
    </header>
    <main class="main-full">
      <RouterView />
    </main>
  </div>
</template>

<style>
.app-layout { display: flex; flex-direction: column; height: 100vh; overflow: hidden; }
.main-full { flex: 1; overflow-y: auto; background: var(--bg); }

.portal-topbar {
  display: flex;
  align-items: center;
  gap: 20px;
  padding: 0 20px;
  height: 52px;
  flex-shrink: 0;
  background: var(--sidebar-bg, #1a1714);
  color: rgba(255,255,255,0.85);
  overflow-x: auto;
}
.portal-topbar-brand {
  font-weight: 700;
  font-size: 14px;
  white-space: nowrap;
  color: #fff;
}
.portal-topbar-nav {
  display: flex;
  gap: 4px;
  flex: 1;
}
.portal-topbar-link {
  padding: 6px 12px;
  border-radius: 6px;
  font-size: 13px;
  color: rgba(255,255,255,0.7);
  text-decoration: none;
  white-space: nowrap;
  transition: background 0.15s, color 0.15s;
}
.portal-topbar-link:hover { background: rgba(255,255,255,0.08); color: #fff; }
.portal-topbar-link-active { background: rgba(26,77,62,0.5); color: #fff; }
.portal-topbar-user {
  display: flex;
  align-items: center;
  gap: 10px;
  white-space: nowrap;
}
.portal-topbar-username { font-size: 13px; }
.portal-topbar-role {
  font-size: 11px;
  padding: 2px 8px;
  border-radius: 99px;
  background: rgba(255,255,255,0.1);
}
.portal-topbar-logout {
  background: transparent;
  border: 1px solid rgba(255,255,255,0.25);
  color: rgba(255,255,255,0.85);
  border-radius: 6px;
  padding: 4px 10px;
  font-size: 12px;
  cursor: pointer;
}
.portal-topbar-logout:hover { background: rgba(255,255,255,0.1); }
</style>
