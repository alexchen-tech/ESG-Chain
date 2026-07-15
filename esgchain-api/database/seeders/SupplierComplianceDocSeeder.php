<?php

namespace Database\Seeders;

use App\Models\Supplier;
use App\Models\SupplierComplianceDoc;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class SupplierComplianceDocSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        $docs = [
            // ── CTN-001 台灣紡紗 — UFLPA + 原產地（有效，已驗證）──
            [
                'supplier_code' => 'CTN-001',
                'doc_type'      => 'UFLPA_DECLARATION',
                'file_name'     => 'CTN001_UFLPA_Declaration_2025.pdf',
                'issued_at'     => $now->copy()->subMonths(3)->format('Y-m-d'),
                'expires_at'    => $now->copy()->addMonths(9)->format('Y-m-d'),
                'verified_at'   => $now->copy()->subDays(5),
            ],
            [
                'supplier_code' => 'CTN-001',
                'doc_type'      => 'ORIGIN_CERT',
                'file_name'     => 'CTN001_OriginCert_Q1_2025.pdf',
                'issued_at'     => $now->copy()->subMonths(4)->format('Y-m-d'),
                'expires_at'    => $now->copy()->addMonths(8)->format('Y-m-d'),
                'verified_at'   => $now->copy()->subDays(10),
            ],

            // ── CTN-002 巴基斯坦棉廠 — UFLPA 即將到期（5天內），原產地有效 ──
            [
                'supplier_code' => 'CTN-002',
                'doc_type'      => 'UFLPA_DECLARATION',
                'file_name'     => 'CTN002_UFLPA_Declaration_2024.pdf',
                'issued_at'     => $now->copy()->subMonths(11)->format('Y-m-d'),
                'expires_at'    => $now->copy()->addDays(5)->format('Y-m-d'),
                'verified_at'   => null,
            ],
            [
                'supplier_code' => 'CTN-002',
                'doc_type'      => 'ORIGIN_CERT',
                'file_name'     => 'CTN002_OriginCert_2025.pdf',
                'issued_at'     => $now->copy()->subMonths(2)->format('Y-m-d'),
                'expires_at'    => $now->copy()->addMonths(10)->format('Y-m-d'),
                'verified_at'   => $now->copy()->subDays(14),
            ],

            // ── CTN-003 美國有機棉 — 兩份均有效 ──
            [
                'supplier_code' => 'CTN-003',
                'doc_type'      => 'UFLPA_DECLARATION',
                'file_name'     => 'CTN003_UFLPA_Declaration_2025.pdf',
                'issued_at'     => $now->copy()->subMonths(2)->format('Y-m-d'),
                'expires_at'    => $now->copy()->addMonths(10)->format('Y-m-d'),
                'verified_at'   => $now->copy()->subDays(3),
            ],
            [
                'supplier_code' => 'CTN-003',
                'doc_type'      => 'ORIGIN_CERT',
                'file_name'     => 'CTN003_OriginCert_2025.pdf',
                'issued_at'     => $now->copy()->subMonths(3)->format('Y-m-d'),
                'expires_at'    => $now->copy()->addMonths(9)->format('Y-m-d'),
                'verified_at'   => $now->copy()->subDays(7),
            ],

            // ── SYN-001 南亞聚酯 — SDS 有效 ──
            [
                'supplier_code' => 'SYN-001',
                'doc_type'      => 'SDS',
                'file_name'     => 'SYN001_SDS_PET_Bundle_2025.pdf',
                'issued_at'     => $now->copy()->subMonths(2)->format('Y-m-d'),
                'expires_at'    => $now->copy()->addMonths(22)->format('Y-m-d'),
                'verified_at'   => $now->copy()->subDays(4),
            ],

            // ── SYN-002 Toray Japan — SDS 有效 ──
            [
                'supplier_code' => 'SYN-002',
                'doc_type'      => 'SDS',
                'file_name'     => 'SYN002_SDS_Nylon_2025.pdf',
                'issued_at'     => $now->copy()->subMonths(1)->format('Y-m-d'),
                'expires_at'    => $now->copy()->addMonths(23)->format('Y-m-d'),
                'verified_at'   => $now->copy()->subDays(2),
            ],

            // ── SYN-003 遠東越南 — SDS 即將到期（3天內）──
            [
                'supplier_code' => 'SYN-003',
                'doc_type'      => 'SDS',
                'file_name'     => 'SYN003_SDS_rPET_2024.pdf',
                'issued_at'     => $now->copy()->subMonths(11)->format('Y-m-d'),
                'expires_at'    => $now->copy()->addDays(3)->format('Y-m-d'),
                'verified_at'   => null,
            ],

            // ── WVN-001 宏遠興業 — SDS 有效（染整化學品）──
            [
                'supplier_code' => 'WVN-001',
                'doc_type'      => 'SDS',
                'file_name'     => 'WVN001_SDS_DWR_Chemical_2025.pdf',
                'issued_at'     => $now->copy()->subMonths(4)->format('Y-m-d'),
                'expires_at'    => $now->copy()->addMonths(20)->format('Y-m-d'),
                'verified_at'   => $now->copy()->subDays(15),
            ],

            // ── TRM-001 YKK Taiwan — CMRT 有效（金屬拉鍊）──
            [
                'supplier_code' => 'TRM-001',
                'doc_type'      => 'CMRT',
                'file_name'     => 'TRM001_CMRT_YKK_2025.pdf',
                'issued_at'     => $now->copy()->subMonths(5)->format('Y-m-d'),
                'expires_at'    => $now->copy()->addMonths(7)->format('Y-m-d'),
                'verified_at'   => $now->copy()->subDays(20),
            ],

            // ── TRM-002 浙江偉星 — CMRT 已過期（高風險供應商）──
            [
                'supplier_code' => 'TRM-002',
                'doc_type'      => 'CMRT',
                'file_name'     => 'TRM002_CMRT_2024.pdf',
                'issued_at'     => $now->copy()->subMonths(17)->format('Y-m-d'),
                'expires_at'    => $now->copy()->subMonths(5)->format('Y-m-d'),
                'verified_at'   => null,
            ],

            // ── TRM-002 浙江偉星 — 天然纖維輔料 EUDR 聲明有效 ──
            [
                'supplier_code' => 'TRM-002',
                'doc_type'      => 'EUDR_DDS',
                'file_name'     => 'TRM002_EUDR_DDS_WoodButton_2025.pdf',
                'issued_at'     => $now->copy()->subMonths(3)->format('Y-m-d'),
                'expires_at'    => $now->copy()->addMonths(9)->format('Y-m-d'),
                'verified_at'   => $now->copy()->subDays(12),
            ],

            // ── TRM-004 Prym Germany — CMRT 有效 ──
            [
                'supplier_code' => 'TRM-004',
                'doc_type'      => 'CMRT',
                'file_name'     => 'TRM004_CMRT_Prym_2025.pdf',
                'issued_at'     => $now->copy()->subMonths(4)->format('Y-m-d'),
                'expires_at'    => $now->copy()->addMonths(8)->format('Y-m-d'),
                'verified_at'   => $now->copy()->subDays(8),
            ],

            // ── CHM-001 Huntsman — SDS 有效（DWR 助劑）──
            [
                'supplier_code' => 'CHM-001',
                'doc_type'      => 'SDS',
                'file_name'     => 'CHM001_SDS_DWR_DM3074_2025.pdf',
                'issued_at'     => $now->copy()->subMonths(3)->format('Y-m-d'),
                'expires_at'    => $now->copy()->addMonths(21)->format('Y-m-d'),
                'verified_at'   => $now->copy()->subDays(6),
            ],

            // ── CHM-002 Archroma — SDS 有效 ──
            [
                'supplier_code' => 'CHM-002',
                'doc_type'      => 'SDS',
                'file_name'     => 'CHM002_SDS_Dispersol_2025.pdf',
                'issued_at'     => $now->copy()->subMonths(2)->format('Y-m-d'),
                'expires_at'    => $now->copy()->addMonths(22)->format('Y-m-d'),
                'verified_at'   => $now->copy()->subDays(3),
            ],

            // ── CHM-003 台灣恆隆 — SDS 有效 ──
            [
                'supplier_code' => 'CHM-003',
                'doc_type'      => 'SDS',
                'file_name'     => 'CHM003_SDS_Softener_2025.pdf',
                'issued_at'     => $now->copy()->subMonths(1)->format('Y-m-d'),
                'expires_at'    => $now->copy()->addMonths(23)->format('Y-m-d'),
                'verified_at'   => null,
            ],

            // ── GMN-001 越南成衣 — UFLPA（棉料追溯）有效 ──
            [
                'supplier_code' => 'GMN-001',
                'doc_type'      => 'UFLPA_DECLARATION',
                'file_name'     => 'GMN001_UFLPA_Declaration_2025.pdf',
                'issued_at'     => $now->copy()->subMonths(4)->format('Y-m-d'),
                'expires_at'    => $now->copy()->addMonths(8)->format('Y-m-d'),
                'verified_at'   => $now->copy()->subDays(18),
            ],
            [
                'supplier_code' => 'GMN-001',
                'doc_type'      => 'SDS',
                'file_name'     => 'GMN001_SDS_Process_Chemical_2025.pdf',
                'issued_at'     => $now->copy()->subMonths(2)->format('Y-m-d'),
                'expires_at'    => $now->copy()->addMonths(22)->format('Y-m-d'),
                'verified_at'   => $now->copy()->subDays(9),
            ],

            // ── GMN-002 孟加拉成衣 — UFLPA 已過期（高風險）──
            [
                'supplier_code' => 'GMN-002',
                'doc_type'      => 'UFLPA_DECLARATION',
                'file_name'     => 'GMN002_UFLPA_Declaration_2024.pdf',
                'issued_at'     => $now->copy()->subMonths(15)->format('Y-m-d'),
                'expires_at'    => $now->copy()->subMonths(3)->format('Y-m-d'),
                'verified_at'   => null,
            ],

            // ── GMN-005 斯里蘭卡 MAS — UFLPA 有效，原產地有效 ──
            [
                'supplier_code' => 'GMN-005',
                'doc_type'      => 'UFLPA_DECLARATION',
                'file_name'     => 'GMN005_UFLPA_Declaration_2025.pdf',
                'issued_at'     => $now->copy()->subMonths(3)->format('Y-m-d'),
                'expires_at'    => $now->copy()->addMonths(9)->format('Y-m-d'),
                'verified_at'   => $now->copy()->subDays(11),
            ],
            [
                'supplier_code' => 'GMN-005',
                'doc_type'      => 'ORIGIN_CERT',
                'file_name'     => 'GMN005_OriginCert_LK_2025.pdf',
                'issued_at'     => $now->copy()->subMonths(2)->format('Y-m-d'),
                'expires_at'    => $now->copy()->addMonths(10)->format('Y-m-d'),
                'verified_at'   => $now->copy()->subDays(5),
            ],

            // ── DYE-001 聯發染整 — SDS 有效 ──
            [
                'supplier_code' => 'DYE-001',
                'doc_type'      => 'SDS',
                'file_name'     => 'DYE001_SDS_ReactiveBlack_2025.pdf',
                'issued_at'     => $now->copy()->subMonths(5)->format('Y-m-d'),
                'expires_at'    => $now->copy()->addMonths(19)->format('Y-m-d'),
                'verified_at'   => $now->copy()->subDays(25),
            ],

            // ── WVN-003 孟加拉針織 — UFLPA 待驗證（pending）──
            [
                'supplier_code' => 'WVN-003',
                'doc_type'      => 'UFLPA_DECLARATION',
                'file_name'     => 'WVN003_UFLPA_Declaration_2025.pdf',
                'issued_at'     => $now->copy()->subMonths(1)->format('Y-m-d'),
                'expires_at'    => $now->copy()->addMonths(11)->format('Y-m-d'),
                'verified_at'   => null,
            ],

            // ── GMN-002 孟加拉成衣 — ORIGIN_CERT 即將到期（2天內，高風險）──
            [
                'supplier_code' => 'GMN-002',
                'doc_type'      => 'ORIGIN_CERT',
                'file_name'     => 'GMN002_OriginCert_BD_2024.pdf',
                'issued_at'     => $now->copy()->subMonths(12)->format('Y-m-d'),
                'expires_at'    => $now->copy()->addDays(2)->format('Y-m-d'),
                'verified_at'   => null,
            ],

            // ── WVN-001 宏遠興業 — CMRT 即將到期（6天內）──
            [
                'supplier_code' => 'WVN-001',
                'doc_type'      => 'CMRT',
                'file_name'     => 'WVN001_CMRT_Minerals_2024.pdf',
                'issued_at'     => $now->copy()->subMonths(12)->format('Y-m-d'),
                'expires_at'    => $now->copy()->addDays(6)->format('Y-m-d'),
                'verified_at'   => null,
            ],
        ];

        foreach ($docs as $d) {
            $supplier = Supplier::where('code', $d['supplier_code'])->first();
            if (!$supplier) continue;

            $verifiedAt = $d['verified_at'] instanceof Carbon ? $d['verified_at'] : null;

            SupplierComplianceDoc::updateOrCreate(
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
