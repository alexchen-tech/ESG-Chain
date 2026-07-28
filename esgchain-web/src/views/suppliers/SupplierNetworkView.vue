<template>
  <div class="page-container">
    <div class="page-header">
      <div>
        <h1 class="page-title">供應鏈連結圖</h1>
        <p class="page-subtitle">選擇一個銷售產品，由左至右點選節點展開 T1~T4 供應商層級</p>
      </div>
    </div>

    <div class="toolbar">
      <select v-model="selectedProductId" class="filter-select" @change="onProductChange">
        <option value="" disabled>請選擇銷售產品…</option>
        <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}（{{ p.product_code || '無代碼' }}）</option>
      </select>
      <div class="legend">
        <span class="legend-item"><span class="legend-dot dot-supplier"></span>供應商</span>
        <span class="legend-item"><span class="legend-dot dot-material"></span>物料</span>
        <span class="legend-item"><span class="legend-dot dot-product"></span>銷售產品</span>
        <span class="legend-hint">點選節點可展開／收合下一層</span>
      </div>
    </div>

    <div v-if="isLoadingProducts" class="loading-mask" style="padding:40px;text-align:center;">載入中...</div>

    <div v-else-if="!selectedProductId" class="empty-state">
      <div class="empty-icon">🔗</div>
      <p>請先選擇一個銷售產品</p>
    </div>

    <template v-else>
      <div v-if="isLoadingGraph" class="loading-mask" style="padding:40px;text-align:center;">載入中...</div>

      <template v-else>
        <div class="graph-layout">
          <div class="graph-container-wrap">
            <div ref="graphContainer" class="graph-container"></div>
            <div class="zoom-controls">
              <button class="zoom-btn" title="放大" @click="zoomBy(1.25)">＋</button>
              <button class="zoom-btn" title="縮小" @click="zoomBy(0.8)">－</button>
              <button class="zoom-btn zoom-btn-reset" title="重設視角" @click="fitGraph">⤢</button>
            </div>
            <div class="zoom-hint">滑鼠滾輪／雙指可縮放，拖曳可平移</div>
          </div>

          <div class="detail-panel">
            <div v-if="!selectedNode" class="empty-hint">點選圖中的節點以查看詳情</div>
            <template v-else>
              <div class="detail-type-badge" :class="'badge-' + selectedNode.type">{{ typeLabel(selectedNode.type) }}</div>
              <div class="detail-name">{{ selectedNode.label }}</div>
              <div v-if="selectedNode.type === 'supplier'" class="detail-fields">
                <div v-if="selectedNode.code">代碼：{{ selectedNode.code }}</div>
                <div>供應商層級：{{ selectedNode.tier ? `T${selectedNode.tier}` : '未分級' }}</div>
                <div v-if="selectedNode.country">國別：{{ selectedNode.country }}</div>
                <button class="btn btn-primary btn-sm" style="margin-top:10px;" @click="goToSupplier(selectedNode)">
                  查看供應商詳情
                </button>
              </div>
              <div v-else class="detail-fields">
                <div v-if="selectedNode.code">代碼：{{ selectedNode.code }}</div>
                <div v-if="childMap[selectedNode.id]?.length">
                  點選節點以{{ expandedIds.has(selectedNode.id) ? '收合' : '展開' }}下一層（共 {{ childMap[selectedNode.id]?.length ?? 0 }} 項）
                </div>
              </div>
            </template>
          </div>
        </div>
      </template>
    </template>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { useRouter } from 'vue-router'
import cytoscape, { type Core } from 'cytoscape'
import { supplierNetworkApi, type NetworkNode, type NetworkEdge } from '@/api/modules/supplierNetwork'
import { salesProductApi, type SalesProduct } from '@/api/modules/salesProducts'

const TYPE_LABELS: Record<string, string> = {
  supplier: '供應商',
  material: '物料',
  product: '銷售產品',
}

const COL_WIDTH = 215
const ROW_HEIGHT = 80

