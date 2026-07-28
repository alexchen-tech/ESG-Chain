<script lang="ts">
import { defineComponent, computed, ref, onMounted, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import AppSidebar from '@/components/layout/AppSidebar.vue'

const NO_SIDEBAR_ROUTES = ['/login']

export default defineComponent({
  name: 'App',
  components: { AppSidebar },
  setup() {
    const route = useRoute()
    const auth = useAuthStore()
    const uiStore = useUiStore()
    const isMobile = ref(window.innerWidth < 768)

    function onResize() {
      isMobile.value = window.innerWidth < 768
    }
    onMounted(() => window.addEventListener('resize', onResize))
    onUnmounted(() => window.removeEventListener('resize', onResize))

    const showSidebar = computed(() =>
      auth.isAuthenticated &&
      !NO_SIDEBAR_ROUTES.some(p => route.path.startsWith(p))
    )

    const mainMargin = computed(() => {
      if (!showSidebar.value || isMobile.value) return '0px'
      return uiStore.sidebarCollapsed ? '56px' : '220px'
    })

    return { showSidebar, mainMargin, uiStore, isMobile }
  },
})
</script>

<template>
  <div class="app-layout">
    <AppSidebar v-if="showSidebar" />
    <!-- 手機漢堡按鈕（sidebar 隱藏時顯示） -->
    <button
      v-if="showSidebar && isMobile"
      class="mobile-hamburger"
      @click="uiStore.openMobileSidebar()"
    >☰</button>
    <main
      :class="showSidebar ? 'main-with-sidebar' : 'main-full'"
      :style="showSidebar ? { marginLeft: mainMargin } : {}"
    >
      <RouterView />
    </main>
  </div>
</template>

<style>
.app-layout { display: flex; height: 100vh; overflow: hidden; }
.main-with-sidebar { flex: 1; overflow-y: auto; background: var(--bg); transition: margin-left 0.25s ease; }
.main-full { flex: 1; overflow-y: auto; background: var(--bg); }

.mobile-hamburger {
  position: fixed;
  top: 12px;
  left: 12px;
  z-index: 11;
  background: var(--sidebar-bg);
  color: rgba(255,255,255,0.8);
  border: none;
  border-radius: 6px;
  font-size: 18px;
  width: 36px;
  height: 36px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}
</style>
