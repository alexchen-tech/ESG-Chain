## ADDED Requirements

### Requirement: ERP 合規標記回寫

`ErpAdapterInterface` 新增兩個回寫方法，讓 ESG-Chain 的合規結論可以推送回 ERP 系統，實現雙向整合。

```php
// 回寫合規標記至 ERP 物料主檔
public function pushComplianceTag(string $erpCode, array $tags): bool;

// 在 ERP 鎖定物料採購（危險物質確認前禁採）
public function lockMaterial(string $erpCode, string $reason): bool;
```

具體 Adapter 實作留空（回傳 false + 記錄 Log::warning），由各 ERP 整合 Change 覆寫。

#### Scenario: 化學合規警示確認後回寫 ERP

- **WHEN** `ChemicalComplianceAlert` status → `acknowledged`，且 `alert_level = 'critical'`
- **THEN** 系統呼叫 `ErpAdapter::pushComplianceTag(materialItem.item_code, ['REACH_SVHC'])`；若 Adapter 未實作，記錄 warning log 不拋例外

#### Scenario: 嚴重違規自動鎖定物料

- **WHEN** 掃描發現 critical 警示且物料未鎖定
- **THEN** 系統呼叫 `ErpAdapter::lockMaterial(erpCode, reason)`；若 Adapter 未實作，在 ESG-Chain 側記錄「待 ERP 鎖定」狀態，顯示於物料詳情頁 banner

#### Scenario: 回寫失敗不阻塞流程

- **WHEN** `pushComplianceTag()` 或 `lockMaterial()` 拋出例外
- **THEN** 例外被 catch，記錄至 `erp_sync_logs`（新增 direction='outbound' 欄位），不影響 ESG-Chain 端狀態更新
