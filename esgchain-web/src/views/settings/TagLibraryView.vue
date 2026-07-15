<template>
  <div :class="embedded ? '' : 'page-container'">
    <template v-if="!embedded">
      <div class="breadcrumb">
        <button class="breadcrumb-link" @click="$router.push('/settings')">系統設定</button>
        <span class="breadcrumb-sep">›</span>
        <span class="breadcrumb-current">標籤庫</span>
      </div>

      <div class="page-header">
        <div>
          <h1 class="page-title">標籤庫管理</h1>
          <p class="page-subtitle">三層分類結構：L1 領域 → L2 調查分類 → L3 調查主題。識別碼（Slug）建立後不可修改。</p>
        </div>
      </div>
    </template>

    <div class="tag-library-layout">
      <!-- 左側樹狀導覽 -->
      <aside class="tag-tree">
        <div class="tree-section-label">
          L1 領域
          <button class="tree-add-btn" title="新增領域" @click="openAddL1Modal">＋</button>
        </div>

        <div v-if="tree.length === 0" class="tree-empty">載入中…</div>

        <div v-for="domain in tree" :key="domain.l1_domain" class="tree-domain">
          <!-- L1 row -->
          <div
            class="tree-l1"
            :class="{ active: selectedL1 === domain.l1_domain }"
            @click="toggleL1(domain.l1_domain)"
          >
            <span class="tree-arrow">{{ expandedL1[domain.l1_domain] ? '▾' : '▸' }}</span>
            <span class="tree-l1-label">{{ domain.l1_domain }}</span>
            <span class="l1-count">{{ domain.pillars.length }}</span>
          </div>

          <!-- L2 rows -->
          <div v-show="expandedL1[domain.l1_domain]" class="tree-pillars">
            <div class="tree-l2-header">
              <span>L2 調查分類</span>
              <button
                class="tree-add-btn-sm"
                title="新增調查分類"
                @click.stop="openAddL2Modal(domain.l1_domain)"
              >＋</button>
            </div>
            <div
              v-for="pillar in domain.pillars"
              :key="pillar.l2_pillar"
              class="tree-l2"
              :class="{ active: selectedL1 === domain.l1_domain && selectedL2 === pillar.l2_pillar }"
              @click="selectL2(domain.l1_domain, pillar.l2_pillar)"
            >
              <span class="tree-l2-name">{{ pillar.l2_pillar }}</span>
              <span class="topic-count">{{ pillar.topics.length }}</span>
            </div>
          </div>
        </div>
      </aside>

      <!-- 右側 L3 列表 -->
      <main class="tag-content">
        <!-- Empty state -->
        <div v-if="!selectedL2" class="empty-hint">
          <div class="empty-icon">🏷️</div>
          <div class="empty-title">選擇左側調查分類</div>
          <div class="empty-desc">從左側選擇 L2 調查分類，即可查看與管理其下的 L3 調查主題</div>
        </div>

        <template v-else>
          <!-- 右側 header -->
          <div class="content-header">
            <div class="content-breadcrumb">
              <span class="cb-l1">{{ selectedL1 }}</span>
              <span class="cb-sep">›</span>
              <span class="cb-l2">{{ selectedL2 }}</span>
              <span class="cb-label">L3 調查主題 · {{ filteredTopics.length }} 個</span>
            </div>
            <button class="btn btn-primary btn-sm" @click="openAddL3Modal">＋ 新增調查主題</button>
          </div>

          <!-- L3 table -->
          <div class="table-wrap">
            <table class="data-table" style="table-layout:fixed;width:100%;">
              <colgroup>
                <col>
                <col style="width:220px;">
                <col style="width:220px;">
                <col style="width:68px;">
                <col style="width:140px;">
              </colgroup>
              <thead>
                <tr>
                  <th>調查主題</th>
                  <th>識別碼</th>
                  <th>計分引擎識別碼</th>
                  <th style="text-align:center;">狀態</th>
                  <th>操作</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="topic in filteredTopics" :key="topic.id" :class="{ deprecated: topic.deprecated_at }">
                  <td>
                    <div class="topic-name">{{ topic.label_zh || topic.l3_topic }}</div>
                    <div class="topic-en">{{ topic.label_en }}</div>
                  </td>
                  <td style="overflow:hidden;">
                    <code class="slug-code" :title="topic.slug">{{ topic.slug }}</code>
                  </td>
                  <td style="overflow:hidden;">
                    <span v-if="topic.scoring_engine_key" class="engine-badge" :title="topic.scoring_engine_key">{{ topic.scoring_engine_key }}</span>
                    <span v-else class="na">—</span>
                  </td>
                  <td style="text-align:center;">
                    <span class="badge" :class="topic.deprecated_at ? 'badge-gray' : 'badge-green'">
                      {{ topic.deprecated_at ? '停用' : '啟用' }}
                    </span>
                  </td>
                  <td>
                    <div class="row-actions">
                      <button class="btn btn-secondary btn-sm" @click="openEditL3Modal(topic)">編輯</button>
                      <button
                        v-if="!topic.deprecated_at"
                        class="btn btn-sm btn-danger-ghost"
                        @click="openDeprecateModal(topic)"
                      >停用</button>
                      <button v-else class="btn btn-secondary btn-sm" @click="restoreTag(topic)">恢復</button>
                    </div>
                  </td>
                </tr>
                <tr v-if="filteredTopics.length === 0">
                  <td colspan="5" class="empty-row">此分類尚無調查主題，點擊「＋ 新增調查主題」建立</td>
                </tr>
              </tbody>
            </table>
          </div>
        </template>
      </main>
    </div>

    <!-- ── 新增 L1 領域 Modal ── -->
    <div v-if="showAddL1Modal" class="modal-overlay" @click.self="showAddL1Modal = false">
      <div class="modal-card">
        <div class="modal-header-row">
          <h3 class="modal-title">新增 L1 領域</h3>
          <button class="modal-close" @click="showAddL1Modal = false">×</button>
        </div>
        <div class="form-group">
          <label class="form-label">領域 Key <span class="label-hint">英文，建立後不可改</span></label>
          <input v-model="l1Form.domain" class="form-input" placeholder="例如：Geo-Risk" />
        </div>
        <div class="modal-actions">
          <button class="btn btn-secondary" @click="showAddL1Modal = false">取消</button>
          <button class="btn btn-primary" :disabled="!l1Form.domain.trim() || l1Saving" @click="saveL1">
            {{ l1Saving ? '建立中…' : '建立領域' }}
          </button>
        </div>
      </div>
    </div>

    <!-- ── 新增 L2 調查分類 Modal ── -->
    <div v-if="showAddL2Modal" class="modal-overlay" @click.self="showAddL2Modal = false">
      <div class="modal-card">
        <div class="modal-header-row">
          <h3 class="modal-title">新增 L2 調查分類</h3>
          <button class="modal-close" @click="showAddL2Modal = false">×</button>
        </div>
        <div class="form-group">
          <label class="form-label">所屬領域</label>
          <input :value="selectedL1" class="form-input" disabled />
        </div>
        <div class="form-group">
          <label class="form-label">調查分類名稱</label>
          <input v-model="l2Form.pillar" class="form-input" placeholder="例如：物流/天災" />
        </div>
        <div class="modal-actions">
          <button class="btn btn-secondary" @click="showAddL2Modal = false">取消</button>
          <button class="btn btn-primary" :disabled="!l2Form.pillar.trim() || l2Saving" @click="saveL2">
            {{ l2Saving ? '建立中…' : '建立分類' }}
          </button>
        </div>
      </div>
    </div>

    <!-- ── 新增 L3 調查主題 Modal ── -->
    <div v-if="showAddL3Modal" class="modal-overlay" @click.self="showAddL3Modal = false">
      <div class="modal-card modal-card-wide">
        <div class="modal-header-row">
          <h3 class="modal-title">新增 L3 調查主題</h3>
          <button class="modal-close" @click="showAddL3Modal = false">×</button>
        </div>
        <div class="modal-location-pill">{{ selectedL1 }} › {{ selectedL2 }}</div>
        <div class="form-row">
          <div class="form-group" style="flex:1;">
            <label class="form-label">中文名稱 *</label>
            <input v-model="l3Form.label_zh" class="form-input" placeholder="例如：強迫勞動防制" />
          </div>
          <div class="form-group" style="flex:1;">
            <label class="form-label">英文名稱</label>
            <input v-model="l3Form.label_en" class="form-input" placeholder="例如：Forced Labor Prevention" @input="autoSlug" />
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">
            識別碼（Slug）*
            <span class="slug-immutable-warn">⚠ 建立後永久不可修改</span>
          </label>
          <input v-model="l3Form.slug" class="form-input font-mono" placeholder="自動生成，或手動填入" />
          <p class="form-hint">為 esgchain-ai 計分引擎識別鍵，與計分邏輯強耦合</p>
        </div>
        <div class="form-group">
          <label class="form-label">計分引擎識別碼 <span class="label-hint">選填</span></label>
          <input v-model="l3Form.scoring_engine_key" class="form-input font-mono" placeholder="例如：ghg_scoring_v1" />
          <p class="form-hint">需與 esgchain-ai 部署版本一致</p>
        </div>
        <div class="modal-actions">
          <button class="btn btn-secondary" @click="showAddL3Modal = false">取消</button>
          <button class="btn btn-primary" :disabled="!l3Form.label_zh.trim() || !l3Form.slug.trim() || l3Saving" @click="saveL3">
            {{ l3Saving ? '建立中…' : '建立調查主題' }}
          </button>
        </div>
      </div>
    </div>

    <!-- ── 編輯 L3 Modal ── -->
    <div v-if="showEditL3Modal" class="modal-overlay" @click.self="showEditL3Modal = false">
      <div class="modal-card modal-card-wide">
        <div class="modal-header-row">
          <h3 class="modal-title">編輯調查主題</h3>
          <button class="modal-close" @click="showEditL3Modal = false">×</button>
        </div>
        <div class="form-group">
          <label class="form-label">識別碼（Slug）<span class="slug-lock">🔒 不可修改</span></label>
          <input :value="editL3Form.slug" class="form-input font-mono" disabled />
        </div>
        <div class="form-row">
          <div class="form-group" style="flex:1;">
            <label class="form-label">中文名稱</label>
            <input v-model="editL3Form.label_zh" class="form-input" />
          </div>
          <div class="form-group" style="flex:1;">
            <label class="form-label">英文名稱</label>
            <input v-model="editL3Form.label_en" class="form-input" />
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">計分引擎識別碼</label>
          <input v-model="editL3Form.scoring_engine_key" class="form-input font-mono" />
          <p v-if="editEngineKeyChanged" class="form-hint form-hint-warn">
            ⚠ 修改計分引擎識別碼後，需同步更新並重新部署 esgchain-ai
          </p>
        </div>
        <div class="modal-actions">
          <button class="btn btn-secondary" @click="showEditL3Modal = false">取消</button>
          <button class="btn btn-primary" :disabled="editL3Saving" @click="saveEditL3">
            {{ editL3Saving ? '儲存中…' : '儲存變更' }}
          </button>
        </div>
      </div>
    </div>

    <!-- ── 停用確認 Modal ── -->
    <div v-if="showDeprecateModal" class="modal-overlay" @click.self="showDeprecateModal = false">
      <div class="modal-card">
        <div class="modal-header-row">
          <h3 class="modal-title">確認停用調查主題</h3>
          <button class="modal-close" @click="showDeprecateModal = false">×</button>
        </div>
        <div class="deprecate-info">
          <div class="deprecate-tag-name">{{ deprecateTarget?.label_zh }}</div>
          <div class="deprecate-slug">{{ deprecateTarget?.slug }}</div>
        </div>
        <p class="deprecate-desc">
          停用後，此主題將不再出現在題目打標籤的選項中。<br>
          <strong>已使用此標籤的題目不受影響。</strong>
        </p>
        <div class="modal-actions">
          <button class="btn btn-secondary" @click="showDeprecateModal = false">取消</button>
          <button class="btn btn-danger" :disabled="deprecateSaving" @click="confirmDeprecate">
            {{ deprecateSaving ? '停用中…' : '確認停用' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { tagLibraryApi, type QuestionTag, type TagTreeDomain } from '@/api/modules/settings'

export default defineComponent({
  name: 'TagLibraryView',
  props: {
    embedded: { type: Boolean, default: false },
  },
  data() {
    return {
      tree: [] as TagTreeDomain[],
      expandedL1: {} as Record<string, boolean>,
      selectedL1: '',
      selectedL2: '',

      showAddL1Modal: false,
      l1Saving: false,
      l1Form: { domain: '' },

      showAddL2Modal: false,
      l2Saving: false,
      l2Form: { pillar: '' },

      showAddL3Modal: false,
      l3Saving: false,
      l3Form: { label_zh: '', label_en: '', slug: '', scoring_engine_key: '' },

      showEditL3Modal: false,
      editL3Saving: false,
      editL3Target: null as QuestionTag | null,
      editL3Form: { label_zh: '', label_en: '', slug: '', scoring_engine_key: '' },
      editEngineKeyOriginal: '',

      showDeprecateModal: false,
      deprecateSaving: false,
      deprecateTarget: null as QuestionTag | null,
    }
  },

  computed: {
    filteredTopics(): QuestionTag[] {
      if (!this.selectedL1 || !this.selectedL2) return []
      const domain = this.tree.find(d => d.l1_domain === this.selectedL1)
      const pillar = domain?.pillars.find(p => p.l2_pillar === this.selectedL2)
      return pillar?.topics ?? []
    },
    editEngineKeyChanged(): boolean {
      return this.editL3Form.scoring_engine_key !== this.editEngineKeyOriginal
    },
  },

  mounted() {
    this.loadTree()
  },

  methods: {
    async loadTree() {
      const res = await tagLibraryApi.tree()
      this.tree = res.data.data
      // 預設展開並選取第一個 L1 的第一個 L2
      if (this.tree.length > 0) {
        const first = this.tree[0]
        this.expandedL1[first.l1_domain] = true
        if (!this.selectedL2 && first.pillars.length > 0) {
          this.selectedL1 = first.l1_domain
          this.selectedL2 = first.pillars[0].l2_pillar
        }
      }
    },

    toggleL1(domain: string) {
      this.expandedL1[domain] = !this.expandedL1[domain]
      if (this.expandedL1[domain]) {
        // 展開時自動選取第一個 L2
        const d = this.tree.find(t => t.l1_domain === domain)
        if (d && d.pillars.length > 0) {
          this.selectedL1 = domain
          this.selectedL2 = d.pillars[0].l2_pillar
        }
      } else {
        if (this.selectedL1 === domain) {
          this.selectedL2 = ''
        }
      }
    },

    selectL2(domain: string, pillar: string) {
      this.selectedL1 = domain
      this.selectedL2 = pillar
      this.expandedL1[domain] = true
    },

    openAddL1Modal() {
      this.l1Form = { domain: '' }
      this.showAddL1Modal = true
    },
    openAddL2Modal(domain?: string) {
      if (domain) this.selectedL1 = domain
      this.l2Form = { pillar: '' }
      this.showAddL2Modal = true
    },
    openAddL3Modal() {
      this.l3Form = { label_zh: '', label_en: '', slug: '', scoring_engine_key: '' }
      this.showAddL3Modal = true
    },
    openEditL3Modal(tag: QuestionTag) {
      this.editL3Target = tag
      this.editL3Form = {
        label_zh: tag.label_zh ?? '',
        label_en: tag.label_en ?? '',
        slug: tag.slug,
        scoring_engine_key: tag.scoring_engine_key ?? '',
      }
      this.editEngineKeyOriginal = tag.scoring_engine_key ?? ''
      this.showEditL3Modal = true
    },
    openDeprecateModal(tag: QuestionTag) {
      this.deprecateTarget = tag
      this.showDeprecateModal = true
    },

    autoSlug() {
      if (this.l3Form.label_en) {
        const l1Key = this.selectedL1.toLowerCase().replace(/[-\s]/g, '_')
        const l2Key = this.selectedL2.toLowerCase().replace(/[-\s\/]/g, '_')
        const l3Key = this.l3Form.label_en
          .toLowerCase()
          .replace(/[\s\-\(\)&,\/]/g, '_')
          .replace(/_+/g, '_')
          .replace(/^_|_$/g, '')
        this.l3Form.slug = `${l1Key}.${l2Key}.${l3Key}`
      }
    },

    async saveL1() {
      if (!this.l1Form.domain.trim()) return
      this.l1Saving = true
      try {
        const key = this.l1Form.domain.trim()
        await tagLibraryApi.create({
          l1_domain: key,
          l2_pillar: '_placeholder',
          l3_topic: '_placeholder',
          slug: `${key.toLowerCase().replace(/[-\s]/g, '_')}._._`,
        })
        this.showAddL1Modal = false
        await this.loadTree()
        this.expandedL1[key] = true
        this.selectedL1 = key
        this.selectedL2 = ''
      } finally {
        this.l1Saving = false
      }
    },

    async saveL2() {
      if (!this.l2Form.pillar.trim()) return
      this.l2Saving = true
      try {
        const pillar = this.l2Form.pillar.trim()
        await tagLibraryApi.create({
          l1_domain: this.selectedL1,
          l2_pillar: pillar,
          l3_topic: '_placeholder',
          slug: `${this.selectedL1.toLowerCase().replace(/[-\s]/g, '_')}.${pillar.toLowerCase().replace(/[\s\/]/g, '_')}._`,
        })
        this.showAddL2Modal = false
        await this.loadTree()
        this.selectedL2 = pillar
      } finally {
        this.l2Saving = false
      }
    },

    async saveL3() {
      if (!this.l3Form.label_zh.trim() || !this.l3Form.slug.trim()) return
      this.l3Saving = true
      try {
        await tagLibraryApi.create({
          l1_domain: this.selectedL1,
          l2_pillar: this.selectedL2,
          l3_topic: this.l3Form.label_zh,
          slug: this.l3Form.slug,
          label_zh: this.l3Form.label_zh,
          label_en: this.l3Form.label_en || undefined,
          scoring_engine_key: this.l3Form.scoring_engine_key || null,
        })
        this.showAddL3Modal = false
        await this.loadTree()
      } finally {
        this.l3Saving = false
      }
    },

    async saveEditL3() {
      if (!this.editL3Target) return
      this.editL3Saving = true
      try {
        await tagLibraryApi.update(this.editL3Target.id, {
          label_zh: this.editL3Form.label_zh,
          label_en: this.editL3Form.label_en,
          scoring_engine_key: this.editL3Form.scoring_engine_key || null,
        })
        this.showEditL3Modal = false
        await this.loadTree()
      } finally {
        this.editL3Saving = false
      }
    },

    async confirmDeprecate() {
      if (!this.deprecateTarget) return
      this.deprecateSaving = true
      try {
        await tagLibraryApi.deprecate(this.deprecateTarget.id)
        this.showDeprecateModal = false
        await this.loadTree()
      } finally {
        this.deprecateSaving = false
      }
    },

    async restoreTag(tag: QuestionTag) {
      await tagLibraryApi.restore(tag.id)
      await this.loadTree()
    },
  },
})
</script>

<style scoped>
/* ── 麵包屑 ── */
.breadcrumb { display: flex; align-items: center; gap: 6px; font-size: 13px; margin-bottom: 16px; }
.breadcrumb-link { background: none; border: none; color: var(--accent); cursor: pointer; padding: 0; font-size: 13px; }
.breadcrumb-link:hover { text-decoration: underline; }
.breadcrumb-sep { color: var(--text-secondary); }
.breadcrumb-current { color: var(--text-primary); font-weight: 500; }

/* ── Layout ── */
.tag-library-layout {
  display: flex;
  gap: 20px;
  align-items: flex-start;
}

/* ── Left Tree ── */
.tag-tree {
  width: 240px;
  flex-shrink: 0;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 10px;
  overflow: hidden;
}

.tree-section-label {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 14px 8px;
  font-size: 11px;
  font-weight: 700;
  color: var(--text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.07em;
  border-bottom: 1px solid var(--border);
  background: var(--surface-2);
}

.tree-add-btn {
  background: none;
  border: 1px solid var(--border);
  border-radius: 4px;
  width: 20px;
  height: 20px;
  cursor: pointer;
  font-size: 13px;
  line-height: 1;
  color: var(--text-secondary);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0;
}
.tree-add-btn:hover { background: var(--border); color: var(--text-primary); }

.tree-empty { padding: 16px; font-size: 13px; color: var(--text-secondary); text-align: center; }

.tree-domain { border-bottom: 1px solid var(--border); }
.tree-domain:last-child { border-bottom: none; }

.tree-l1 {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 9px 14px;
  cursor: pointer;
  font-weight: 600;
  font-size: 13px;
  color: var(--text-primary);
  user-select: none;
}
.tree-l1:hover { background: var(--surface-2); }
.tree-l1.active { background: var(--surface-2); }

.tree-arrow { font-size: 11px; color: var(--text-secondary); width: 12px; flex-shrink: 0; }
.tree-l1-label { flex: 1; }
.l1-count {
  font-size: 11px;
  background: var(--border);
  border-radius: 8px;
  padding: 1px 6px;
  color: var(--text-secondary);
  font-weight: 400;
}

.tree-pillars { background: var(--surface-2); }

.tree-l2-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 5px 14px 4px 28px;
  font-size: 10px;
  font-weight: 700;
  color: var(--text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.06em;
  opacity: 0.7;
}
.tree-add-btn-sm {
  background: none;
  border: none;
  cursor: pointer;
  font-size: 12px;
  color: var(--text-secondary);
  padding: 0 2px;
  line-height: 1;
}
.tree-add-btn-sm:hover { color: var(--accent); }

.tree-l2 {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 7px 14px 7px 28px;
  cursor: pointer;
  font-size: 13px;
  color: var(--text-secondary);
  border-left: 2px solid transparent;
  transition: all 0.1s;
}
.tree-l2:hover { background: var(--border); color: var(--text-primary); }
.tree-l2.active {
  background: var(--border);
  color: var(--accent);
  border-left-color: var(--accent);
  font-weight: 500;
}

.tree-l2-name { flex: 1; }

.topic-count {
  font-size: 11px;
  background: #fff;
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 1px 6px;
  color: var(--text-secondary);
  font-weight: 500;
  min-width: 20px;
  text-align: center;
}
.tree-l2.active .topic-count { background: var(--accent); border-color: var(--accent); color: #fff; }

/* ── Right Panel ── */
.tag-content { flex: 1; min-width: 0; }

.empty-hint {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 80px 20px;
  text-align: center;
}
.empty-icon { font-size: 40px; margin-bottom: 12px; opacity: 0.4; }
.empty-title { font-size: 16px; font-weight: 600; color: var(--text-primary); margin-bottom: 6px; }
.empty-desc { font-size: 13px; color: var(--text-secondary); max-width: 300px; line-height: 1.6; }

.content-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 14px;
}

.content-breadcrumb {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 14px;
}
.cb-l1 { color: var(--text-secondary); }
.cb-sep { color: var(--border); }
.cb-l2 { font-weight: 600; color: var(--text-primary); }
.cb-label {
  margin-left: 4px;
  font-size: 12px;
  color: var(--text-secondary);
  background: var(--surface-2);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 2px 8px;
}

.table-wrap { background: var(--surface); border: 1px solid var(--border); border-radius: 8px; overflow: hidden; }

.topic-name { font-size: 13px; font-weight: 500; }
.topic-en { font-size: 12px; color: var(--text-secondary); margin-top: 1px; }

.slug-code {
  display: block;
  font-size: 11.5px;
  background: var(--surface-2);
  border: 1px solid var(--border);
  border-radius: 4px;
  padding: 2px 6px;
  color: var(--text-secondary);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.engine-badge {
  display: block;
  font-size: 11px;
  background: #e8f4fd;
  color: #1a6fa0;
  border: 1px solid #b8ddf5;
  border-radius: 4px;
  padding: 2px 6px;
  font-family: monospace;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.na { color: var(--text-secondary); font-size: 13px; }

tr.deprecated { opacity: 0.5; }
.empty-row { text-align: center; padding: 32px; color: var(--text-secondary); font-size: 13px; }

.row-actions { display: flex; gap: 6px; flex-wrap: nowrap; white-space: nowrap; }

.btn-danger-ghost {
  background: none;
  border: 1px solid #e5c5c5;
  color: #c0392b;
}
.btn-danger-ghost:hover { background: #fdf2f2; }

/* ── Modals ── */
.modal-overlay {
  position: fixed; inset: 0;
  background: rgba(0,0,0,0.35);
  display: flex; align-items: center; justify-content: center;
  z-index: 1000;
}
.modal-card {
  background: var(--surface);
  border-radius: 10px;
  padding: 24px 28px;
  width: 440px;
  max-width: 94vw;
  box-shadow: 0 8px 32px rgba(0,0,0,0.12);
}
.modal-card-wide { width: 560px; }

.modal-header-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 18px;
}
.modal-title { font-size: 16px; font-weight: 600; margin: 0; }
.modal-close {
  background: none; border: none; font-size: 20px;
  cursor: pointer; color: var(--text-secondary); line-height: 1; padding: 0;
}
.modal-close:hover { color: var(--text-primary); }

.modal-location-pill {
  display: inline-block;
  font-size: 12px;
  background: var(--surface-2);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 3px 10px;
  margin-bottom: 16px;
  color: var(--text-secondary);
}

.form-row { display: flex; gap: 14px; }
.form-group { margin-bottom: 14px; }
.form-label { display: block; font-size: 13px; font-weight: 500; margin-bottom: 5px; color: var(--text-primary); }
.label-hint { font-weight: 400; color: var(--text-secondary); margin-left: 4px; }
.form-input { width: 100%; box-sizing: border-box; }
.form-hint { font-size: 12px; color: var(--text-secondary); margin-top: 4px; }
.form-hint-warn { color: #c0392b !important; }

.slug-immutable-warn {
  font-size: 11px;
  color: #c0392b;
  font-weight: 400;
  margin-left: 6px;
}
.slug-lock { font-size: 12px; color: var(--text-secondary); font-weight: 400; margin-left: 6px; }

.modal-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 20px; }

.deprecate-info {
  background: var(--surface-2);
  border: 1px solid var(--border);
  border-radius: 6px;
  padding: 10px 14px;
  margin-bottom: 12px;
}
.deprecate-tag-name { font-weight: 600; font-size: 14px; }
.deprecate-slug { font-size: 12px; color: var(--text-secondary); font-family: monospace; margin-top: 2px; }
.deprecate-desc { font-size: 13px; color: var(--text-secondary); line-height: 1.6; margin-bottom: 4px; }
</style>
