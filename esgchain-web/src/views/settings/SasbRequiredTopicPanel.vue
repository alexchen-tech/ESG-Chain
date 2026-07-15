<template>
  <div class="section-card">
    <div class="section-header">
      <div>
        <h2 class="section-title">SASB 必調題目設定</h2>
        <p class="section-desc">依 SASB 產業代碼設定必調的 ESG 議題，發送問卷給供應商時自動標記。</p>
      </div>
      <button class="btn btn-primary" @click="openAddTopic">＋ 新增必調 Topic</button>
    </div>

    <div v-if="isLoadingTopics" class="loading-state">載入中...</div>

    <div v-else-if="!Object.keys(sasbTopics).length" class="empty-state">
      尚無 SASB 必調設定
    </div>

    <div v-else class="sasb-list">
      <div v-for="(topics, code) in sasbTopics" :key="code" class="sasb-group">
        <div class="sasb-group-header" @click="toggleCode(code)">
          <div class="sasb-code-info">
            <span class="badge badge-gray font-mono">{{ code }}</span>
            <span class="sasb-count">{{ topics.length }} 個必調 topic</span>
          </div>
          <span class="expand-icon">{{ expandedCodes[code] ? '▲' : '▼' }}</span>
        </div>

        <div v-if="expandedCodes[code]" class="sasb-topics">
          <table class="pillar-table">
            <thead>
              <tr>
                <th>Tag Slug</th>
                <th>標籤庫對照</th>
                <th>名稱</th>
                <th>說明</th>
                <th style="width:80px;"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="topic in topics" :key="topic.id">
                <td><code class="font-mono" style="font-size:12px;">{{ topic.tag_slug }}</code></td>
                <td>
                  <span v-if="tagMatch(topic.tag_slug)?.deprecated_at" class="match-badge match-warn" :title="`對應標籤已停用：${tagMatch(topic.tag_slug)?.label_zh}`">
                    ⚠ 標籤已停用
                  </span>
                  <span v-else-if="tagMatch(topic.tag_slug)" class="match-badge match-ok" :title="tagMatch(topic.tag_slug)?.slug">
                    ✓ {{ tagMatch(topic.tag_slug)?.label_zh || tagMatch(topic.tag_slug)?.l3_topic }}
                  </span>
                  <span v-else class="match-badge match-error" title="標籤庫中找不到此 slug">
                    ⚠ 標籤庫中找不到
                  </span>
                </td>
                <td>{{ topic.label_zh || '—' }}</td>
                <td style="color:var(--text-secondary);font-size:13px;">{{ topic.rationale || '—' }}</td>
                <td>
                  <button class="btn-icon-danger" :disabled="deletingTopicId === topic.id" @click="deleteTopic(topic.id)">
                    <span v-if="deletingTopicId === topic.id">...</span>
                    <span v-else>刪除</span>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- 新增 SASB 必調 Topic Modal -->
    <div v-if="showAddTopicModal" class="modal-overlay" @click.self="showAddTopicModal = false">
      <div class="modal-box">
        <h3 class="modal-title">新增 SASB 必調 Topic</h3>
        <div class="form-group">
          <label class="form-label">SASB 產業代碼</label>
          <input v-model="addForm.sasb_industry_code" class="form-input font-mono" placeholder="例：EM-IS" maxlength="20" />
        </div>
        <div class="form-group">
          <label class="form-label">ESG L3 Tag Slug</label>
          <input v-model="addForm.tag_slug" class="form-input font-mono" placeholder="例：esg.env.ghg_emission" maxlength="80" />
          <p v-if="addForm.tag_slug" class="form-hint" :class="{ 'form-hint-warn': !tagMatch(addForm.tag_slug) }">
            <template v-if="tagMatch(addForm.tag_slug)">✓ 對應標籤庫：{{ tagMatch(addForm.tag_slug)?.label_zh || tagMatch(addForm.tag_slug)?.l3_topic }}</template>
            <template v-else>⚠ 標籤庫中找不到此 slug，請確認輸入是否正確</template>
          </p>
        </div>
        <div class="form-group">
          <label class="form-label">說明（選填）</label>
          <textarea v-model="addForm.rationale" class="form-input" rows="2" placeholder="為何此 topic 對此產業為必調..."></textarea>
        </div>
        <div v-if="addError" class="error-msg">{{ addError }}</div>
        <div class="modal-actions">
          <button class="btn btn-secondary" @click="showAddTopicModal = false">取消</button>
          <button class="btn btn-primary" :disabled="isAddingTopic || !addForm.sasb_industry_code || !addForm.tag_slug" @click="submitAddTopic">
            <span v-if="isAddingTopic">新增中...</span>
            <span v-else>新增</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { sasbRequiredTopicsApi } from '@/api/modules/saq'
import { tagLibraryApi } from '@/api/modules/settings'

