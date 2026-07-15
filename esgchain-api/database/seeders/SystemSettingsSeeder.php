<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingsSeeder extends Seeder
{
    public function run(): void
    {
        SystemSetting::set('carbon_price_eur', '65.00');
        $this->command->info('系統設定：碳價假設預設值 €65.00/tCO₂e 已植入');
    }
}
