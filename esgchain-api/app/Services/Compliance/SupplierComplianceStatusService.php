<?php

namespace App\Services\Compliance;

use App\Models\SalesProduct;
use App\Models\MaterialGroup;
use App\Models\ProductBomLine;
use App\Models\Supplier;
use App\Models\SupplierComplianceDoc;
use App\Models\SupplierGroup;
use App\Services\PCF\PcrCalculationService;
use Illuminate\Support\Collection;

class SupplierComplianceStatusService
{
    private const STATUS_PRIORITY = ['expired' => 4, 'expiring_soon' => 3, 'pending' => 2, 'valid' => 1, 'unconfigured' => 0];

    public function getSupplierSummary(Supplier $supplier): array
    {
        $docs = $supplier->complianceDocs()->get();

        $counts = ['valid' => 0, 'expiring_soon' => 0, 'expired' => 0, 'pending' => 0];
        foreach ($docs as $doc) {
            $counts[$doc->status] = ($counts[$doc->status] ?? 0) + 1;
        }

        // 從 BomLineSupplier 聚合此供應商的合規需求
        $requiredTypes = $supplier->bomLineSuppliers()
            ->with('bomLine.materialGroup')
            ->get()
            ->flatMap(function ($bls) {
                return $bls->bomLine?->materialGroup?->required_doc_types ?? [];
            })
            ->unique()
            ->values()
            ->toArray();

        $submittedTypes = $docs->pluck('doc_type')->unique()->values()->toArray();
        $missingTypes   = array_values(array_diff($requiredTypes, $submittedTypes));

        return [
            'supplier_id'            => $supplier->id,
            'supplier_name'          => $supplier->name,
            'total_docs'             => $docs->count(),
            'valid_count'            => $counts['valid'],
            'expiring_soon_count'    => $counts['expiring_soon'],
            'expired_count'          => $counts['expired'],
            'pending_count'          => $counts['pending'],
            'missing_required_types' => $missingTypes,
        ];
    }

    /**
     * 以 BomLine 為單一驅動來源計算產品合規狀態。
     * 廢棄舊雙路徑（ProductSupplier 路徑）。
     */
    public function getProductCompliance(SalesProduct $product): array
    {
        $bomLines = $product->bomLines()
            ->whereNotNull('material_group_id')
            ->with(['materialGroup', 'bomLineSuppliers.supplier.complianceDocs'])
            ->get();

        if ($bomLines->isEmpty()) {
            return [
                'product_id'             => $product->id,
                'product_name'           => $product->name,
                'applicable_regulations' => $product->applicable_regulations,
                'overall_status'         => 'unconfigured',
                'bom_lines'              => [],
            ];
        }

        $worstPriority  = 0;
        $worstStatus    = 'valid';
        $bomLineResults = [];

        foreach ($bomLines as $line) {
            $requiredTypes = $line->materialGroup->required_doc_types ?? [];

            if ($line->bomLineSuppliers->isEmpty()) {
                $bomLineResults[] = [
                    'bom_line_id'        => $line->id,
                    'erp_line_id'        => $line->erp_line_id,
                    'material_name'      => $line->material_name,
                    'bom_line_type'      => $line->bom_line_type,
                    'material_group'     => $line->materialGroup->name,
                    'required_doc_types' => $requiredTypes,
                    'suppliers'          => [],
                    'line_status'        => 'no_supplier',
                ];
                continue;
            }

            $supplierResults   = [];
            $lineWorstPriority = 0;
            $lineWorstStatus   = 'valid';

            foreach ($line->bomLineSuppliers as $bls) {
                $supplier = $bls->supplier;
                $docs     = $supplier->complianceDocs;

                $submittedTypes = $docs->pluck('doc_type')->unique()->values()->toArray();
                $missingTypes   = array_values(array_diff($requiredTypes, $submittedTypes));

                $counts = ['valid' => 0, 'expiring_soon' => 0, 'expired' => 0, 'pending' => 0];
                foreach ($docs->whereIn('doc_type', $requiredTypes) as $doc) {
                    $counts[$doc->status] = ($counts[$doc->status] ?? 0) + 1;
                }

                $supplierStatus = 'valid';
                if (!empty($missingTypes)) {
                    $supplierStatus = 'pending';
                } elseif ($counts['expired'] > 0) {
                    $supplierStatus = 'expired';
                } elseif ($counts['expiring_soon'] > 0) {
                    $supplierStatus = 'expiring_soon';
                }

                $supplierResults[] = [
                    'supplier_id'        => $supplier->id,
                    'supplier_name'      => $supplier->name,
                    'role'               => $bls->role,
                    'source'             => $bls->source,
                    'required_doc_types' => $requiredTypes,
                    'missing_doc_types'  => $missingTypes,
                    'doc_status'         => $supplierStatus,
                    'docs'               => $docs->whereIn('doc_type', $requiredTypes)->map(fn($d) => [
                        'doc_type'   => $d->doc_type,
                        'status'     => $d->status,
                        'expires_at' => $d->expires_at,
                    ])->values()->toArray(),
                ];

                // 只有 primary 供應商影響產品整體合規
                if ($bls->role === 'primary') {
                    $priority = self::STATUS_PRIORITY[$supplierStatus] ?? 0;
                    if ($priority > $lineWorstPriority) {
                        $lineWorstPriority = $priority;
                        $lineWorstStatus   = $supplierStatus;
                    }
                }
            }

            $bomLineResults[] = [
                'bom_line_id'        => $line->id,
                'erp_line_id'        => $line->erp_line_id,
                'material_name'      => $line->material_name,
                'bom_line_type'      => $line->bom_line_type,
                'material_group'     => $line->materialGroup->name,
                'required_doc_types' => $requiredTypes,
                'suppliers'          => $supplierResults,
                'line_status'        => $lineWorstStatus,
            ];

            $globalPriority = self::STATUS_PRIORITY[$lineWorstStatus] ?? 0;
            if ($globalPriority > $worstPriority) {
                $worstPriority = $globalPriority;
                $worstStatus   = $lineWorstStatus;
            }
        }

        return [
            'product_id'             => $product->id,
            'product_name'           => $product->name,
            'applicable_regulations' => $product->applicable_regulations,
            'overall_status'         => $worstStatus,
            'bom_lines'              => $bomLineResults,
        ];
    }

