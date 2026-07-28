<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesProduct extends Model
{
    use HasUuids, HasFactory, SoftDeletes;

    protected $table = 'sales_products';

    protected $fillable = [
        'name', 'product_code', 'model_no', 'hs_code', 'description', 'unit',
        'quantity', 'unit_price', 'currency', 'is_cbam_applicable',
        'is_eudr_applicable', 'cbam_category', 'dpp_category', 'embedded_emissions',
        'emissions_source', 'emissions_updated_at', 'material_group_id',
        'customer_id', 'applicable_regulations', 'inferred_regulations',
    ];

    protected $casts = [
        'is_cbam_applicable'   => 'boolean',
        'is_eudr_applicable'   => 'boolean',
        'quantity'             => 'float',
        'unit_price'           => 'float',
        'embedded_emissions'   => 'float',
        'emissions_updated_at' => 'datetime',
        'applicable_regulations' => 'array',
        'inferred_regulations'   => 'array',
    ];

    public static function checkCbamApplicability(string $hsCode): array
    {
        $prefix = substr($hsCode, 0, 2);
        $map = [
            '72' => 'steel', '73' => 'steel',
            '25' => 'cement',
            '76' => 'aluminium',
            '28' => 'hydrogen',
            '31' => 'fertiliser',
            '27' => 'electricity',
        ];
        $category = $map[$prefix] ?? null;
        return ['is_applicable' => $category !== null, 'category' => $category];
    }

    /**
     * EUDR（歐盟禁伐林法規）Annex I 規範商品之 HS 前綴對照：牛、可可、咖啡、
     * 棕櫚油、橡膠、大豆、木材／木製品。
     */
    public static function checkEudrApplicability(string $hsCode): bool
    {
        $prefix = substr($hsCode, 0, 2);
        $eudrPrefixes = ['01', '02', '41', '18', '09', '15', '40', '12', '44', '47', '48'];
        return in_array($prefix, $eudrPrefixes, true);
    }

    /**
     * DPP（歐盟數位產品護照）類別判定，跟 cbam_category（CBAM 六類）是兩套
     * 不同的分類體系，不可混用。目前只實作電池（HS 8507 系列：鉛酸/鎳氫鎳鎘/
     * 鋰離子/其他蓄電池），其餘類別留 null，待未來擴充時再擴充對照表。
     * 回傳值可被使用者人工覆寫，覆寫後不會被本方法的後續呼叫覆蓋回去
     * （呼叫端只在 HS Code 變更時才重新判定，見 SalesProductController）。
     */
    public static function checkDppCategory(string $hsCode): ?string
    {
        $prefix4 = substr($hsCode, 0, 4);
        if ($prefix4 === '8507') {
            return 'battery';
        }
        return null;
    }

    public function batterySpec(): HasOne
    {
        return $this->hasOne(ProductBatterySpec::class);
    }

    public function materialGroup(): BelongsTo
    {
        return $this->belongsTo(MaterialGroup::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function tradeGoodSuppliers(): HasMany
    {
        return $this->hasMany(TradeGoodSupplier::class, 'trade_good_id');
    }

    public function emissionReports(): HasMany
    {
        return $this->hasMany(TradeGoodSupplierEmission::class, 'trade_good_id');
    }

    public function bomLines(): HasMany
    {
        return $this->hasMany(ProductBomLine::class, 'sales_product_id');
    }

    public function packaging(): HasOne
    {
        return $this->hasOne(ProductPackaging::class);
    }

    public function productionBatches(): HasMany
    {
        return $this->hasMany(ProductionBatch::class, 'sales_product_id')->orderByDesc('production_date');
    }

    public function pcfSnapshots(): HasMany
    {
        return $this->hasMany(PcfSnapshot::class, 'sales_product_id')->orderByDesc('snapshot_at');
    }

    public function latestPcfSnapshot(): ?PcfSnapshot
    {
        return $this->pcfSnapshots()->first();
    }

    public function circularitySnapshots(): HasMany
    {
        return $this->hasMany(ProductCircularitySnapshot::class, 'sales_product_id')->orderByDesc('calculated_at');
    }

    public function latestCircularitySnapshot(): ?ProductCircularitySnapshot
    {
        return $this->circularitySnapshots()->first();
    }

    public function complianceDocs(): HasMany
    {
        return $this->hasMany(SupplierComplianceDoc::class, 'trade_good_id');
    }

    public function syncInferredRegulations(): array
    {
        // 第一層：從 BomLine effective material group doc types 推論
        // effective 來源優先順序：materialItem->materialGroup，無則 fallback BomLine 自身 materialGroup
        $regulations = $this->bomLines()
            ->with(['materialGroup', 'materialItem.materialGroup'])
            ->get()
            ->flatMap(fn($line) => ($line->materialItem?->materialGroup ?? $line->materialGroup)?->required_doc_types ?? [])
            ->map(fn($docType) => match ($docType) {
                'UFLPA_DECLARATION' => 'UFLPA',
                'EUDR_DDS'          => 'EUDR',
                'CMRT'              => 'CMRT',
                'SDS'               => 'REACH',
                'CE_DOC'            => 'CE',
                default             => null,
            })
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        // 第二層：BomLine 推論為空時，fallback 呼叫 AI HS碼推論
        if (empty($regulations) && $this->hs_code) {
            $regulations = $this->inferRegulationsFromAi();
        }

        $this->update(['inferred_regulations' => $regulations]);

        return $regulations;
    }

    private function inferRegulationsFromAi(): array
    {
        try {
            $aiUrl = rtrim(config('services.ai.url', env('AI_SERVICE_URL', 'http://esgchain-ai:8000')), '/');
            $resp  = \Illuminate\Support\Facades\Http::timeout(10)->post(
                "{$aiUrl}/ai/v1/celery/regulations-infer",
                [
                    'hs_code'      => $this->hs_code,
                    'product_name' => $this->name,
                ]
            );

            if ($resp->successful()) {
                return $resp->json('inferred_regulations', []);
            }
        } catch (\Throwable) {
            // AI 不可用時靜默 fallback，回傳空陣列
        }

        return [];
    }
}
