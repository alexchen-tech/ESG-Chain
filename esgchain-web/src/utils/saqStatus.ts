export type SaqStatus =
  | 'sent'
  | 'in_progress'
  | 'submitted'
  | 'under_review'
  | 'review_returned'
  | 'completed'
  | 'reviewed'

export const SAQ_STATUS_LABEL: Record<string, string> = {
  sent:            '待填寫',
  in_progress:     '填寫中',
  submitted:       '待審核',
  under_review:    '審核中',
  review_returned: '已退回',
  completed:       '審核完成',
  reviewed:        '已複核',
}

export const SAQ_STATUS_BADGE: Record<string, string> = {
  sent:            'badge-gray',
  in_progress:     'badge-yellow',
  submitted:       'badge-blue',
  under_review:    'badge-purple',
  review_returned: 'badge-orange',
  completed:       'badge-green',
  reviewed:        'badge-green',
}
