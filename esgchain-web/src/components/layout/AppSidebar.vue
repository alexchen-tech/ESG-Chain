<template>
  <div class="sidebar-root">
    <!-- Mobile overlay backdrop -->
    <div
      v-if="isMobile && uiStore.sidebarMobileOpen"
      class="sidebar-backdrop"
      @click="uiStore.closeMobileSidebar()"
    />

    <nav
      class="sidebar"
      :class="{
        'sidebar-collapsed': !isMobile && uiStore.sidebarCollapsed,
        'sidebar-mobile-open': isMobile && uiStore.sidebarMobileOpen,
      }"
    >
      <!-- 頂部：Logo + 漢堡按鈕 -->
      <div class="sidebar-top">
        <span v-show="!uiStore.sidebarCollapsed || isMobile" class="logo-text">ESG·Chain</span>
        <button class="hamburger-btn" @click="toggleHandler">☰</button>
      </div>

      <div class="sidebar-menu">
        <template v-for="item in menuItems" :key="item.name">
          <!-- 有子選單的父項 -->
          <template v-if="item.children">
            <div
              class="menu-item menu-parent"
              :class="{ 'menu-item-active': isParentActive(item) }"
              :data-tooltip="item.label"
              @click="toggleGroup(item.name)"
            >
              <span class="menu-icon">{{ item.icon }}</span>
              <span class="menu-label">{{ item.label }}</span>
              <span class="menu-chevron" :class="{ open: openGroups.includes(item.name) }">›</span>
            </div>
            <div v-show="openGroups.includes(item.name) && (!uiStore.sidebarCollapsed || isMobile)" class="menu-children">
              <router-link
                v-for="child in item.children.filter(c => c.roles.includes(userRole))"
                :key="child.name"
                :to="child.path"
                class="menu-item menu-child"
                active-class="menu-item-active"
                @click="isMobile && uiStore.closeMobileSidebar()"
              >
                <span class="menu-icon" style="font-size:10px;">▸</span>
                <span class="menu-label">{{ child.label }}</span>
              </router-link>
            </div>
          </template>
          <!-- 一般項目 -->
          <router-link
            v-else
            :to="item.path"
            class="menu-item"
            active-class="menu-item-active"
            :data-tooltip="item.label"
            @click="isMobile && uiStore.closeMobileSidebar()"
          >
            <span class="menu-icon">{{ item.icon }}</span>
            <span class="menu-label">{{ item.label }}</span>
          </router-link>
        </template>
      </div>

      <div class="sidebar-footer">
        <div v-show="!uiStore.sidebarCollapsed || isMobile" class="user-info">
          <div class="user-avatar">{{ userInitial }}</div>
          <div class="user-details">
            <div class="user-name">{{ authStore.user?.name }}</div>
            <div class="user-role">{{ roleLabel }}</div>
          </div>
        </div>
        <button class="logout-btn" @click="logout" :title="uiStore.sidebarCollapsed ? '登出' : ''">
          <span>⏏</span>
          <span v-if="!isMobile && !uiStore.sidebarCollapsed">登出</span>
        </button>
      </div>
    </nav>
  </div>
</template>

