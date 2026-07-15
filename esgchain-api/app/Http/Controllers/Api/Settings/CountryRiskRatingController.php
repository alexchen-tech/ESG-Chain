<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Models\CountryRiskRating;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CountryRiskRatingController extends Controller
{
    public function index(): JsonResponse
    {
        if (!auth()->user()?->hasAnyRole(['admin', 'sustain'])) {
            return response()->json(['success' => false, 'message' => '無權限'], 403);
        }

        $ratings = CountryRiskRating::orderBy('country_code')->get();

        return response()->json(['success' => true, 'data' => $ratings]);
    }

    public function store(Request $request): JsonResponse
    {
        if (!auth()->user()?->hasAnyRole(['admin', 'sustain'])) {
            return response()->json(['success' => false, 'message' => '無權限'], 403);
        }

        $data = $request->validate([
            'country_code'                => ['required', 'string', 'size:2', 'unique:country_risk_ratings,country_code'],
            'country_name'                => ['required', 'string', 'max:100'],
            'labor_risk'                  => ['required', 'integer', 'min:1', 'max:5'],
            'env_risk'                    => ['required', 'integer', 'min:1', 'max:5'],
            'geo_risk'                    => ['required', 'integer', 'min:1', 'max:5'],
            'source'                      => ['nullable', 'string', 'max:50'],
            'sub_scores'                  => ['nullable', 'array'],
            'sub_scores.political'        => ['nullable', 'integer', 'min:1', 'max:5'],
            'sub_scores.environmental'    => ['nullable', 'integer', 'min:1', 'max:5'],
            'sub_scores.social'           => ['nullable', 'integer', 'min:1', 'max:5'],
            'sub_scores.regulatory'       => ['nullable', 'integer', 'min:1', 'max:5'],
        ]);

        $data['country_code'] = strtoupper($data['country_code']);
        $data['source'] = $data['source'] ?? 'manual';

        $rating = CountryRiskRating::create($data);

        return response()->json(['success' => true, 'data' => $rating], 201);
    }

    public function update(Request $request, CountryRiskRating $countryRiskRating): JsonResponse
    {
        if (!auth()->user()?->hasAnyRole(['admin', 'sustain'])) {
            return response()->json(['success' => false, 'message' => '無權限'], 403);
        }

        $data = $request->validate([
            'labor_risk'               => ['sometimes', 'required', 'integer', 'min:1', 'max:5'],
            'env_risk'                 => ['sometimes', 'required', 'integer', 'min:1', 'max:5'],
            'geo_risk'                 => ['sometimes', 'required', 'integer', 'min:1', 'max:5'],
            'sub_scores'               => ['sometimes', 'nullable', 'array'],
            'sub_scores.political'     => ['sometimes', 'nullable', 'integer', 'min:1', 'max:5'],
            'sub_scores.environmental' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:5'],
            'sub_scores.social'        => ['sometimes', 'nullable', 'integer', 'min:1', 'max:5'],
            'sub_scores.regulatory'    => ['sometimes', 'nullable', 'integer', 'min:1', 'max:5'],
        ]);

        $countryRiskRating->update($data);

        return response()->json(['success' => true, 'data' => $countryRiskRating->fresh()]);
    }
}
