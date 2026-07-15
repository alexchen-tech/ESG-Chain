## ADDED Requirements

### Requirement: 地緣事件建立
系統 SHALL 允許 admin/sustain 角色建立地緣事件，記錄影響供應商風險的外部事件（關稅、制裁、國家風險評等更新等），並自動計算受影響供應商清單。

受影響供應商計算規則：
- `affected_scope.country_codes` 不為空時：比對 `suppliers.country_code` 及 `supplier_facilities.country`（active 廠址）
- 結果去重，每家供應商只出現一次

#### Scenario: 建立地緣事件成功
- **WHEN** admin 送出 `POST /api/v1/risk/geo-events`，含 name、event_type、affected_scope、severity、occurred_at
- **THEN** 系統建立 `geo_events` 記錄（status 預設 active）
- **AND** 系統計算受影響供應商，為每家供應商建立 `geo_event_supplier_reviews` 記錄（status=pending，pre_e4_score 填入該供應商最新 RA 的 dim_e4）

#### Scenario: 影響範圍計算含廠址
- **WHEN** geo_event 的 affected_scope.country_codes 包含 'VN'
- **THEN** 所有 `suppliers.country_code = 'VN'` 的供應商 SHALL 納入受影響清單
- **AND** 所有在 VN 有 active 廠址的供應商（即使 HQ 不在 VN）也 SHALL 納入

#### Scenario: 供應商無最新 RA 時 pre_e4_score 為 null
- **WHEN** 受影響供應商沒有任何 risk_assessment 記錄
- **THEN** 建立 review 記錄時 pre_e4_score SHALL 為 null

---

### Requirement: 批次 E4 重算（Celery 排程）
系統 SHALL 支援對地緣事件觸發批次 E4 分數重算，計算由 esgchain-ai Celery task 非同步執行。

#### Scenario: 觸發批次重算
- **WHEN** admin 送出 `POST /api/v1/risk/geo-events/{id}/recalculate`
- **THEN** 系統更新所有 pending 的 geo_event_supplier_reviews 為 status=recalculating
- **AND** 系統對 esgchain-ai 送出非同步請求，含受影響供應商 IDs 與最新 country_defense_scores
- **AND** API 立即回傳 202 Accepted（不等待計算完成）

#### Scenario: Celery 計算完成後回調
- **WHEN** esgchain-ai 完成 recalculate_e4_batch 並呼叫 `POST /api/v1/risk/geo-events/{id}/review-callback`
- **THEN** Laravel SHALL 為每家供應商建立新的 RiskAssessment（source_type='geo_event'，source_id=geo_event_id）
- **AND** 更新對應 geo_event_supplier_reviews：status='done'，post_e4_score 填入新 dim_e4，risk_assessment_id 填入新建 RA 的 id
- **AND** RiskAssessmentObserver SHALL 對新 RA 執行 CAP 閾值檢查

#### Scenario: 重算超時保護
- **WHEN** geo_event_supplier_reviews.status='recalculating' 且 recalculation_started_at 超過 10 分鐘
- **THEN** 系統定期 job SHALL 將該記錄標記為 status='failed'，並記錄 error_message

---

### Requirement: 地緣事件複查清單 UI
系統 SHALL 提供地緣事件列表頁與詳情頁，顯示事件資訊及受影響供應商的複查狀態。

#### Scenario: 事件列表顯示
- **WHEN** 使用者進入 `/risk/geo-events`
- **THEN** 顯示地緣事件列表，含事件名稱、event_type、severity badge、受影響供應商數、複查進度（done/total）、occurred_at

#### Scenario: 事件詳情顯示受影響供應商
- **WHEN** 使用者點擊地緣事件進入詳情頁
- **THEN** 顯示受影響供應商清單表格，含供應商名稱、country_code、pre_e4_score、post_e4_score（計算後）、review_status badge
- **AND** 提供「批次重算 E4」按鈕（僅在有 pending 記錄時可點擊）
- **AND** 按鈕點擊後 disabled + loading，顯示「計算中...」狀態

#### Scenario: 重算進行中狀態顯示
- **WHEN** 部分 reviews 狀態為 recalculating
- **THEN** 顯示進度指示（例：「3 / 7 完成」）
- **AND** 頁面 SHALL 每 5 秒輪詢一次狀態更新（直到所有 review 為 done 或 failed）