<script lang="ts">
import { defineComponent, computed, ref, onMounted, onUnmounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { useRouter } from 'vue-router'

const ROLE_LABELS: Record<string, string> = {
  admin: '系統管理員', buyer: '採購商', sustain: '永續長',
  comply: '法遵', analyst: '分析師',
}

const ALL_MENU = [
  { name: 'dashboard',      path: '/dashboard',      icon: '⊞', label: '儀表板',      roles: ['admin','buyer','sustain','comply','analyst'] },
  { name: 'suppliers',      path: '/suppliers',      icon: '⊕', label: '供應商管理',   roles: ['admin','buyer','sustain','comply','analyst'] },
  {
    name: 'material-group', path: '', icon: '▤', label: '物料管理',
    roles: ['admin','buyer','sustain','comply','analyst'],
    children: [
      { name: 'material-items',  path: '/materials/items',  label: '物料主檔', roles: ['admin','buyer','sustain','comply','analyst'] },
      { name: 'material-groups', path: '/materials/groups', label: '物料群組', roles: ['admin','buyer','sustain','comply'] },
    ],
  },
  {
    name: 'q-group', path: '', icon: '✦', label: '永續倡議問卷',
    roles: ['admin','sustain','comply','analyst'],
    children: [
      { name: 'questionnaires-review',    path: '/questionnaires/review',    label: '審核管理',    roles: ['admin','sustain','comply','analyst'] },
      { name: 'questionnaires-projects',  path: '/questionnaires/projects',  label: '評核專案',    roles: ['admin','sustain','comply','analyst'] },
      { name: 'questionnaires-series',    path: '/questionnaires/series',    label: '評核系列',    roles: ['admin','sustain','comply','analyst'] },
      { name: 'questionnaires-templates', path: '/questionnaires/templates', label: '評核範本',     roles: ['admin'] },
      { name: 'question-bank',            path: '/settings/question-bank',   label: '題目庫',      roles: ['admin'] },
    ],
  },
  {
    name: 'compliance-group', path: '', icon: '◎', label: '商品合規管理',
    roles: ['admin','buyer','sustain','comply'],
    children: [
      { name: 'compliance-dashboard', path: '/compliance',          label: '合規看板',   roles: ['admin','buyer','sustain','comply'] },
      { name: 'sales-products',        path: '/sales-products',      label: '銷售產品',   roles: ['admin','buyer','sustain','comply'] },
      { name: 'pcf-requests',          path: '/compliance/pcf-requests',       label: '碳排請求',   roles: ['admin','buyer','sustain'] },
      { name: 'production-batches',   path: '/compliance/production-batches', label: '生產批號', roles: ['admin','buyer','comply'] },
      { name: 'shipments',             path: '/compliance/shipments',          label: '出口申報', roles: ['admin','buyer','comply'] },
    ],
  },
  {
    name: 'risk-group', path: '', icon: '◉', label: '風險稽核',
    roles: ['admin','buyer','sustain','comply','analyst'],
    children: [
      { name: 'risk', path: '/risk', label: '永續風險概覽', roles: ['admin','sustain','comply','analyst'] },
      { name: 'risk-geo-events', path: '/risk/geo-events', label: '地緣事件複查', roles: ['admin','sustain'] },
      // { name: 'sustainability-risk', path: '/dashboard/sustainability-risk', label: '永續風險概覽', roles: ['admin','sustain','comply','analyst'] }, // 與六維熱圖完全重疊，已隱藏
      { name: 'cap',  path: '/cap',  label: 'CAP 矯正', roles: ['admin','buyer','sustain','comply'] },
    ],
  },
  // { name: 'reports', path: '/reports', icon: '▦', label: '報告管理', roles: ['admin','sustain','analyst'] }, // 功能開發中，暫時隱藏
  // 供應商 Portal 選單
  { name: 'supplier-portal',        path: '/supplier/portal',              icon: '⊞', label: '供應商入口',    roles: ['supplier','sup_esg'] },
  { name: 'supplier-survey-list',   path: '/supplier/compliance',          icon: '📄', label: '合規文件',      roles: ['supplier','sup_esg'] },
  { name: 'portal-pcf',             path: '/supplier/portal/pcf',          icon: '🌿', label: '碳排申報',      roles: ['supplier','sup_esg'] },
  { name: 'portal-disclosures',     path: '/supplier/portal/disclosures',  icon: '📊', label: '永續 KPI 填報', roles: ['supplier','sup_esg'] },
  {
    name: 'settings-group', path: '', icon: '⚙', label: '系統設定',
    roles: ['admin'],
    children: [
      { name: 'settings',         path: '/settings',               label: '一般設定',  roles: ['admin'] },
      { name: 'classification-scoring', path: '/settings/classification-scoring', label: '分類與計分管理', roles: ['admin'] },
      { name: 'customers',        path: '/settings/customers',       label: '客戶主檔',   roles: ['admin'] },
      { name: 'market-rules',     path: '/settings/market-rules',    label: '市場合規規則', roles: ['admin'] },
    ],
  },
]

export default defineComponent({
  name: 'AppSidebar',
  setup() {
    const authStore = useAuthStore()
    const uiStore = useUiStore()
    const router = useRouter()
    const isMobile = ref(false)

    function checkMobile() {
      isMobile.value = window.innerWidth < 768
    }

    onMounted(() => {
      checkMobile()
      window.addEventListener('resize', checkMobile)
    })
    onUnmounted(() => window.removeEventListener('resize', checkMobile))

    const openGroups = ref<string[]>(['q-group', 'risk-group', 'compliance-group', 'settings-group']) // 預設展開

    const userRole = computed(() => authStore.user?.role ?? '')

    const menuItems = computed(() => {
      const role = userRole.value
      return ALL_MENU.filter(m => m.roles.includes(role))
    })

    const roleLabel = computed(() =>
      ROLE_LABELS[userRole.value] ?? userRole.value
    )

    function toggleGroup(name: string) {
      const idx = openGroups.value.indexOf(name)
      if (idx >= 0) openGroups.value.splice(idx, 1)
      else openGroups.value.push(name)
    }

    function isParentActive(item: any): boolean {
      return item.children?.some((c: any) => router.currentRoute.value.path.startsWith(c.path)) ?? false
    }

    function toggleHandler() {
      if (isMobile.value) {
        uiStore.closeMobileSidebar()
      } else {
        uiStore.toggleSidebar()
      }
    }

    function logout() {
      authStore.logout()
      router.push('/login')
    }

    const userInitial = computed(() => {
      const name = authStore.user?.name ?? ''
      return name.charAt(0).toUpperCase() || '?'
    })

    return { authStore, uiStore, menuItems, roleLabel, logout, isMobile, toggleHandler, openGroups, userRole, toggleGroup, isParentActive, userInitial }
  },
})
</script>

<style scoped>
/* sidebar-root 本身不佔空間，只是 Fragment wrapper */
.sidebar-root { position: fixed; top: 0; left: 0; z-index: 10; }

.sidebar-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.45);
  z-index: 9;
}