// 由左到右：產品 → 物料 → T1 → T2 → T3 → T4 → 未分級，跟 buildGraphModel() 的欄位算法一致
const HEADER_LABELS: Record<number, string> = {
  0: '銷售產品', 1: '物料', 2: 'T1', 3: 'T2', 4: 'T3', 5: 'T4', 6: '未分級',
}

export default defineComponent({
  name: 'SupplierNetworkView',
  setup() { return { router: useRouter() } },

  data() {
    return {
      isLoadingProducts: false,
      isLoadingGraph: false,
      products: [] as SalesProduct[],
      selectedProductId: '',
      nodes: [] as NetworkNode[],
      edges: [] as NetworkEdge[],
      nodesById: {} as Record<string, NetworkNode>,
      childMap: {} as Record<string, string[]>,
      parentMap: {} as Record<string, string>,
      positions: {} as Record<string, { x: number; y: number }>,
      visibleNodeIds: new Set<string>(),
      visibleEdgeKeys: new Set<string>(),
      expandedIds: new Set<string>(),
      selectedNode: null as NetworkNode | null,
      cy: null as Core | null,
    }
  },

  async mounted() {
    this.isLoadingProducts = true
    try {
      const { data } = await salesProductApi.search('')
      this.products = data.data
    } finally {
      this.isLoadingProducts = false
    }
  },

  beforeUnmount() {
    this.cy?.destroy()
  },

  methods: {
    async onProductChange() {
      this.selectedNode = null
      this.cy?.destroy()
      this.cy = null
      if (!this.selectedProductId) return

      this.isLoadingGraph = true
      try {
        const { data } = await supplierNetworkApi.graph(this.selectedProductId)
        this.nodes = data.data.nodes
        this.edges = data.data.edges
      } finally {
        this.isLoadingGraph = false
      }
      await this.$nextTick()
      this.buildGraphModel()
      this.initGraph()
    },

    supplierColIndex(tier: number | null | undefined): number {
      if (tier && tier >= 1 && tier <= 4) return 1 + tier // T1=2, T2=3, T3=4, T4=5
      return 6 // 未分級
    },

    buildGraphModel() {
      this.nodesById = {}
      for (const n of this.nodes) this.nodesById[n.id] = n

      // 子節點關係：product 的子節點是連到它的 material；material 的子節點是連到它的 supplier
      const childMap: Record<string, string[]> = {}
      const parentMap: Record<string, string> = {}
      for (const e of this.edges) {
        const targetNode = this.nodesById[e.target]
        if (targetNode && (targetNode.type === 'product' || targetNode.type === 'material')) {
          if (!childMap[e.target]) childMap[e.target] = []
          childMap[e.target]!.push(e.source)
          parentMap[e.source] = e.target
        }
      }
      this.childMap = childMap
      this.parentMap = parentMap

      const materials = this.nodes.filter(n => n.type === 'material')
      const suppliersByCol: Record<number, NetworkNode[]> = { 2: [], 3: [], 4: [], 5: [], 6: [] }
      for (const n of this.nodes) {
        if (n.type === 'supplier') suppliersByCol[this.supplierColIndex(n.tier)]!.push(n)
      }

      const positions: Record<string, { x: number; y: number }> = {}
      const product = this.nodes.find(n => n.type === 'product')
      if (product) positions[product.id] = { x: 0, y: 200 }
      materials.forEach((n, i) => { positions[n.id] = { x: 1 * COL_WIDTH, y: i * ROW_HEIGHT + 40 } })
      for (const col of Object.keys(suppliersByCol)) {
        suppliersByCol[Number(col)]!.forEach((n, i) => {
          positions[n.id] = { x: Number(col) * COL_WIDTH, y: i * ROW_HEIGHT + 40 }
        })
      }
      this.positions = positions

      this.visibleNodeIds = new Set()
      this.visibleEdgeKeys = new Set()
      this.expandedIds = new Set()
    },

    initGraph() {
      const product = this.nodes.find(n => n.type === 'product')
      if (!product) return

      this.cy = cytoscape({
        container: this.$refs.graphContainer as HTMLElement,
        elements: [],
        style: [
          {
            selector: 'node',
            style: {
              'label': 'data(label)',
              'shape': 'round-rectangle',
              'font-size': '15px',
              'text-valign': 'center',
              'text-halign': 'center',
              'text-wrap': 'wrap',
              'text-max-width': '148px',
              'text-overflow-wrap': 'anywhere',
              'color': '#1f2937',
              'line-height': 1.35,
              // 寬度固定，確保同一欄位永遠對齊；高度改依換行後的文字自動撐開，
              // 避免文字行數超過框高時彼此重疊、糊成一團（曾發生過的問題）
              'width': 170,
              'height': 'label',
              'padding': '8px',
              'border-width': 2.5,
            },
          },
          { selector: 'node[type="supplier"]', style: { 'background-color': '#dbeafe', 'border-color': '#2563eb' } },
          { selector: 'node[type="material"]', style: { 'background-color': '#fef3c7', 'border-color': '#d97706' } },
          { selector: 'node[type="product"]', style: { 'background-color': '#dcfce7', 'border-color': '#16a34a', 'font-weight': 'bold' } },
          { selector: 'node[?hasChildren]', style: { 'border-style': 'dashed' } },
          { selector: 'node[?expanded]', style: { 'border-style': 'solid' } },
          {
            // 欄位標題節點：跟資料節點畫在同一個畫布座標系裡，縮放/平移時永遠對得上同一欄，
            // 不會像先前「HTML 表頭 + 獨立可平移畫布」那樣，fit() 一縮放欄位就對不齊
            selector: 'node[?isHeader]',
            style: {
              'shape': 'round-rectangle',
              'background-opacity': 0,
              'border-width': 0,
              'font-size': '16px',
              'font-weight': 'bold',
              'color': '#6b7280',
              'text-valign': 'center',
              'text-halign': 'center',
              'width': 170,
              'height': 24,
            },
          },
          {
            // 心智圖風格連接線：柔和曲線＋淡色，貼近節點顏色而非死板直線
            selector: 'edge',
            style: {
              'width': 2.5,
              'line-color': '#c7d2e0',
              'curve-style': 'unbundled-bezier',
              'control-point-distances': [20],
              'control-point-weights': [0.5],
              'target-arrow-shape': 'none',
              'opacity': 0.7,
            },
          },
          // node/edge 分開設定，避免 edge 的線寬 'width' 誤蓋到 node 的方框寬度（曾導致選取節點方框大小跑掉）
          { selector: 'node.highlighted', style: { 'background-color': '#fee2e2', 'border-color': '#dc2626', 'border-width': 3.5, 'opacity': 1, 'z-index': 999 } },
          { selector: 'edge.highlighted', style: { 'line-color': '#dc2626', 'opacity': 1, 'width': 3, 'z-index': 999 } },
          { selector: '.dimmed', style: { 'opacity': 0.1 } },
        ],
        layout: { name: 'preset' },
        zoomingEnabled: true,
        userZoomingEnabled: true,
        // 節點很少時（例如剛選好產品、只有一個節點）fit() 會把畫面放到超大填滿容器，
        // 導致文字/框體大小失控；限制 fit() 最多只能放大到這個倍率
        maxZoom: 1.3,
      })

      // 欄位標題：product=0, material=1, T1=2, T2=3, T3=4, T4=5, 未分級=6，跟 buildGraphModel() 的欄位算法一致
      // 只先放「銷售產品／物料」兩欄標題；T1~未分級要等真的展開出該欄的節點才出現，
      // 避免一開始就把整個畫面撐到最右邊的空欄，看起來很空
      this.ensureHeaderForColumn(0)
      this.ensureHeaderForColumn(1)

      this.addNode(product.id)
      this.fitGraph()

      this.cy.on('tap', 'node[!isHeader]', (evt) => this.onNodeTap(evt.target.id()))
      this.cy.on('tap', (evt) => { if (evt.target === this.cy) this.clearHighlight() })
    },

    nodeLabel(node: NetworkNode): string {
      const children = this.childMap[node.id]
      if (!children || !children.length) return node.label
      if (this.expandedIds.has(node.id)) return node.label
      return `${node.label}（${children.length}）`
    },

    ensureHeaderForColumn(colIndex: number) {
      if (!this.cy) return
      const hid = `header:${colIndex}`
      if (this.visibleNodeIds.has(hid)) return
      this.cy.add({
        data: { id: hid, label: HEADER_LABELS[colIndex] ?? '', isHeader: true },
        position: { x: colIndex * COL_WIDTH, y: -40 },
        locked: true,
        grabbable: false,
        selectable: false,
      })
      this.visibleNodeIds.add(hid)
    },

    addNode(id: string) {
      if (!this.cy || this.visibleNodeIds.has(id)) return
      const node = this.nodesById[id]
      if (!node) return
      const pos = this.positions[node.id] ?? { x: 0, y: 0 }
      this.ensureHeaderForColumn(Math.round(pos.x / COL_WIDTH))
      this.cy.add({
        data: {
          id: node.id,
          baseLabel: node.label,
          label: this.nodeLabel(node),
          type: node.type,
          hasChildren: !!this.childMap[node.id]?.length,
          expanded: this.expandedIds.has(node.id),
        },
        position: pos,
      })
      this.visibleNodeIds.add(id)
    },

    addEdge(sourceId: string, targetId: string) {
      if (!this.cy) return
      const key = `${sourceId}->${targetId}`
      if (this.visibleEdgeKeys.has(key)) return
      this.cy.add({ data: { source: sourceId, target: targetId } })
      this.visibleEdgeKeys.add(key)
    },

    expand(id: string) {
      const children = this.childMap[id]
      if (!children || !children.length || !this.cy) return
      this.expandedIds.add(id)
      this.cy.getElementById(id).data('expanded', true)
      this.cy.getElementById(id).data('label', this.nodeLabel(this.nodesById[id]!))
      for (const childId of children) {
        this.addNode(childId)
        this.addEdge(childId, id)
      }
    },

    collapse(id: string) {
      const children = this.childMap[id]
      if (!children || !this.cy) return
      for (const childId of children) {
        // 若子節點自己也展開了，先收合它，避免留下孤兒節點
        if (this.expandedIds.has(childId)) this.collapse(childId)
        this.cy.getElementById(childId).remove()
        this.visibleNodeIds.delete(childId)
        this.visibleEdgeKeys.delete(`${childId}->${id}`)
      }
      this.expandedIds.delete(id)
      const ele = this.cy.getElementById(id)
      if (ele.length) {
        ele.data('expanded', false)
        ele.data('label', this.nodeLabel(this.nodesById[id]!))
      }
    },

    onNodeTap(id: string) {
      this.selectNode(id)
      const children = this.childMap[id]
      if (!children || !children.length) return
      if (this.expandedIds.has(id)) {
        this.collapse(id)
      } else {
        this.expand(id)
      }
      this.fitGraph()
    },

    fitGraph() {
      if (!this.cy) return
      this.cy.fit(undefined, 40)
      // fit() 預設會把內容整個置中（水平、垂直都置中），畫面每次展開新的一欄，
      // 置中點就跟著整個位移，導致最左邊的節點被推出畫布外看起來像被裁切。
      // 改成固定靠左上角對齊：只在上緣、左緣留固定邊距，往右、往下長，
      // 不管展開到第幾欄，最左邊（銷售產品欄）永遠停在同一個位置
      const bb = this.cy.elements().boundingBox()
      const zoom = this.cy.zoom()
      const topPadding = 30
      const leftPadding = 30
      this.cy.pan({ x: leftPadding - bb.x1 * zoom, y: topPadding - bb.y1 * zoom })
    },

    zoomBy(factor: number) {
      if (!this.cy) return
      const container = this.$refs.graphContainer as HTMLElement
      const center = { x: container.clientWidth / 2, y: container.clientHeight / 2 }
      this.cy.zoom({ level: this.cy.zoom() * factor, renderedPosition: center })
    },

    selectNode(id: string) {
      const node = this.nodesById[id] ?? null
      this.selectedNode = node
      if (!this.cy) return

      this.cy.elements().removeClass('highlighted dimmed')
      const target = this.cy.getElementById(id)
      let highlighted = target.closedNeighborhood()

      // 往上一路連回銷售產品，保留完整鏈結（不會只剩點到的節點跟它的直接鄰居，中間斷開）
      let currentId = id
      while (this.parentMap[currentId]) {
        const parentId = this.parentMap[currentId]!
        const parentEle = this.cy.getElementById(parentId)
        const edgeEle = this.cy.getElementById(currentId).edgesWith(parentEle)
        highlighted = highlighted.union(parentEle).union(edgeEle)
        currentId = parentId
      }

      // 欄位標題節點不屬於任何節點的鄰居，但也不該被跟著淡化，維持隨時可讀
      this.cy.elements().not('[?isHeader]').difference(highlighted).addClass('dimmed')
      highlighted.addClass('highlighted')
    },

    clearHighlight() {
      this.selectedNode = null
      this.cy?.elements().removeClass('highlighted dimmed')
    },

    goToSupplier(node: NetworkNode) {
      if (node.ref_id) this.router.push(`/suppliers/${node.ref_id}`)
    },

    typeLabel(t: string) { return TYPE_LABELS[t] ?? t },
  },
})
</script>