    public function getSupplierDashboard(): array
    {
        return Supplier::all()->map(function (Supplier $supplier) {
            return $this->getSupplierSummary($supplier);
        })->values()->toArray();
    }

    public function getProductDashboard(): array
    {
        return SalesProduct::all()->map(function (SalesProduct $product) {
            return $this->getProductCompliance($product);
        })->values()->toArray();
    }

    /**
     * 從 bom_line_suppliers 查詢此供應商在各產品 BomLine 的採購需求。
     */
    public function getSupplierBomRequirements(Supplier $supplier): array
    {
        $bomLineSuppliers = $supplier->bomLineSuppliers()
            ->with(['bomLine.salesProduct', 'bomLine.materialGroup'])
            ->get();

        if ($bomLineSuppliers->isEmpty()) {
            return [];
        }

        $docs    = $supplier->complianceDocs()->get()->keyBy('doc_type');
        $grouped = $bomLineSuppliers->groupBy(fn($bls) => $bls->bomLine->sales_product_id);
        $result  = [];
        $index   = 1;

        // 預載此供應商所有 BomLine 的最新 PCF 請求狀態
        $bomLineIds = $bomLineSuppliers->map(fn($bls) => $bls->bom_line_id)->unique()->values();
        $pcfStatuses = \App\Models\PcfRequestLine::whereIn('bom_line_id', $bomLineIds)
            ->whereHas('pcfRequest', fn($q) => $q->where('supplier_id', $supplier->id))
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('bom_line_id')
            ->map(fn($lines) => $lines->first()->status);

        foreach ($grouped as $productId => $blsGroup) {
            $product = $blsGroup->first()->bomLine->salesProduct;

            $bomLineData = $blsGroup->map(function ($bls) use ($docs, $pcfStatuses) {
                $line          = $bls->bomLine;
                $requiredTypes = $line->materialGroup?->required_doc_types ?? [];
                $submittedDocs = collect($requiredTypes)->map(function ($docType) use ($docs) {
                    $doc = $docs->get($docType);
                    return [
                        'doc_type'   => $docType,
                        'status'     => $doc?->status ?? 'missing',
                        'expires_at' => $doc?->expires_at,
                    ];
                })->values()->toArray();

                return [
                    'bom_line_id'        => $line->id,
                    'material_name'      => $line->material_name,
                    'bom_line_type'      => $line->bom_line_type,
                    'hs_code'            => $line->hs_code,
                    'material_group'     => $line->materialGroup?->name,
                    'role'               => $bls->role,
                    'required_doc_types' => $requiredTypes,
                    'submitted_docs'     => $submittedDocs,
                    'pcf_status'         => $pcfStatuses->get($line->id, 'none'),
                ];
            })->values()->toArray();

            $result[] = [
                'product_index'          => $index++,
                'product_id'             => $productId,
                'product_name'           => $product?->name ?? '',
                'applicable_regulations' => $product->applicable_regulations ?? [],
                'bom_lines'              => $bomLineData,
            ];
        }

        return $result;
    }

