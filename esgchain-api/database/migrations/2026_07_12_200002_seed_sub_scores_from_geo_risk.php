<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $ratings = DB::table('country_risk_ratings')->whereNull('sub_scores')->get();

        foreach ($ratings as $r) {
            $val = (int) $r->geo_risk;
            DB::table('country_risk_ratings')
                ->where('id', $r->id)
                ->update([
                    'sub_scores' => json_encode([
                        'political'     => $val,
                        'environmental' => $val,
                        'social'        => $val,
                        'regulatory'    => $val,
                    ]),
                ]);
        }
    }

    public function down(): void
    {
        // irreversible seed — no-op
    }
};
