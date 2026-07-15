export interface BankFilterPayload {
  keyword: string
  l1?: string
  l2?: string
  l3?: string
}

export function applyBankFilter<T extends {
  question_text: string
  question_tags?: Array<{ l1_domain: string; l2_pillar: string; slug: string }>
}>(
  items: T[],
  { keyword, l1, l2, l3 }: BankFilterPayload,
): T[] {
  const kw = keyword.trim().toLowerCase()
  return items.filter(item => {
    if (kw && !item.question_text.toLowerCase().includes(kw)) return false
    if (l1 && !item.question_tags?.some(t => t.l1_domain === l1)) return false
    if (l2 && !item.question_tags?.some(t => t.l2_pillar === l2)) return false
    if (l3 && !item.question_tags?.some(t => t.slug === l3)) return false
    return true
  })
}
