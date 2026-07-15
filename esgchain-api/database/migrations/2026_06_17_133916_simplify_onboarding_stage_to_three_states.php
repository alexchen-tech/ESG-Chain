<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // potential / invited / reviewing / certified → active
        DB::table('suppliers')
            ->whereIn('onboarding_stage', ['potential', 'invited', 'reviewing', 'certified'])
            ->update(['onboarding_stage' => 'active']);
    }

    public function down(): void
    {
        DB::table('suppliers')
            ->where('onboarding_stage', 'active')
            ->update(['onboarding_stage' => 'certified']);
    }
};