    /**
     * 更新供應商的 applicable_regulations（從 BomLineSupplier 聚合）。
     */
    public function syncSupplierApplicableRegulations(Supplier $supplier): void
    {
        $regulations = $supplier->bomLineSuppliers()
            ->with('bomLine.materialGroup')
            ->get()
            ->flatMap(fn($bls) => $bls->bomLine?->materialGroup?->required_doc_types ?? [])
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

        $supplier->update(['applicable_regulations' => $regulations]);
    }

    public function getMatrixData(?string $supplierGroupId = null, ?int $tier = null, ?float $riskScoreMin = null): array
    {
        $materialGroups = MaterialGroup::whereNotNull('required_doc_types')
            ->where('required_doc_types', '!=', '[]')
            ->get();

        $docTypes = ['EUDR_DDS', 'UFLPA_DECLARATION', 'CMRT', 'SDS', 'CE_DOC'];

        $rows = [];
        foreach ($materialGroups as $mg) {
            $required = $mg->required_doc_types ?? [];
            if (empty($required)) continue;

            // 找出所有透過 BomLine 提供此物料群組的供應商
            $supplierQuery = Supplier::whereHas('bomLineSuppliers', function ($q) use ($mg) {
                $q->whereHas('bomLine', function ($q2) use ($mg) {
                    $q2->where('material_group_id', $mg->id);
                });
            });

            if ($supplierGroupId) {
                $supplierQuery->where('group_id', $supplierGroupId);
            }
            if ($tier !== null) {
                $supplierQuery->where('tier', $tier);
            }
            if ($riskScoreMin !== null) {
                $supplierQuery->whereNotNull('risk_score')->where('risk_score', '>=', $riskScoreMin);
            }

            $suppliers = $supplierQuery->with('complianceDocs')->get();
            $total = $suppliers->count();

            $cells = [];
            foreach ($docTypes as $dt) {
                if (!in_array($dt, $required)) {
                    $cells[$dt] = null;
                    continue;
                }

                $compliant = 0;
                $expiring  = 0;
                $issues    = 0;

                foreach ($suppliers as $s) {
                    $doc = $s->complianceDocs->firstWhere('doc_type', $dt);
                    if (!$doc) {
                        $issues++;
                    } elseif ($doc->status === 'valid') {
                        $compliant++;
                    } elseif ($doc->status === 'expiring_soon') {
                        $expiring++;
                    } else {
                        $issues++;
                    }
                }

                $cells[$dt] = [
                    'total'     => $total,
                    'compliant' => $compliant,
                    'expiring'  => $expiring,
                    'issues'    => $issues,
                    'pct'       => $total > 0 ? (int) round($compliant / $total * 100) : 0,
                ];
            }

            // 有群組篩選時，略過該群組完全沒有供應商的物料行
            if ($supplierGroupId && $total === 0) {
                continue;
            }

            $rows[] = [
                'material_group_id'   => $mg->id,
                'material_group_name' => $mg->name,
                'cells'               => $cells,
            ];
        }

        return [
            'doc_types'    => $docTypes,
            'rows'         => $rows,
            'filter_group' => $supplierGroupId,
        ];
    }

    public function getDppReadinessList(): array
    {
        $products = SalesProduct::with([
            'bomLines.materialGroup',
            'bomLines.bomLineSuppliers.supplier.complianceDocs',
        ])->get();

        return $products->map(fn($p) => $this->calcDppSummary($p))->values()->toArray();
    }

