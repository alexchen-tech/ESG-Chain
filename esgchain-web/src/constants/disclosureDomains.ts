// 永續 KPI 揭露欄位（supplier_disclosure_fields.slug）的領域前綴中文對照，
// 供應商 Portal 填報頁與中心廠端「永續績效」分頁共用同一份，避免各自維護
// 一份跟資料庫實際 slug 前綴（cert/diversity/energy/ghg/governance/labor/
// safety/supply_chain/waste/water）對不上而各自長歪。
export const DISCLOSURE_PREFIX_LABELS: Record<string, string> = {
  cert: '認證資格',
  ghg: '溫室氣體排放',
  energy: '能源使用',
  labor: '勞動權益',
  water: '水資源',
  waste: '廢棄物',
  governance: '公司治理',
  diversity: '多元共融',
  supply_chain: '供應鏈管理',
  safety: '職業安全',
}
