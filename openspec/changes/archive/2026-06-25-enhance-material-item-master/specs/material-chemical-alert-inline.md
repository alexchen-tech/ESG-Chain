target: openspec/specs/material-chemical-alert-inline/spec.md
action: create

---

## Capability: 化學合規掃描結果內嵌（material-chemical-alert-inline）

將 `ChemicalComplianceAlert` 的掃描結果顯示於料號詳情頁的化學組成 Tab，使操作者在填報 CAS 成分後可即時查看合規風險，不需跳轉至其他頁面。

### Background

`ChemicalComplianceScanJob` 掃描後將結果寫入 `ChemicalComplianceAlert`（alert_level: info/warning/critical，regulated_list: reach_svhc/rohs）。既有 API `GET /api/v1/chemical-compliance-alerts?material_item_id={id}` 已支援此 filter，無需新增後端 endpoint。

### Requirement: 化學組成 Tab 顯示合規掃描結果

#### Scenario: 進入化學組成 Tab
- **WHEN** 使用者切換到「化學組成」Tab
- **THEN** 同步呼叫 `GET /api/v1/chemical-compliance-alerts?material_item_id={id}&per_page=50`；化學成分表與合規掃描結果同步顯示於同一頁面

#### Scenario: 無受管制物質
- **WHEN** ChemicalComplianceAlert 查詢回傳空陣列
- **THEN** 合規掃描結果區顯示「✓ 未偵測到受管制物質」（綠色）

#### Scenario: 有合規警告
- **WHEN** ChemicalComplianceAlert 查詢回傳一或多筆
- **THEN** 每筆 alert 以行列（alert-row）顯示：regulated_list badge（REACH SVHC / RoHS）+ 物質名稱 + restriction_notes（截斷顯示）；背景色依 alert_level：critical→淡紅、warning→淡橘、info→淡藍

#### Scenario: alert_level 視覺規則
| alert_level | 背景色  | tag 色  |
|-------------|---------|---------|
| critical    | #fef2f2 | 紅（#dc2626） |
| warning     | #fffbeb | 橘（#d97706） |
| info        | #eff6ff | 藍（#1d4ed8） |

#### Scenario: 重新掃描
- **WHEN** 使用者點擊「重新掃描」按鈕
- **THEN** 呼叫 `POST /api/v1/material-items/{id}/chemical-compliance-scan`；按鈕 disabled + 顯示「掃描中...」；掃描完成後重新取得 alert 清單並更新顯示

#### Scenario: 新增化學成分後掃描結果
- **WHEN** 使用者新增 CAS 成分並手動點擊「重新掃描」
- **THEN** 掃描結果反映最新化學成分清單的合規風險

### Requirement: 合規 badge 顏色規範（全域）
以下 CSS class 供所有頁面使用化學合規結果時統一引用：
- `.alert-critical` — 背景 `#fef2f2`
- `.alert-warning` — 背景 `#fffbeb`
- `.alert-info` — 背景 `#eff6ff`
- `.alert-tag` — 受管制清單標籤（font-size 11px，rounded 4px）
