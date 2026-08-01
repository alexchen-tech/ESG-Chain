<template>
  <div class="page-container">
    <div class="breadcrumb">
      <button class="breadcrumb-link" @click="$router.push('/settings')">系統設定</button>
      <span class="breadcrumb-sep">›</span>
      <span class="breadcrumb-current">存取控制</span>
    </div>

    <div class="page-header">
      <div>
        <h1 class="page-title">存取控制</h1>
        <p class="page-subtitle">使用者帳號管理與角色權限矩陣</p>
      </div>
    </div>

    <div class="detail-tabs">
      <button
        v-for="tab in TABS"
        :key="tab.key"
        class="detail-tab"
        :class="{ active: activeTab === tab.key }"
        @click="activeTab = tab.key"
      >{{ tab.label }}</button>
    </div>

    <div class="tab-panel-wrap">
      <div v-show="activeTab === 'users'">
        <UsersView />
      </div>
      <div v-show="activeTab === 'roles'">
        <RolesView />
      </div>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import UsersView from './UsersView.vue'
import RolesView from './RolesView.vue'

const TABS = [
  { key: 'users', label: '使用者管理' },
  { key: 'roles', label: '角色管理' },
]

export default defineComponent({
  name: 'AccessControlHubView',

  components: { UsersView, RolesView },

  data() {
    return {
      TABS,
      activeTab: 'users',
    }
  },

  mounted() {
    const tab = this.$route.query.tab as string | undefined
    if (tab && TABS.some(t => t.key === tab)) {
      this.activeTab = tab
    }
  },
})
</script>