export default {
  name: 'SasbRequiredTopicPanel',

  data() {
    return {
      isLoadingTopics: false,
      sasbTopics: {},
      expandedCodes: {},
      showAddTopicModal: false,
      isAddingTopic: false,
      deletingTopicId: null,
      addError: '',
      addForm: {
        sasb_industry_code: '',
        tag_slug: '',
        rationale: '',
      },
      tagsBySlug: {},
    }
  },

  mounted() {
    this.loadTopics()
    this.loadTagLibrary()
  },

  methods: {
    async loadTopics() {
      this.isLoadingTopics = true
      try {
        const res = await sasbRequiredTopicsApi.getAll()
        this.sasbTopics = res.data.data || {}
      } finally {
        this.isLoadingTopics = false
      }
    },

    async loadTagLibrary() {
      const res = await tagLibraryApi.list({ include_deprecated: true })
      const map = {}
      for (const tag of res.data.data || []) {
        map[tag.slug] = tag
      }
      this.tagsBySlug = map
    },

    tagMatch(slug) {
      return this.tagsBySlug[slug] || null
    },

    toggleCode(code) {
      this.expandedCodes = { ...this.expandedCodes, [code]: !this.expandedCodes[code] }
    },

    openAddTopic() {
      this.addForm = { sasb_industry_code: '', tag_slug: '', rationale: '' }
      this.addError = ''
      this.showAddTopicModal = true
    },

    async submitAddTopic() {
      this.addError = ''
      this.isAddingTopic = true
      try {
        await sasbRequiredTopicsApi.create(this.addForm)
        this.showAddTopicModal = false
        await this.loadTopics()
        const code = this.addForm.sasb_industry_code
        if (code) {
          this.expandedCodes = { ...this.expandedCodes, [code]: true }
        }
      } catch (e) {
        const errors = e?.response?.data?.errors
        if (errors) {
          this.addError = Object.values(errors).flat().join('；')
        } else {
          this.addError = e?.response?.data?.message || '新增失敗'
        }
      } finally {
        this.isAddingTopic = false
      }
    },

    async deleteTopic(id) {
      if (!confirm('確定刪除此必調設定？')) return
      this.deletingTopicId = id
      try {
        await sasbRequiredTopicsApi.delete(id)
        await this.loadTopics()
      } catch {
        alert('刪除失敗')
      } finally {
        this.deletingTopicId = null
      }
    },
  },
}
</script>

<style scoped>
.section-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 24px;
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 20px;
}

.section-title {
  font-size: 16px;
  font-weight: 600;
  color: var(--text-primary);
  margin: 0 0 4px;
}

.section-desc {
  font-size: 13px;
  color: var(--text-secondary);
  margin: 0;
}

.loading-state,
.empty-state {
  text-align: center;
  padding: 32px;
  color: var(--text-secondary);
}

.pillar-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}

.pillar-table th {
  text-align: left;
  padding: 8px 12px;
  border-bottom: 1px solid var(--border);
  font-weight: 500;
  color: var(--text-secondary);
  background: var(--surface-alt, #faf9f7);
}

.pillar-table td {
  padding: 10px 12px;
  border-bottom: 1px solid var(--border-light, #eeece8);
  vertical-align: middle;
}

.match-badge {
  display: inline-block;
  font-size: 12px;
  padding: 2px 8px;
  border-radius: 10px;
  white-space: nowrap;
}
.match-ok { background: #e6f4ec; color: #1a4d3e; border: 1px solid #b8e0c8; }
.match-warn { background: #fff4e0; color: #a05a00; border: 1px solid #f0d59c; }
.match-error { background: #fdecec; color: #c0392b; border: 1px solid #f5c6c6; }

/* SASB List */
.sasb-list { display: flex; flex-direction: column; gap: 8px; }

.sasb-group {
  border: 1px solid var(--border);
  border-radius: 6px;
  overflow: hidden;
}

.sasb-group-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 16px;
  cursor: pointer;
  background: var(--surface-alt, #faf9f7);
  transition: background 0.15s;
}

.sasb-group-header:hover { background: var(--surface-hover, #f0ede8); }

.sasb-code-info {
  display: flex;
  align-items: center;
  gap: 12px;
}

.sasb-count { font-size: 13px; color: var(--text-secondary); }

.expand-icon { font-size: 11px; color: var(--text-secondary); }

.sasb-topics { padding: 0; }

.btn-icon-danger {
  background: none;
  border: 1px solid #dc2626;
  color: #dc2626;
  padding: 3px 8px;
  border-radius: 4px;
  font-size: 12px;
  cursor: pointer;
  transition: all 0.15s;
}

.btn-icon-danger:hover:not(:disabled) { background: #dc2626; color: #fff; }
.btn-icon-danger:disabled { opacity: 0.5; cursor: not-allowed; }

/* Modal */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.4);
  z-index: 1000;
  display: flex;
  align-items: center;
  justify-content: center;
}

.modal-box {
  background: var(--surface);
  border-radius: 8px;
  padding: 28px;
  width: 480px;
  max-width: 90vw;
}

.modal-title {
  font-size: 16px;
  font-weight: 600;
  margin: 0 0 20px;
}

.form-group { margin-bottom: 16px; }
.form-label { display: block; font-size: 13px; font-weight: 500; margin-bottom: 6px; }
.form-input {
  width: 100%;
  padding: 8px 10px;
  border: 1px solid var(--border);
  border-radius: 4px;
  font-size: 13px;
  background: var(--surface);
  box-sizing: border-box;
}

.form-hint { font-size: 12px; color: var(--text-secondary); margin-top: 4px; }
.form-hint-warn { color: #c0392b; }

.error-msg {
  color: #dc2626;
  font-size: 13px;
  margin-bottom: 12px;
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  margin-top: 20px;
}

.btn-secondary {
  background: var(--surface);
  border: 1px solid var(--border);
  color: var(--text-primary);
  padding: 8px 16px;
  border-radius: 4px;
  cursor: pointer;
  font-size: 13px;
}
</style>