.sidebar {
  width: 220px;
  background: var(--sidebar-bg);
  display: flex;
  flex-direction: column;
  height: 100vh;
  overflow: hidden;
  transition: width 0.25s ease;
}

/* 桌面收合 */
.sidebar-collapsed { width: 56px; }

/* 手機：預設隱藏，開啟時滑入 */
@media (max-width: 767px) {
  .sidebar {
    width: 220px;
    transform: translateX(-220px);
    transition: transform 0.25s ease;
  }
  .sidebar-mobile-open { transform: translateX(0); }
}

/* 頂部 */
.sidebar-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 18px 12px 16px;
  border-bottom: 1px solid rgba(255,255,255,0.08);
  min-height: 60px;
  flex-shrink: 0;
}

.logo-text {
  font-family: var(--font-title);
  font-size: 17px;
  font-weight: 700;
  color: #fff;
  letter-spacing: 0.02em;
  white-space: nowrap;
}

.hamburger-btn {
  background: none;
  border: none;
  color: rgba(255,255,255,0.6);
  font-size: 18px;
  cursor: pointer;
  padding: 4px 6px;
  border-radius: 4px;
  line-height: 1;
  flex-shrink: 0;
  transition: color 0.15s, background 0.15s;
}
.hamburger-btn:hover { color: #fff; background: rgba(255,255,255,0.08); }

/* 選單 */
.sidebar-menu {
  flex: 1;
  padding: 10px 0;
  overflow-y: auto;
  overflow-x: hidden;
}

.menu-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 18px;
  color: rgba(255,255,255,0.55);
  text-decoration: none;
  font-size: 14px;
  transition: all 0.15s;
  border-left: 3px solid transparent;
  white-space: nowrap;
  position: relative;
}
.menu-item:hover { color: rgba(255,255,255,0.85); background: rgba(255,255,255,0.04); }
.menu-item-active { color: #fff; background: rgba(26,77,62,0.4); border-left-color: var(--accent); }

/* 父選單項目 */
.menu-parent { cursor: pointer; }
.menu-chevron {
  margin-left: auto;
  font-size: 14px;
  color: rgba(255,255,255,0.4);
  transition: transform 0.2s;
  display: inline-block;
}
.menu-chevron.open { transform: rotate(90deg); }

/* 子選單 */
.menu-children { overflow: hidden; }
.menu-child {
  padding-left: 36px !important;
  font-size: 13px !important;
  border-left: none !important;
}
.menu-child.menu-item-active {
  background: rgba(26,77,62,0.3);
  border-left: none !important;
  color: rgba(255,255,255,0.9);
}

.menu-icon { font-size: 15px; width: 20px; text-align: center; flex-shrink: 0; }
.menu-label { font-size: 14px; transition: opacity 0.2s; white-space: nowrap; }

/* 收合時 menu 置中，文字隱藏，顯示 tooltip */
.sidebar-collapsed .menu-item { padding: 10px 0; justify-content: center; }
.sidebar-collapsed .menu-label { opacity: 0; width: 0; overflow: hidden; }
.sidebar-collapsed .menu-item[data-tooltip]:hover::after {
  content: attr(data-tooltip);
  position: fixed;
  left: 60px;
  background: #2d2d2d;
  color: #fff;
  padding: 4px 10px;
  border-radius: 4px;
  font-size: 12px;
  white-space: nowrap;
  pointer-events: none;
  z-index: 100;
}

/* 底部 */
.sidebar-footer {
  padding: 12px 10px;
  border-top: 1px solid rgba(255,255,255,0.06);
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.user-info {
  display: flex;
  align-items: center;
  gap: 9px;
  overflow: hidden;
  padding: 2px 4px;
}
.user-avatar {
  width: 30px; height: 30px;
  border-radius: 50%;
  background: rgba(26,77,62,0.7);
  border: 1px solid rgba(26,77,62,1);
  color: #a7f3d0;
  display: flex; align-items: center; justify-content: center;
  font-size: 13px; font-weight: 700;
  flex-shrink: 0;
}
.user-details { overflow: hidden; min-width: 0; }
.user-name { font-size: 13px; font-weight: 500; color: rgba(255,255,255,0.9); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.user-role { font-size: 11px; color: rgba(255,255,255,0.38); white-space: nowrap; }

.logout-btn {
  width: 100%;
  padding: 6px 10px;
  background: rgba(255,255,255,0.05);
  color: rgba(255,255,255,0.45);
  border: 1px solid rgba(255,255,255,0.08);
  border-radius: 6px;
  font-size: 12.5px;
  cursor: pointer;
  transition: all 0.15s;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
}
.logout-btn:hover { background: rgba(255,255,255,0.09); color: rgba(255,255,255,0.8); }
</style>
