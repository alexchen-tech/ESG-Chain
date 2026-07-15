<template>
  <div class="page-container">
    <div class="breadcrumb">
      <button class="breadcrumb-link" @click="router.push('/settings')">系統設定</button>
      <span class="breadcrumb-sep">›</span>
      <span class="breadcrumb-current">計分模型</span>
    </div>

    <div class="page-header">
      <div>
        <h1 class="page-title">計分模型管理</h1>
        <p class="page-subtitle">管理各框架預設 Pillar 加權與 SASB 必調題目設定</p>
      </div>
    </div>

    <FrameworkDefaultWeightPanel />

    <div style="margin-top:24px;">
      <SasbRequiredTopicPanel />
    </div>
  </div>
</template>

<script>
import { useRouter } from 'vue-router'
import FrameworkDefaultWeightPanel from './FrameworkDefaultWeightPanel.vue'
import SasbRequiredTopicPanel from './SasbRequiredTopicPanel.vue'

export default {
  name: 'ScoringModelView',

  components: {
    FrameworkDefaultWeightPanel,
    SasbRequiredTopicPanel,
  },

  setup() {
    const router = useRouter()
    return { router }
  },
}
</script>