<style scoped>
.toolbar { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 12px; flex-wrap: wrap; }
.legend { display: flex; align-items: center; gap: 14px; font-size: 14px; color: var(--text-secondary); }
.legend-item { display: flex; align-items: center; gap: 5px; }
.legend-hint { color: var(--text-secondary); font-style: italic; }
.legend-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
.dot-supplier { background: #2563eb; }
.dot-material { background: #d97706; }
.dot-product { background: #16a34a; }

.graph-layout { display: flex; gap: 16px; }
.graph-container-wrap { position: relative; flex: 1 1 auto; min-width: 0; }
.graph-container {
  width: 100%; height: 640px; background: var(--surface); border: 1px solid var(--border); border-radius: 8px;
}
.zoom-controls {
  position: absolute; top: 12px; right: 12px; display: flex; flex-direction: column; gap: 4px;
  background: var(--surface); border: 1px solid var(--border); border-radius: 8px; padding: 4px; box-shadow: 0 1px 4px rgba(0,0,0,.08);
}
.zoom-btn {
  width: 32px; height: 32px; border: none; background: var(--surface-2); border-radius: 6px; cursor: pointer;
  font-size: 16px; font-weight: 700; color: var(--text-primary); line-height: 1;
}
.zoom-btn:hover { background: var(--accent); color: #fff; }
.zoom-btn-reset { font-size: 14px; }
.zoom-hint {
  position: absolute; bottom: 10px; right: 12px; font-size: 12px; color: var(--text-secondary);
  background: var(--surface); padding: 3px 8px; border-radius: 4px; opacity: .75;
}
.detail-panel {
  flex: 0 0 340px; width: 340px; min-width: 340px; background: var(--surface); border: 1px solid var(--border);
  border-radius: 8px; padding: 20px;
}
.empty-hint { font-size: 15px; color: var(--text-secondary); }
.detail-type-badge {
  display: inline-block; font-size: 13px; font-weight: 600; padding: 3px 10px; border-radius: 4px; margin-bottom: 10px;
}
.badge-supplier { background: #dbeafe; color: #1d4ed8; }
.badge-material { background: #fef3c7; color: #b45309; }
.badge-product { background: #dcfce7; color: #15803d; }
.detail-name { font-size: 19px; font-weight: 700; color: var(--text-primary); margin-bottom: 12px; line-height: 1.4; }
.detail-fields { font-size: 15px; color: var(--text-secondary); display: flex; flex-direction: column; gap: 8px; line-height: 1.5; }
</style>
