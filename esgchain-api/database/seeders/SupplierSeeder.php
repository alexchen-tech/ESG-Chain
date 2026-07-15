<?php

namespace Database\Seeders;

use App\Models\Supplier;
use App\Models\SupplierContact;
use App\Models\SupplierGroup;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $groups = SupplierGroup::all()->keyBy('name');

        $suppliers = [
            // ── 棉紡紗線供應商 ──
            [
                'code' => 'CTN-001', 'name' => '台灣紡紗股份有限公司',
                'country_code' => 'TW', 'industry' => '棉紡紗線', 'tier' => 1,
                'status' => 'active', 'onboarding_stage' => 'certified',
                'group' => '棉紡紗線供應商', 'risk_score' => 0.18,
                'contact_name' => '陳志明', 'contact_email' => 'supplier1@twspinning.com.tw',
                'contact_title' => 'ESG 負責人', 'supplier_role' => 'supplier',
            ],
            [
                'code' => 'CTN-002', 'name' => 'Masood Textile Mills Pakistan',
                'country_code' => 'PK', 'industry' => '棉紡紗線', 'tier' => 2,
                'status' => 'active', 'onboarding_stage' => 'certified',
                'group' => '棉紡紗線供應商', 'risk_score' => 0.42,
                'contact_name' => 'Asim Raza', 'contact_email' => 'esg@masoodtextile.pk',
                'contact_title' => 'Sustainability Manager', 'supplier_role' => null,
            ],
            [
                'code' => 'CTN-003', 'name' => 'Organic Cotton Alliance USA',
                'country_code' => 'US', 'industry' => '有機棉', 'tier' => 2,
                'status' => 'active', 'onboarding_stage' => 'reviewing',
                'group' => '棉紡紗線供應商', 'risk_score' => 0.12,
                'contact_name' => 'Emily Carter', 'contact_email' => 'esg@orgcotton.us',
                'contact_title' => 'Supply Chain Director', 'supplier_role' => null,
            ],
            [
                'code' => 'CTN-004', 'name' => 'India Cotton Consortium',
                'country_code' => 'IN', 'industry' => '棉花原料', 'tier' => 3,
                'status' => 'active', 'onboarding_stage' => 'invited',
                'group' => '棉紡紗線供應商', 'risk_score' => 0.55,
                'contact_name' => 'Vikram Patel', 'contact_email' => 'esg@indiacotton.in',
                'contact_title' => 'Procurement Head', 'supplier_role' => null,
            ],
            [
                'code' => 'CTN-005', 'name' => '新疆棉紡織品有限公司',
                'country_code' => 'CN', 'industry' => '棉紡紗線', 'tier' => 2,
                'status' => 'suspended', 'onboarding_stage' => 'reviewing',
                'group' => '棉紡紗線供應商', 'risk_score' => 0.88,
                'contact_name' => '王建國', 'contact_email' => 'esg@xjcotton.cn',
                'contact_title' => '供應鏈主任', 'supplier_role' => null,
            ],

            // ── 合成纖維供應商 ──
            [
                'code' => 'SYN-001', 'name' => '南亞塑膠纖維事業部',
                'country_code' => 'TW', 'industry' => '聚酯纖維', 'tier' => 1,
                'status' => 'active', 'onboarding_stage' => 'certified',
                'group' => '合成纖維供應商', 'risk_score' => 0.15,
                'contact_name' => '林志揚', 'contact_email' => 'esg@nanya-fiber.com.tw',
                'contact_title' => '永續發展部主任', 'supplier_role' => null,
            ],
            [
                'code' => 'SYN-002', 'name' => 'Toray Industries Japan',
                'country_code' => 'JP', 'industry' => '尼龍/碳纖維', 'tier' => 1,
                'status' => 'active', 'onboarding_stage' => 'certified',
                'group' => '合成纖維供應商', 'risk_score' => 0.10,
                'contact_name' => '中村健一', 'contact_email' => 'csr@toray.co.jp',
                'contact_title' => 'CSR Manager', 'supplier_role' => null,
            ],
            [
                'code' => 'SYN-003', 'name' => 'Far Eastern New Century Vietnam',
                'country_code' => 'VN', 'industry' => '再生聚酯纖維', 'tier' => 1,
                'status' => 'active', 'onboarding_stage' => 'certified',
                'group' => '合成纖維供應商', 'risk_score' => 0.22,
                'contact_name' => 'Nguyen Thi Lan', 'contact_email' => 'esg@fenc-vn.com',
                'contact_title' => 'ESG Manager', 'supplier_role' => null,
            ],
            [
                'code' => 'SYN-004', 'name' => 'Indorama Synthetics Indonesia',
                'country_code' => 'ID', 'industry' => '聚酯纖維', 'tier' => 2,
                'status' => 'active', 'onboarding_stage' => 'reviewing',
                'group' => '合成纖維供應商', 'risk_score' => 0.38,
                'contact_name' => 'Budi Santoso', 'contact_email' => 'esg@indorama.co.id',
                'contact_title' => 'Sustainability Officer', 'supplier_role' => null,
            ],

            // ── 織布染整廠 ──
            [
                'code' => 'WVN-001', 'name' => '宏遠興業股份有限公司',
                'country_code' => 'TW', 'industry' => '機能針織布', 'tier' => 1,
                'status' => 'active', 'onboarding_stage' => 'certified',
                'group' => '織布染整廠', 'risk_score' => 0.16,
                'contact_name' => '葉清來', 'contact_email' => 'esg@everest.com.tw',
                'contact_title' => 'ESG 副理', 'supplier_role' => null,
            ],
            [
                'code' => 'WVN-002', 'name' => 'Vietnam Weaving Factory Co.',
                'country_code' => 'VN', 'industry' => '梭織布', 'tier' => 1,
                'status' => 'active', 'onboarding_stage' => 'certified',
                'group' => '織布染整廠', 'risk_score' => 0.28,
                'contact_name' => 'Tran Van Minh', 'contact_email' => 'esg@vwfactory.vn',
                'contact_title' => 'ESG Coordinator', 'supplier_role' => null,
            ],
            [
                'code' => 'WVN-003', 'name' => 'Bangladesh Knitting Mills Ltd.',
                'country_code' => 'BD', 'industry' => '針織布', 'tier' => 2,
                'status' => 'active', 'onboarding_stage' => 'reviewing',
                'group' => '織布染整廠', 'risk_score' => 0.62,
                'contact_name' => 'Md. Karim', 'contact_email' => 'esg@bdknitting.com.bd',
                'contact_title' => 'Compliance Manager', 'supplier_role' => null,
            ],
            [
                'code' => 'DYE-001', 'name' => '聯發紡織染整股份有限公司',
                'country_code' => 'TW', 'industry' => '染整加工', 'tier' => 1,
                'status' => 'active', 'onboarding_stage' => 'certified',
                'group' => '織布染整廠', 'risk_score' => 0.20,
                'contact_name' => '張秋燕', 'contact_email' => 'esg@lianfa-dye.com.tw',
                'contact_title' => '環安部主任', 'supplier_role' => null,
            ],
            [
                'code' => 'DYE-002', 'name' => 'PT Sari Warna Asli Indonesia',
                'country_code' => 'ID', 'industry' => '染整加工', 'tier' => 2,
                'status' => 'active', 'onboarding_stage' => 'reviewing',
                'group' => '織布染整廠', 'risk_score' => 0.45,
                'contact_name' => 'Hendra Kusuma', 'contact_email' => 'esg@sariwarna.co.id',
                'contact_title' => 'HSE Manager', 'supplier_role' => null,
            ],

            // ── 成衣製造商 ──
            [
                'code' => 'GMN-001', 'name' => '越南成衣股份有限公司',
                'country_code' => 'VN', 'industry' => '成衣製造', 'tier' => 1,
                'status' => 'active', 'onboarding_stage' => 'certified',
                'group' => '成衣製造商', 'risk_score' => 0.25,
                'contact_name' => 'Nguyen Van An', 'contact_email' => 'esg@vietgarment.vn',
                'contact_title' => 'ESG Manager', 'supplier_role' => 'sup_esg',
            ],
            [
                'code' => 'GMN-002', 'name' => 'DBL Group Bangladesh',
                'country_code' => 'BD', 'industry' => '成衣製造', 'tier' => 1,
                'status' => 'active', 'onboarding_stage' => 'reviewing',
                'group' => '成衣製造商', 'risk_score' => 0.58,
                'contact_name' => 'Mostafizur Rahman', 'contact_email' => 'esg@dblgroup.com.bd',
                'contact_title' => 'Sustainability Director', 'supplier_role' => null,
            ],
            [
                'code' => 'GMN-003', 'name' => 'Phatex Garment Cambodia',
                'country_code' => 'KH', 'industry' => '成衣製造', 'tier' => 2,
                'status' => 'active', 'onboarding_stage' => 'reviewing',
                'group' => '成衣製造商', 'risk_score' => 0.50,
                'contact_name' => 'Sok Phea', 'contact_email' => 'esg@phatex.com.kh',
                'contact_title' => 'CSR Officer', 'supplier_role' => null,
            ],
            [
                'code' => 'GMN-004', 'name' => 'Hawassa Industrial Park Ethiopia',
                'country_code' => 'ET', 'industry' => '成衣製造', 'tier' => 2,
                'status' => 'active', 'onboarding_stage' => 'invited',
                'group' => '成衣製造商', 'risk_score' => 0.65,
                'contact_name' => 'Abel Tesfaye', 'contact_email' => 'esg@hawassapark.et',
                'contact_title' => 'ESG Coordinator', 'supplier_role' => null,
            ],
            [
                'code' => 'GMN-005', 'name' => 'MAS Holdings Sri Lanka',
                'country_code' => 'LK', 'industry' => '內衣成衣製造', 'tier' => 1,
                'status' => 'active', 'onboarding_stage' => 'certified',
                'group' => '成衣製造商', 'risk_score' => 0.20,
                'contact_name' => 'Nimal Jayasinghe', 'contact_email' => 'esg@masholdings.lk',
                'contact_title' => 'VP Sustainability', 'supplier_role' => null,
            ],
            [
                'code' => 'GMN-006', 'name' => 'Myanmar Garment Factory',
                'country_code' => 'MM', 'industry' => '成衣製造', 'tier' => 3,
                'status' => 'inactive', 'onboarding_stage' => 'potential',
                'group' => '成衣製造商', 'risk_score' => 0.82,
                'contact_name' => 'Tin Maung', 'contact_email' => 'esg@mmgarment.mm',
                'contact_title' => 'Manager', 'supplier_role' => null,
            ],

            // ── 輔料配件商 ──
            [
                'code' => 'TRM-001', 'name' => 'YKK Fastening Taiwan',
                'country_code' => 'TW', 'industry' => '拉鍊', 'tier' => 2,
                'status' => 'active', 'onboarding_stage' => 'certified',
                'group' => '輔料配件商', 'risk_score' => 0.08,
                'contact_name' => '村上健二', 'contact_email' => 'esg@ykk.com.tw',
                'contact_title' => 'CSR Manager', 'supplier_role' => null,
            ],
            [
                'code' => 'TRM-002', 'name' => '浙江偉星實業有限公司',
                'country_code' => 'CN', 'industry' => '鈕扣/配件', 'tier' => 2,
                'status' => 'active', 'onboarding_stage' => 'reviewing',
                'group' => '輔料配件商', 'risk_score' => 0.40,
                'contact_name' => '李明', 'contact_email' => 'esg@weixing-button.cn',
                'contact_title' => '品質副理', 'supplier_role' => null,
            ],
            [
                'code' => 'TRM-003', 'name' => 'Korea Label & Hang Tag Co.',
                'country_code' => 'KR', 'industry' => '織標/吊牌', 'tier' => 2,
                'status' => 'active', 'onboarding_stage' => 'certified',
                'group' => '輔料配件商', 'risk_score' => 0.15,
                'contact_name' => 'Kim Ji-hoon', 'contact_email' => 'esg@korealabel.kr',
                'contact_title' => 'QA Manager', 'supplier_role' => null,
            ],
            [
                'code' => 'TRM-004', 'name' => 'Prym Consumer Germany',
                'country_code' => 'DE', 'industry' => '金屬配件', 'tier' => 2,
                'status' => 'active', 'onboarding_stage' => 'certified',
                'group' => '輔料配件商', 'risk_score' => 0.12,
                'contact_name' => 'Hans Weber', 'contact_email' => 'esg@prym.de',
                'contact_title' => 'Sustainability Manager', 'supplier_role' => null,
            ],

            // ── 染料化學品商 ──
            [
                'code' => 'CHM-001', 'name' => 'Huntsman Textile Effects Germany',
                'country_code' => 'DE', 'industry' => '染料化學品', 'tier' => 2,
                'status' => 'active', 'onboarding_stage' => 'certified',
                'group' => '染料化學品商', 'risk_score' => 0.18,
                'contact_name' => 'Petra Müller', 'contact_email' => 'esg@huntsman-textile.de',
                'contact_title' => 'EHS Director', 'supplier_role' => null,
            ],
            [
                'code' => 'CHM-002', 'name' => 'Archroma Switzerland',
                'country_code' => 'CH', 'industry' => '特殊染料助劑', 'tier' => 2,
                'status' => 'active', 'onboarding_stage' => 'certified',
                'group' => '染料化學品商', 'risk_score' => 0.14,
                'contact_name' => 'Sophie Huber', 'contact_email' => 'sustainability@archroma.com',
                'contact_title' => 'Sustainability Lead', 'supplier_role' => null,
            ],
            [
                'code' => 'CHM-003', 'name' => '台灣恆隆化學工業',
                'country_code' => 'TW', 'industry' => '紡織助劑', 'tier' => 2,
                'status' => 'active', 'onboarding_stage' => 'certified',
                'group' => '染料化學品商', 'risk_score' => 0.20,
                'contact_name' => '洪文慶', 'contact_email' => 'esg@henglong-chem.com.tw',
                'contact_title' => '研發副理', 'supplier_role' => null,
            ],
            [
                'code' => 'CHM-004', 'name' => 'Kiri Industries India',
                'country_code' => 'IN', 'industry' => '活性染料', 'tier' => 3,
                'status' => 'active', 'onboarding_stage' => 'reviewing',
                'group' => '染料化學品商', 'risk_score' => 0.52,
                'contact_name' => 'Pravin Shah', 'contact_email' => 'esg@kiriindustries.in',
                'contact_title' => 'ESG Officer', 'supplier_role' => null,
            ],

            // ── 包材物流商 ──
            [
                'code' => 'PKG-001', 'name' => '遠東包材（桃園）有限公司',
                'country_code' => 'TW', 'industry' => '成品外箱包材', 'tier' => 3,
                'status' => 'active', 'onboarding_stage' => 'certified',
                'group' => '包材物流商', 'risk_score' => 0.15,
                'contact_name' => '江美雲', 'contact_email' => 'esg@fepkg.com.tw',
                'contact_title' => '客服主任', 'supplier_role' => null,
            ],
            [
                'code' => 'LOG-001', 'name' => 'Kerry Logistics Hong Kong',
                'country_code' => 'HK', 'industry' => '國際貨運', 'tier' => 1,
                'status' => 'active', 'onboarding_stage' => 'certified',
                'group' => '包材物流商', 'risk_score' => 0.16,
                'contact_name' => 'Raymond Leung', 'contact_email' => 'esg@kerrylogistics.hk',
                'contact_title' => 'ESG Manager', 'supplier_role' => null,
            ],
            [
                'code' => 'LOG-002', 'name' => 'DHL Freight Vietnam',
                'country_code' => 'VN', 'industry' => '倉儲配送', 'tier' => 2,
                'status' => 'active', 'onboarding_stage' => 'certified',
                'group' => '包材物流商', 'risk_score' => 0.18,
                'contact_name' => 'Pham Thi Thu', 'contact_email' => 'esg@dhl-vn.com',
                'contact_title' => 'Operations Manager', 'supplier_role' => null,
            ],
        ];

        foreach ($suppliers as $s) {
            $groupName    = $s['group'];
            $contactName  = $s['contact_name'];
            $contactEmail = $s['contact_email'];
            $contactTitle = $s['contact_title'];
            $supplierRole = $s['supplier_role'];
            unset($s['group'], $s['contact_name'], $s['contact_email'], $s['contact_title'], $s['supplier_role']);

            $s['group_id'] = $groupName ? ($groups[$groupName]->id ?? null) : null;

            $supplier = Supplier::firstOrCreate(['code' => $s['code']], $s);

            SupplierContact::firstOrCreate(
                ['email' => $contactEmail],
                [
                    'supplier_id' => $supplier->id,
                    'name'        => $contactName,
                    'email'       => $contactEmail,
                    'title'       => $contactTitle,
                    'is_primary'  => true,
                ]
            );

            if ($supplierRole) {
                $user = User::firstOrCreate(
                    ['email' => $contactEmail],
                    [
                        'name'        => $contactName,
                        'password'    => Hash::make('demo1234'),
                        'supplier_id' => $supplier->id,
                    ]
                );
                $user->update(['supplier_id' => $supplier->id]);
                $user->syncRoles([$supplierRole]);
            }
        }
    }
}
