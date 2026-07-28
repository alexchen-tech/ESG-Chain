<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BatchExportReview extends Model
{
    use HasUuids;

    // 沿用 MarketComplianceRule::MARKETS 作為全站唯一權威市場代碼清單，
    // 避免批號審查與市場合規規則兩處對不上（曾發生過 GLOBAL vs APAC/NA 兜不起來的問題）
    public const MARKETS  = MarketComplianceRule::MARKETS;

    // 'pending' 僅為 DB 欄位 default（尚未曾執行過審查的理論狀態），
    // BatchExportReviewService::overallStatus() 只會產生 pass/warning/fail 三者，
    // 一筆 BatchExportReview 只要存在就一定已經跑過 overallStatus()，不會停在 pending。
    public const STATUSES = ['pending', 'pass', 'warning', 'fail'];

    protected $fillable = [
        'production_batch_id',
        'market',
        'program',
        'status',
        'findings',
        'reviewed_at',
    ];

    protected $casts = [
        'findings'    => 'array',
        'reviewed_at' => 'datetime',
    ];

    public function productionBatch(): BelongsTo
    {
        return $this->belongsTo(ProductionBatch::class);
    }
}
