// 供應商名稱遮罩：只揭露前 50% 長度，其餘以「＊」取代，避免 demo 環境展示真實公司名稱
// 全系統畫面一律套用（列表/詳情/下拉選單/彈窗等），不分內部/外部使用者。
export function maskSupplierName(name: string | null | undefined): string {
  if (!name) return name ?? ''
  const chars = Array.from(name) // 用 code point 切，避免中文/emoji 被截半個字
  const visibleCount = Math.ceil(chars.length / 2)
  return chars
    .map((c, i) => (i < visibleCount ? c : '＊'))
    .join('')
}
