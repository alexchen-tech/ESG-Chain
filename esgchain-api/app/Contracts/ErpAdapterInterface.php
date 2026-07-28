<?php

namespace App\Contracts;

interface ErpAdapterInterface
{
    /**
     * 拉取供應商清單（增量）
     * @param string|null $since ISO8601 時間戳，null 表示全量
     * @return array<int, array<string, mixed>>
     */
    public function fetchSuppliers(?string $since = null): array;

    /**
     * 拉取物料清單（增量）
     * @param string|null $since ISO8601 時間戳，null 表示全量
     * @return array<int, array<string, mixed>>
     */
    public function fetchMaterials(?string $since = null): array;

    /**
     * 拉取銷售產品清單（增量）
     * @param string|null $since ISO8601 時間戳，null 表示全量
     * @return array<int, array<string, mixed>>
     */
    public function fetchProducts(?string $since = null): array;

    /**
     * 拉取 BOM 行（增量，依產品過濾或全量）
     * @param string|null $since ISO8601 時間戳
     * @param string|null $productCode 限定產品編碼
     * @return array<int, array<string, mixed>>
     */
    public function fetchBomLines(?string $since = null, ?string $productCode = null): array;

    /**
     * 將化學合規標籤推回 ERP（REACH/RoHS 狀態）
     * @param string $materialCode ERP 物料編碼
     * @param string $regulatedList 法規清單（reach_svhc / rohs）
     * @param string $status compliant | non_compliant | under_review
     * @return bool 推送成功回傳 true；ERP 不支援則回傳 false
     */
    public function pushComplianceTag(string $materialCode, string $regulatedList, string $status): bool;

    /**
     * 請求 ERP 鎖定物料（禁止新採購訂單）
     * @param string $materialCode ERP 物料編碼
     * @param string $reason 鎖定原因說明
     * @return bool 鎖定成功回傳 true；ERP 不支援則回傳 false
     */
    public function lockMaterial(string $materialCode, string $reason): bool;
}
