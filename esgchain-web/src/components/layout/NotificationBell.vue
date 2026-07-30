<template>
  <div class="notif-bell-wrap" :class="{ 'notif-bell-wrap--dark': dark }" v-click-outside="close">
    <button class="notif-bell-btn" :class="{ 'notif-bell-btn--active': open }" title="通知" @click="toggle">
      <span class="notif-bell-icon">🔔</span>
      <span v-if="unreadCount > 0" class="notif-bell-badge">{{ unreadCount > 99 ? '99+' : unreadCount }}</span>
    </button>

    <div v-if="open" class="notif-panel">
      <div class="notif-panel__header">
        <span class="notif-panel__title">通知</span>
        <button v-if="unreadCount > 0" class="notif-panel__mark-all" @click="markAllRead">全部標記已讀</button>
      </div>

      <div v-if="isLoading" class="notif-panel__empty">載入中...</div>
      <div v-else-if="items.length === 0" class="notif-panel__empty">目前沒有通知</div>
      <div v-else class="notif-panel__list">
        <div
          v-for="item in items"
          :key="item.id"
          class="notif-item"
          :class="{ 'notif-item--unread': !item.read_at }"
          @click="onItemClick(item)"
        >
          <div class="notif-item__dot" v-if="!item.read_at"></div>
          <div class="notif-item__body">
            <div class="notif-item__title">{{ item.data.title ?? item.data.message }}</div>
            <div v-if="item.data.title" class="notif-item__message">{{ item.data.message }}</div>
            <div class="notif-item__time">{{ formatTime(item.created_at) }}</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import type { PropType } from 'vue'
import type { NotificationItem } from '@/api/modules/notifications'

interface NotificationApi {
  list: (page?: number, perPage?: number) => Promise<{ data: { data: NotificationItem[] } }>
  unreadCount: () => Promise<{ data: { data: { unread_count: number } } }>
  markRead: () => Promise<unknown>
  markOneRead: (id: string) => Promise<unknown>
}

const POLL_INTERVAL_MS = 60_000

export default defineComponent({
  name: 'NotificationBell',
  directives: {
    // 點擊面板外自動收合
    clickOutside: {
      mounted(el, binding) {
        el.__clickOutsideHandler__ = (e: MouseEvent) => {
          if (!(el === e.target || el.contains(e.target as Node))) binding.value()
        }
        document.addEventListener('click', el.__clickOutsideHandler__)
      },
      unmounted(el) {
        document.removeEventListener('click', el.__clickOutsideHandler__)
      },
    },
  },
  props: {
    api: {
      type: Object as PropType<NotificationApi>,
      required: true,
    },
    // CAP 相關通知點擊後導向的清單頁路徑（目前系統唯一通知類型是 CAP 指派，
    // 尚未做到單筆深連結，先導向清單頁讓使用者自行找到該筆）
    capListPath: {
      type: String,
      default: '/caps',
    },
    // 深色 topbar（供應商 Portal）情境下使用淺色圖示與 hover 效果
    dark: {
      type: Boolean,
      default: false,
    },
  },
  data() {
    return {
      open: false,
      isLoading: false,
      items: [] as NotificationItem[],
      unreadCount: 0,
      pollTimer: null as ReturnType<typeof setInterval> | null,
    }
  },
  mounted() {
    this.loadUnreadCount()
    this.pollTimer = setInterval(() => this.loadUnreadCount(), POLL_INTERVAL_MS)
  },
  beforeUnmount() {
    if (this.pollTimer) clearInterval(this.pollTimer)
  },
  methods: {
    async loadUnreadCount() {
      try {
        const { data } = await this.api.unreadCount()
        this.unreadCount = data.data.unread_count
      } catch { /* 靜默失敗，不影響其他區塊 */ }
    },
    async toggle() {
      this.open = !this.open
      if (this.open) await this.loadList()
    },
    close() {
      this.open = false
    },
    async loadList() {
      this.isLoading = true
      try {
        const { data } = await this.api.list(1, 20)
        this.items = data.data
      } catch { /* 靜默失敗 */ }
      finally { this.isLoading = false }
    },
    async markAllRead() {
      try {
        await this.api.markRead()
        this.items = this.items.map(i => ({ ...i, read_at: i.read_at ?? new Date().toISOString() }))
        this.unreadCount = 0
      } catch { /* 靜默失敗 */ }
    },
    async onItemClick(item: NotificationItem) {
      if (!item.read_at) {
        try {
          await this.api.markOneRead(item.id)
          item.read_at = new Date().toISOString()
          this.unreadCount = Math.max(0, this.unreadCount - 1)
        } catch { /* 靜默失敗 */ }
      }
      this.open = false
      if (item.data.cap_id) this.$router.push(this.capListPath)
    },
    formatTime(iso: string): string {
      const d = new Date(iso)
      return d.toLocaleString('zh-TW', { month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' })
    },
  },
})
</script>

<style scoped>
.notif-bell-wrap { position: relative; }
.notif-bell-btn {
  position: relative;
  width: 36px; height: 36px;
  display: flex; align-items: center; justify-content: center;
  background: none;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-size: 17px;
}
.notif-bell-btn:hover, .notif-bell-btn--active { background: rgba(0,0,0,0.06); }
.notif-bell-wrap--dark .notif-bell-btn:hover,
.notif-bell-wrap--dark .notif-bell-btn--active { background: rgba(255,255,255,0.08); }
.notif-bell-icon { line-height: 1; }
.notif-bell-badge {
  position: absolute;
  top: 2px; right: 2px;
  min-width: 15px; height: 15px; padding: 0 3px;
  background: #dc2626;
  color: #fff;
  border-radius: 999px;
  font-size: 10px;
  font-weight: 700;
  line-height: 15px;
  text-align: center;
}

.notif-panel {
  position: absolute;
  top: 44px; right: 0;
  width: 340px;
  max-height: 420px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 10px;
  box-shadow: 0 8px 30px rgba(0,0,0,.15);
  z-index: 300;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
.notif-panel__header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 12px 14px;
  border-bottom: 1px solid var(--border);
  flex-shrink: 0;
}
.notif-panel__title { font-size: 14px; font-weight: 700; color: var(--text-primary); }
.notif-panel__mark-all { background: none; border: none; color: var(--accent); font-size: 12px; cursor: pointer; }
.notif-panel__mark-all:hover { text-decoration: underline; }
.notif-panel__empty { padding: 32px 16px; text-align: center; font-size: 13px; color: var(--text-secondary); }
.notif-panel__list { overflow-y: auto; }

.notif-item {
  display: flex; gap: 8px;
  padding: 12px 14px;
  border-bottom: 1px solid var(--border);
  cursor: pointer;
  transition: background 0.1s;
}
.notif-item:last-child { border-bottom: none; }
.notif-item:hover { background: var(--surface-2); }
.notif-item--unread { background: var(--accent-soft); }
.notif-item--unread:hover { background: var(--accent-soft); opacity: 0.85; }
.notif-item__dot { width: 7px; height: 7px; border-radius: 50%; background: var(--accent); margin-top: 5px; flex-shrink: 0; }
.notif-item__body { flex: 1; min-width: 0; }
.notif-item__title { font-size: 13px; font-weight: 600; color: var(--text-primary); }
.notif-item__message { font-size: 12px; color: var(--text-secondary); margin-top: 2px; }
.notif-item__time { font-size: 11px; color: var(--text-secondary); margin-top: 4px; }
</style>
