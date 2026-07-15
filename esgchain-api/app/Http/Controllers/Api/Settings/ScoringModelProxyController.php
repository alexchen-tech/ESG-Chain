<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ScoringModelProxyController extends Controller
{
    private function aiUrl(): string
    {
        return config('services.ai.url', env('AI_SERVICE_URL', 'http://esgchain-ai:8000'));
    }

    public function index(Request $request): JsonResponse
    {
        $response = Http::timeout(15)->withToken($request->bearerToken())->get("{$this->aiUrl()}/ai/v1/scoring/scoring-models", $request->only(['industry_code', 'is_active']));
        return response()->json($response->json(), $response->status());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'                => ['required', 'string', 'max:100'],
            'sasb_industry_code'  => ['nullable', 'string', 'max:20'],
            'weight_e'            => ['required', 'numeric', 'min:0', 'max:1'],
            'weight_s'            => ['required', 'numeric', 'min:0', 'max:1'],
            'weight_g'            => ['required', 'numeric', 'min:0', 'max:1'],
            'grade_a_threshold'   => ['nullable', 'numeric'],
            'grade_b_threshold'   => ['nullable', 'numeric'],
            'grade_c_threshold'   => ['nullable', 'numeric'],
            'grade_d_threshold'   => ['nullable', 'numeric'],
            'description'         => ['nullable', 'string'],
        ]);

        $response = Http::timeout(15)->withToken($request->bearerToken())->post("{$this->aiUrl()}/ai/v1/scoring/scoring-models", $validated);
        return response()->json($response->json(), $response->status());
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'name'               => ['sometimes', 'string', 'max:100'],
            'weight_e'           => ['sometimes', 'numeric', 'min:0', 'max:1'],
            'weight_s'           => ['sometimes', 'numeric', 'min:0', 'max:1'],
            'weight_g'           => ['sometimes', 'numeric', 'min:0', 'max:1'],
            'grade_a_threshold'  => ['sometimes', 'numeric'],
            'grade_b_threshold'  => ['sometimes', 'numeric'],
            'grade_c_threshold'  => ['sometimes', 'numeric'],
            'grade_d_threshold'  => ['sometimes', 'numeric'],
            'is_active'          => ['sometimes', 'boolean'],
            'description'        => ['sometimes', 'nullable', 'string'],
        ]);

        $response = Http::timeout(15)->withToken($request->bearerToken())->put("{$this->aiUrl()}/ai/v1/scoring/scoring-models/{$id}", $validated);
        return response()->json($response->json(), $response->status());
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $response = Http::timeout(15)->withToken($request->bearerToken())->delete("{$this->aiUrl()}/ai/v1/scoring/scoring-models/{$id}");
        return response()->json($response->json(), $response->status());
    }
}
