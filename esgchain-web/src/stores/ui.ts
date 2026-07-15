import { defineStore } from 'pinia'

const STORAGE_KEY = 'esgchain-sidebar-collapsed'

export const useUiStore = defineStore('ui', {
  state: () => ({
    sidebarCollapsed: localStorage.getItem(STORAGE_KEY) === 'true',
    sidebarMobileOpen: false,
  }),

  actions: {
    toggleSidebar() {
      this.sidebarCollapsed = !this.sidebarCollapsed
      localStorage.setItem(STORAGE_KEY, String(this.sidebarCollapsed))
    },
    openMobileSidebar() {
      this.sidebarMobileOpen = true
    },
    closeMobileSidebar() {
      this.sidebarMobileOpen = false
    },
  },
})
