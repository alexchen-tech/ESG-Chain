<?php

namespace Database\Seeders;

use App\Models\Supplier;
use App\Models\SupplierComplianceDoc;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class ComplianceDashboardSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        // 補充文件：讓各產品合規看板呈現豐富的多元狀態
        $docs = [
            // ── TRM-003 Korea Label — CMRT 有效（金屬吊牌扣環）──
            [
                'supplier_code' => 'TRM-003',
                'doc_type'      => 'CMRT',
                'file_name'     => 'TRM003_CMRT_KoreaLabel_2025.pdf',
                'issued_at'     => $now->copy()->subMonths(4)->format('Y-m-d'),
                'expires_at'    => $now->copy()->addMonths(8)->format('Y-m-d'),
                'verified_at'   => $now->copy()->subDays(12),
            ],

            // ── SYN-004 Indorama Indonesia — SDS 即將到期 ──
            [
                'supplier_code' => 'SYN-004',
                'doc_type'      => 'SDS',
                'file_name'     => 'SYN004_SDS_PET_2024.pdf',
                'issued_at'     => $now->copy()->subMonths(11)->format('Y-m-d'),
                'expires_at'    => $now->copy()->addDays(25)->format('Y-m-d'),
                'verified_at'   => null,
            ],

            // ── GMN-003 柬埔寨成衣 — EUDR（木質包材）有效 ──
            [
                'supplier_code' => 'GMN-003',
                'doc_type'      => 'EUDR_DDS',
                'file_name'     => 'GMN003_EUDR_WoodPackaging_2025.pdf',
                'issued_at'     => $now->copy()->subMonths(2)->format('Y-m-d'),
                'expires_at'    => $now->copy()->addMonths(10)->format('Y-m-d'),
                'verified_at'   => $now->copy()->subDays(5),
            ],
            [
                'supplier_code' => 'GMN-003',
                'doc_type'      => 'ORIGIN_CERT',
                'file_name'     => 'GMN003_OriginCert_KH_2025.pdf',
                'issued_at'     => $now->copy()->subMonths(3)->format('Y-m-d'),
                'expires_at'    => $now->copy()->addMonths(9)->format('Y-m-d'),
                'verified_at'   => null,
            ],

            // ── CTN-004 印度棉花 — UFLPA 有效，原產地待驗證 ──
            [
                'supplier_code' => 'CTN-004',
                'doc_type'      => 'UFLPA_DECLARATION',
                'file_name'     => 'CTN004_UFLPA_Declaration_2025.pdf',
                'issued_at'     => $now->copy()->subMonths(5)->format('Y-m-d'),
                'expires_at'    => $now->copy()->addMonths(7)->format('Y-m-d'),
                'verified_at'   => $now->copy()->subDays(30),
            ],

            // ── CHM-004 印度染料廠 — SDS 已過期（需補件）──
            [
                'supplier_code' => 'CHM-004',
                'doc_type'      => 'SDS',
                'file_name'     => 'CHM004_SDS_ReactiveDye_2024.pdf',
                'issued_at'     => $now->copy()->subMonths(14)->format('Y-m-d'),
                'expires_at'    => $now->copy()->subMonths(2)->format('Y-m-d'),
                'verified_at'   => null,
            ],

            // ── WVN-002 越南織布廠 — SDS 有效（整理劑）──
            [
                'supplier_code' => 'WVN-002',
                'doc_type'      => 'SDS',
                'file_name'     => 'WVN002_SDS_Finishing_2025.pdf',
                'issued_at'     => $now->copy()->subMonths(3)->format('Y-m-d'),
                'expires_at'    => $now->copy()->addMonths(21)->format('Y-m-d'),
                'verified_at'   => $now->copy()->subDays(16),
            ],

            // ── GMN-004 衣索比亞 — UFLPA 已提交待審（pending）──
            [
                'supplier_code' => 'GMN-004',
                'doc_type'      => 'UFLPA_DECLARATION',
                'file_name'     => 'GMN004_UFLPA_Declaration_2025.pdf',
                'issued_at'     => $now->copy()->subMonths(1)->format('Y-m-d'),
                'expires_at'    => $now->copy()->addMonths(11)->format('Y-m-d'),
                'verified_at'   => null,
            ],

            // ── DYE-002 印尼染整 — SDS 即將到期 ──
            [
                'supplier_code' => 'DYE-002',
                'doc_type'      => 'SDS',
                'file_name'     => 'DYE002_SDS_IndoReactive_2024.pdf',
                'issued_at'     => $now->copy()->subMonths(11)->format('Y-m-d'),
                'expires_at'    => $now->copy()->addDays(20)->format('Y-m-d'),
                'verified_at'   => null,
            ],
        ];

        foreach ($docs as $d) {
            $supplier = Supplier::where('code', $d['supplier_code'])->first();
            if (!$supplier) continue;

            $verifiedAt = $d['verified_at'] instanceof Carbon ? $d['verified_at'] : null;

            SupplierComplianceDoc::firstOrCreate(
                [
                    'supplier_id' => $supplier->id,
                    'doc_type'    => $d['doc_type'],
                    'file_name'   => $d['file_name'],
                ],
                [
                    'file_path'   => "compliance-docs/{$supplier->id}/{$d['file_name']}",
                    'issued_at'   => $d['issued_at'],
                    'expires_at'  => $d['expires_at'],
                    'verified_at' => $verifiedAt,
                ]
            );
        }
    }
}
