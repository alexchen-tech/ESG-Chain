<?php

namespace Database\Seeders;

use App\Models\SasbIndustry;
use Illuminate\Database\Seeder;

class SasbIndustrySeeder extends Seeder
{
    public function run(): void
    {
        $industries = [
            // Technology & Communications
            ['sector' => 'Technology & Communications', 'industry' => 'Electronic Manufacturing Services & Original Design Manufacturing', 'code' => 'TC-ES'],
            ['sector' => 'Technology & Communications', 'industry' => 'Hardware', 'code' => 'TC-HW'],
            ['sector' => 'Technology & Communications', 'industry' => 'Internet Media & Services', 'code' => 'TC-IM'],
            ['sector' => 'Technology & Communications', 'industry' => 'Semiconductors', 'code' => 'TC-SC'],
            ['sector' => 'Technology & Communications', 'industry' => 'Software & IT Services', 'code' => 'TC-SI'],
            ['sector' => 'Technology & Communications', 'industry' => 'Telecommunication Services', 'code' => 'TC-TL'],
            // Extractives & Minerals Processing
            ['sector' => 'Extractives & Minerals Processing', 'industry' => 'Coal Operations', 'code' => 'EM-CO'],
            ['sector' => 'Extractives & Minerals Processing', 'industry' => 'Construction Materials', 'code' => 'EM-CM'],
            ['sector' => 'Extractives & Minerals Processing', 'industry' => 'Iron & Steel Producers', 'code' => 'EM-IS'],
            ['sector' => 'Extractives & Minerals Processing', 'industry' => 'Metals & Mining', 'code' => 'EM-MM'],
            ['sector' => 'Extractives & Minerals Processing', 'industry' => 'Oil & Gas – Exploration & Production', 'code' => 'EM-EP'],
            ['sector' => 'Extractives & Minerals Processing', 'industry' => 'Oil & Gas – Midstream', 'code' => 'EM-MD'],
            ['sector' => 'Extractives & Minerals Processing', 'industry' => 'Oil & Gas – Refining & Marketing', 'code' => 'EM-RM'],
            ['sector' => 'Extractives & Minerals Processing', 'industry' => 'Oil & Gas – Services', 'code' => 'EM-SV'],
            // Financials
            ['sector' => 'Financials', 'industry' => 'Asset Management & Custody Activities', 'code' => 'FN-AC'],
            ['sector' => 'Financials', 'industry' => 'Commercial Banks', 'code' => 'FN-CB'],
            ['sector' => 'Financials', 'industry' => 'Insurance', 'code' => 'FN-IN'],
            ['sector' => 'Financials', 'industry' => 'Investment Banking & Brokerage', 'code' => 'FN-IB'],
            ['sector' => 'Financials', 'industry' => 'Mortgage Finance', 'code' => 'FN-MF'],
            // Food & Beverage
            ['sector' => 'Food & Beverage', 'industry' => 'Agricultural Products', 'code' => 'FB-AG'],
            ['sector' => 'Food & Beverage', 'industry' => 'Alcoholic Beverages', 'code' => 'FB-AB'],
            ['sector' => 'Food & Beverage', 'industry' => 'Food Retailers & Distributors', 'code' => 'FB-FR'],
            ['sector' => 'Food & Beverage', 'industry' => 'Meat, Poultry & Dairy', 'code' => 'FB-MP'],
            ['sector' => 'Food & Beverage', 'industry' => 'Non-Alcoholic Beverages', 'code' => 'FB-NB'],
            ['sector' => 'Food & Beverage', 'industry' => 'Processed Foods', 'code' => 'FB-PF'],
            ['sector' => 'Food & Beverage', 'industry' => 'Restaurants', 'code' => 'FB-RN'],
            ['sector' => 'Food & Beverage', 'industry' => 'Tobacco', 'code' => 'FB-TB'],
            // Health Care
            ['sector' => 'Health Care', 'industry' => 'Biotechnology & Pharmaceuticals', 'code' => 'HC-BP'],
            ['sector' => 'Health Care', 'industry' => 'Drug Retailers', 'code' => 'HC-DR'],
            ['sector' => 'Health Care', 'industry' => 'Health Care Delivery', 'code' => 'HC-DY'],
            ['sector' => 'Health Care', 'industry' => 'Health Care Distributors', 'code' => 'HC-DI'],
            ['sector' => 'Health Care', 'industry' => 'Managed Care', 'code' => 'HC-MC'],
            ['sector' => 'Health Care', 'industry' => 'Medical Equipment & Supplies', 'code' => 'HC-MS'],
            // Infrastructure
            ['sector' => 'Infrastructure', 'industry' => 'Electric Utilities & Power Generators', 'code' => 'IF-EU'],
            ['sector' => 'Infrastructure', 'industry' => 'Engineering & Construction Services', 'code' => 'IF-EN'],
            ['sector' => 'Infrastructure', 'industry' => 'Gas Utilities & Distributors', 'code' => 'IF-GU'],
            ['sector' => 'Infrastructure', 'industry' => 'Home Builders', 'code' => 'IF-HB'],
            ['sector' => 'Infrastructure', 'industry' => 'Real Estate', 'code' => 'IF-RE'],
            ['sector' => 'Infrastructure', 'industry' => 'Real Estate Services', 'code' => 'IF-RS'],
            ['sector' => 'Infrastructure', 'industry' => 'Waste Management', 'code' => 'IF-WM'],
            ['sector' => 'Infrastructure', 'industry' => 'Water Utilities & Services', 'code' => 'IF-WU'],
            // Renewable Resources & Alternative Energy
            ['sector' => 'Renewable Resources & Alternative Energy', 'industry' => 'Biofuels', 'code' => 'RR-BI'],
            ['sector' => 'Renewable Resources & Alternative Energy', 'industry' => 'Forestry Management', 'code' => 'RR-FM'],
            ['sector' => 'Renewable Resources & Alternative Energy', 'industry' => 'Fuel Cells & Industrial Batteries', 'code' => 'RR-FC'],
            ['sector' => 'Renewable Resources & Alternative Energy', 'industry' => 'Pulp & Paper Products', 'code' => 'RR-PP'],
            ['sector' => 'Renewable Resources & Alternative Energy', 'industry' => 'Solar Technology & Project Developers', 'code' => 'RR-ST'],
            ['sector' => 'Renewable Resources & Alternative Energy', 'industry' => 'Wind Technology & Project Developers', 'code' => 'RR-WT'],
            // Resource Transformation
            ['sector' => 'Resource Transformation', 'industry' => 'Aerospace & Defense', 'code' => 'RT-AE'],
            ['sector' => 'Resource Transformation', 'industry' => 'Chemicals', 'code' => 'RT-CH'],
            ['sector' => 'Resource Transformation', 'industry' => 'Containers & Packaging', 'code' => 'RT-CP'],
            ['sector' => 'Resource Transformation', 'industry' => 'Electrical & Electronic Equipment', 'code' => 'RT-EE'],
            ['sector' => 'Resource Transformation', 'industry' => 'Industrial Machinery & Goods', 'code' => 'RT-IG'],
            // Services
            ['sector' => 'Services', 'industry' => 'Advertising & Marketing', 'code' => 'SV-AD'],
            ['sector' => 'Services', 'industry' => 'Casinos & Gaming', 'code' => 'SV-CA'],
            ['sector' => 'Services', 'industry' => 'Education', 'code' => 'SV-ED'],
            ['sector' => 'Services', 'industry' => 'Hotels & Lodging', 'code' => 'SV-HL'],
            ['sector' => 'Services', 'industry' => 'Leisure Facilities', 'code' => 'SV-LF'],
            ['sector' => 'Services', 'industry' => 'Media & Entertainment', 'code' => 'SV-ME'],
            ['sector' => 'Services', 'industry' => 'Professional & Commercial Services', 'code' => 'SV-PS'],
            // Transportation
            ['sector' => 'Transportation', 'industry' => 'Air Freight & Logistics', 'code' => 'TR-AF'],
            ['sector' => 'Transportation', 'industry' => 'Airlines', 'code' => 'TR-AL'],
            ['sector' => 'Transportation', 'industry' => 'Auto Parts', 'code' => 'TR-AP'],
            ['sector' => 'Transportation', 'industry' => 'Automobiles', 'code' => 'TR-AU'],
            ['sector' => 'Transportation', 'industry' => 'Car Rental & Leasing', 'code' => 'TR-CR'],
            ['sector' => 'Transportation', 'industry' => 'Cruise Lines', 'code' => 'TR-CL'],
            ['sector' => 'Transportation', 'industry' => 'Marine Transportation', 'code' => 'TR-MT'],
            ['sector' => 'Transportation', 'industry' => 'Rail Transportation', 'code' => 'TR-RA'],
            ['sector' => 'Transportation', 'industry' => 'Road Transportation', 'code' => 'TR-RO'],
            // Consumer Goods
            ['sector' => 'Consumer Goods', 'industry' => 'Apparel, Accessories & Footwear', 'code' => 'CG-AA'],
            ['sector' => 'Consumer Goods', 'industry' => 'Building Products & Furnishings', 'code' => 'CG-BF'],
            ['sector' => 'Consumer Goods', 'industry' => 'Electronic Commerce', 'code' => 'CG-EC'],
            ['sector' => 'Consumer Goods', 'industry' => 'Household & Personal Products', 'code' => 'CG-HP'],
            ['sector' => 'Consumer Goods', 'industry' => 'Multiline and Specialty Retailers & Distributors', 'code' => 'CG-MR'],
            ['sector' => 'Consumer Goods', 'industry' => 'Toys & Sporting Goods', 'code' => 'CG-TS'],
        ];

        foreach ($industries as $data) {
            SasbIndustry::firstOrCreate(['code' => $data['code']], $data);
        }
    }
}
