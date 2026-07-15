<?php

namespace App\Services\Settings;

use Illuminate\Support\Facades\DB;

class SystemSettingsService
{
    const DEFAULT_DIM_WEIGHTS = [
        'E1' => 0.25, 'E2' => 0.15, 'E3' => 0.20,
        'E4' => 0.15, 'E5' => 0.10, 'E6' => 0.15,
    ];

    public function getDimWeightDefaults(): array
    {
        $row = DB::table('system_settings')->where('key', 'dim_weight_defaults')->first();
        if (!$row) return self::DEFAULT_DIM_WEIGHTS;

        $decoded = json_decode($row->value, true);
        return is_array($decoded) ? $decoded : self::DEFAULT_DIM_WEIGHTS;
    }

    public function setDimWeightDefaults(array $weights): void
    {
        $sum = array_sum($weights);
        if ($sum < 0.99 || $sum > 1.01) {
            throw new \InvalidArgumentException('dim_weights 合計須等於 1.0（允許 ±0.01 容差）');
        }

        DB::table('system_settings')->upsert([
            [
                'key'        => 'dim_weight_defaults',
                'value'      => json_encode($weights),
                'updated_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ], ['key'], ['value', 'updated_by', 'updated_at']);
    }
}
