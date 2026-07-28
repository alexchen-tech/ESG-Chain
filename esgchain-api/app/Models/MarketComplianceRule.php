<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MarketComplianceRule extends Model
{
    use HasUuids;

    /**
     * 全站唯一權威市場代碼清單，供出口市場審查（BatchExportReview::MARKETS 沿用同一份）、
     * 市場合規規則表單共用，避免各處各自維護一份寫死陣列而逐漸長歪
     * （曾發生過 UK vs GB、GLOBAL vs APAC/NA 兩處對不上的問題）。
     */
    public const MARKETS = ['EU', 'US', 'UK', 'JP', 'APAC', 'NA'];

    // 法規範疇：供「執行審查」篩選只跑哪個範疇的檢查項目；general 為各市場通用文件
    // （SDS/ORIGIN_CERT/CPSIA 等），不屬於特定專案法規
    public const PROGRAMS = ['general', 'dpp', 'cbam', 'eudr', 'uflpa'];

    protected $fillable = [
        'market',
        'doc_type',
        'program',
        'scope',
        'is_mandatory',
        'effective_from',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'is_mandatory'   => 'boolean',
        'is_active'      => 'boolean',
        'effective_from' => 'date',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
                     ->where('effective_from', '<=', now()->toDateString());
    }

    public function scopeForMarket(Builder $query, string $market): Builder
    {
        return $query->where('market', $market);
    }
}