    public function getDppReadinessDetail(SalesProduct $product): array
    {
        $product->load([
            'bomLines.materialGroup',
            'bomLines.bomLineSuppliers.supplier.complianceDocs',
        ]);

        $summary = $this->calcDppSummary($product);

        $materialBomLines = $product->bomLines->where('bom_line_type', 'material');

        // Section 1: material_list
        $materialItems = $materialBomLines->map(fn($line) => [
            'material_name'   => $line->material_name,
            'hs_code'         => $line->hs_code,
            'material_group'  => $line->materialGroup?->name,
            'has_group'       => !is_null($line->material_group_id),
        ])->values()->toArray();

        // Section 2: supplier_compliance
        $supplierItems = [];
        foreach ($materialBomLines as $line) {
            $requiredTypes = $line->materialGroup?->required_doc_types ?? [];
            foreach ($line->bomLineSuppliers->where('role', 'primary') as $bls) {
                $supplier = $bls->supplier;
                $docs = $supplier->complianceDocs;
                foreach ($requiredTypes as $dt) {
                    $doc = $docs->firstWhere('doc_type', $dt);
                    $supplierItems[] = [
                        'supplier_name' => $supplier->name,
                        'doc_type'      => $dt,
                        'status'        => $doc ? $doc->status : 'missing',
                        'expires_at'    => $doc?->expires_at?->toDateString(),
                    ];
                }
            }
        }

        // Section 3: regulations
        $inferredRegs   = $product->inferred_regulations ?? [];
        $applicableRegs = $product->applicable_regulations ?? [];
        $allRegs        = array_unique(array_merge($inferredRegs, $applicableRegs));

        return array_merge($summary, [
            'sections' => [
                'material_list' => [
                    'status'  => $summary['material_completeness_pct'] >= 100 ? 'complete' : 'partial',
                    'total'   => count($materialItems),
                    'complete'=> collect($materialItems)->filter(fn($i) => $i['has_group'])->count(),
                    'items'   => $materialItems,
                ],
                'supplier_compliance' => [
                    'status'         => $summary['supplier_compliance_pct'] >= 80 ? 'complete' : ($summary['supplier_compliance_pct'] > 0 ? 'partial' : 'issues'),
                    'total_required' => count($supplierItems),
                    'compliant'      => collect($supplierItems)->filter(fn($i) => in_array($i['status'], ['valid', 'expiring_soon']))->count(),
                    'items'          => $supplierItems,
                ],
                'regulations' => [
                    'has_espr'               => in_array('ESPR', $allRegs),
                    'all_regulations'        => array_values($allRegs),
                    'inferred_regulations'   => $inferredRegs,
                    'applicable_regulations' => $applicableRegs,
                ],
            ],
        ]);
    }

    private function calcDppSummary(SalesProduct $product): array
    {
        $materialBomLines = $product->bomLines->where('bom_line_type', 'material');
        $totalMaterial = $materialBomLines->count();
        $withGroup = $materialBomLines->filter(fn($l) => !is_null($l->material_group_id))->count();
        $materialPct = $totalMaterial > 0 ? (int) round($withGroup / $totalMaterial * 100) : 0;

        // supplier compliance: primary suppliers' required docs
        $totalRequired = 0;
        $compliant = 0;
        foreach ($materialBomLines as $line) {
            $requiredTypes = $line->materialGroup?->required_doc_types ?? [];
            foreach ($line->bomLineSuppliers->where('role', 'primary') as $bls) {
                $docs = $bls->supplier->complianceDocs;
                foreach ($requiredTypes as $dt) {
                    $totalRequired++;
                    $doc = $docs->firstWhere('doc_type', $dt);
                    if ($doc && in_array($doc->status, ['valid', 'expiring_soon'])) {
                        $compliant++;
                    }
                }
            }
        }
        $supplierPct = $totalRequired > 0 ? (int) round($compliant / $totalRequired * 100) : 0;

        $allRegs = array_unique(array_merge(
            $product->inferred_regulations ?? [],
            $product->applicable_regulations ?? []
        ));
        $hasEspr = in_array('ESPR', $allRegs);

        // overall status
        if ($totalMaterial === 0) {
            $status = 'not_started';
        } elseif ($hasEspr && $materialPct >= 100 && $supplierPct >= 80) {
            $status = 'ready';
        } else {
            $status = 'partial';
        }

        // issues list
        // PCR 維度：primary supplier 有 GRS（status=valid）且 pcr_percentage > 0 的 BomLine 比率
        $pcrLines = 0;
        $pcrCompliant = 0;
        $product->load(['bomLines.materialItem', 'bomLines.bomLineSuppliers.supplier.complianceDocs']);
        foreach ($materialBomLines as $line) {
            $hasPcr = $line->materialItem && $line->materialItem->pcr_percentage > 0;
            if (!$hasPcr) continue;
            $pcrLines++;
            $hasGrs = false;
            foreach ($line->bomLineSuppliers->where('role', 'primary') as $bls) {
                $grsDoc = $bls->supplier->complianceDocs->first(fn($d) => $d->doc_type === 'GRS' && $d->status === 'valid');
                if ($grsDoc) { $hasGrs = true; break; }
            }
            if ($hasGrs) $pcrCompliant++;
        }
        $pcrPct = $pcrLines > 0 ? (int) round($pcrCompliant / $pcrLines * 100) : 0;

        $issues = [];
        if (!$hasEspr) $issues[] = '未標記 ESPR 法規';
        if ($materialPct < 100) $issues[] = ($totalMaterial - $withGroup) . ' 個 BomLine 缺少物料群組';
        if ($supplierPct < 80 && $totalRequired > 0) $issues[] = '供應商合規覆蓋率不足（' . $supplierPct . '%）';
        if ($pcrLines > 0 && $pcrPct < 80) $issues[] = 'PCR 循環材料 GRS 認證未達 80%（現況 ' . $pcrPct . '%）';

        return [
            'product_id'                => $product->id,
            'product_name'              => $product->name,
            'has_espr_regulation'       => $hasEspr,
            'readiness_status'          => $status,
            'material_completeness_pct' => $materialPct,
            'supplier_compliance_pct'   => $supplierPct,
            'bom_line_count'            => $product->bomLines->count(),
            'issues'                    => $issues,
            'pcr'                       => [
                'pcr_lines'    => $pcrLines,
                'compliant'    => $pcrCompliant,
                'coverage_pct' => $pcrPct,
                'ready'        => $pcrLines === 0 || $pcrPct >= 80,
            ],
        ];
    }

