# Design: Frontend P2 — 系統設定頁面

## SettingsView 版面

```
/settings
══════════════════════════════════════════════════════

[問卷範本] [供應商分組] [SASB 分類]   ← Tabs

Tab 1: 問卷範本
  ┌────────────────────────────────────────────────────┐
  │  + 新增範本                                        │
  │ ─────────────────────────────────────────────────  │
  │  名稱              版本   產業     狀態   操作      │
  │  2025 T1 ESG 評估  3.0.0  通用     ● 啟用  [刪]    │
  │  2024 T1 ESG 評估  2.1.0  TC-ES   ○ 停用  [刪]    │
  └────────────────────────────────────────────────────┘

Tab 2: 供應商分組
  ┌────────────────────────────────────────────────────┐
  │  + 新增分組                                        │
  │  T1 關鍵供應商      3 家        [編輯] [刪]        │
  └────────────────────────────────────────────────────┘

Tab 3: SASB 分類
  搜尋：[             ]
  ▶ Technology & Communications  (6)
  ▼ Extractives & Minerals Processing  (8)
    TC-ES  Electronic Manufacturing Services...
    TC-HW  Hardware
    ...
```

---

## api/modules/settings.ts

```typescript
export const settingsApi = {
  templates: {
    list: (params?) => http.get('/api/v1/settings/questionnaire-templates', { params }),
    create: (data) => http.post('/api/v1/settings/questionnaire-templates', data),
    update: (id, data) => http.put(`/api/v1/settings/questionnaire-templates/${id}`, data),
    delete: (id) => http.delete(`/api/v1/settings/questionnaire-templates/${id}`),
  },
  groups: {
    list: () => http.get('/api/v1/settings/supplier-groups'),
    create: (data) => http.post('/api/v1/settings/supplier-groups', data),
    update: (id, data) => http.put(`/api/v1/settings/supplier-groups/${id}`, data),
    delete: (id) => http.delete(`/api/v1/settings/supplier-groups/${id}`),
  },
  sasb: {
    list: (sector?) => http.get('/api/v1/settings/sasb-industries',
      { params: sector ? { sector } : {} }),
  },
}
```

---

## SASB 摺疊展開邏輯

```javascript
data() {
  return {
    industries: [],       // 全部資料
    expandedSectors: {},  // { 'Technology & Communications': true }
    search: '',
  }
},
computed: {
  groupedBySector() {
    const filtered = this.industries.filter(i =>
      !this.search ||
      i.sector.toLowerCase().includes(this.search.toLowerCase()) ||
      i.industry.toLowerCase().includes(this.search.toLowerCase())
    )
    return filtered.reduce((acc, item) => {
      if (!acc[item.sector]) acc[item.sector] = []
      acc[item.sector].push(item)
      return acc
    }, {})
  }
}
```
