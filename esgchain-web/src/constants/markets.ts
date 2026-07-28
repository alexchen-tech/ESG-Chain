// 全站唯一權威市場代碼清單，對應後端 App\Models\MarketComplianceRule::MARKETS
// （BatchExportReview::MARKETS 沿用同一份）。批號審查、市場合規規則設定頁共用，
// 不可各自維護一份寫死陣列（曾發生過 UK vs GB、GLOBAL vs APAC/NA 兩處對不上的問題）。
export const EXPORT_MARKETS = ['EU', 'US', 'UK', 'JP', 'APAC', 'NA'] as const

export type ExportMarket = typeof EXPORT_MARKETS[number]

export const MARKET_LABELS: Record<ExportMarket, string> = {
  EU: 'EU 歐盟',
  US: 'US 美國',
  UK: 'UK 英國',
  JP: 'JP 日本',
  APAC: 'APAC 亞太',
  NA: 'NA 北美',
}