    public function getMatrixDrill(string $materialGroupId, string $docType, ?string $supplierGroupId = null, ?int $tier = null, ?float $riskScoreMin = null): array
    {
        $mg = MaterialGroup::findOrFail($materialGroupId);

        $supplierQuery = Supplier::whereHas('bomLineSuppliers', function ($q) use ($materialGroupId) {
            $q->whereHas('bomLine', function ($q2) use ($materialGroupId) {
                $q2->where('material_group_id', $materialGroupId);
            });
        });

        if ($supplierGroupId) {
            $supplierQuery->where('group_id', $supplierGroupId);
        }
        if ($tier !== null) {
            $supplierQuery->where('tier', $tier);
        }
        if ($riskScoreMin !== null) {
            $supplierQuery->whereNotNull('risk_score')->where('risk_score', '>=', $riskScoreMin);
        }

        $suppliers = $supplierQuery->with(['complianceDocs', 'group'])->get();

        $statusOrder = ['missing' => 0, 'expired' => 1, 'expiring_soon' => 2, 'valid' => 3];

        $list = $suppliers->map(function ($s) use ($docType) {
            $doc    = $s->complianceDocs->firstWhere('doc_type', $docType);
            $status = $doc ? $doc->status : 'missing';

            return [
                'supplier_id'      => $s->id,
                'supplier_name'    => $s->name,
                'supplier_group'   => $s->group?->name,
                'status'           => $status,
                'expires_at'       => $doc?->expires_at?->toDateString(),
                'tier'             => $s->tier,
                'risk_score'       => $s->risk_score,
                'onboarding_stage' => $s->onboarding_stage,
            ];
        })->sortBy(fn($s) => $statusOrder[$s['status']] ?? 99)->values()->toArray();

        return [
            'material_group_name' => $mg->name,
            'doc_type'            => $docType,
            'suppliers'           => $list,
        ];
    }

    public function getPendingVerifications(): array
    {
        return SupplierComplianceDoc::whereNull('verified_at')
            ->with('supplier')
            ->orderByRaw('CASE WHEN expires_at IS NULL THEN 0 ELSE 1 END ASC, expires_at ASC')
            ->get()
            ->map(function ($doc) {
                return [
                    'id'             => $doc->id,
                    'supplier_id'    => $doc->supplier_id,
                    'supplier_name'  => $doc->supplier?->name,
                    'doc_type'       => $doc->doc_type,
                    'file_name'      => $doc->file_name,
                    'uploaded_at'    => $doc->created_at?->toDateString(),
                    'expires_at'     => $doc->expires_at?->toDateString(),
                    'missing_expiry' => $doc->expires_at === null,
                    'status'         => $doc->status,
                ];
            })->values()->toArray();
    }

    public function syncProductInferredRegulations(SalesProduct $product): array
    {
        return $product->syncInferredRegulations();
    }

    public function syncAllProductsInferredRegulations(): int
    {
        $count = 0;
        SalesProduct::chunk(100, function ($products) use (&$count) {
            foreach ($products as $product) {
                $product->syncInferredRegulations();
                $count++;
            }
        });
        return $count;
    }
}
