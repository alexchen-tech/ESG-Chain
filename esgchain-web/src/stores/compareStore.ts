import { defineStore } from 'pinia'
import type { Supplier } from '@/api/modules/suppliers'

const MAX = 4

export const useCompareStore = defineStore('compare', {
  state: () => ({
    suppliers: [] as Supplier[],
  }),

  getters: {
    canAdd: (state) => state.suppliers.length < MAX,
    isAdded: (state) => (id: string) => state.suppliers.some(s => s.id === id),
  },

  actions: {
    add(supplier: Supplier) {
      if (this.suppliers.length >= MAX) return
      if (this.suppliers.some(s => s.id === supplier.id)) return
      this.suppliers.push(supplier)
    },
    remove(id: string) {
      this.suppliers = this.suppliers.filter(s => s.id !== id)
    },
    clear() {
      this.suppliers = []
    },
  },
})
