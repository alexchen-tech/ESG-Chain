<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Models\SasbIndustry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SasbIndustryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = SasbIndustry::query()
            ->when($request->sector, fn($q, $v) => $q->where('sector', $v))
            ->orderBy('sector')
            ->orderBy('industry')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $data,
            'message' => '',
        ]);
    }
}
