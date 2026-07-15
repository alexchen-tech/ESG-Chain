<?php

namespace App\Http\Controllers\Api\Risk;

use App\Http\Controllers\Controller;
use App\Services\Risk\AiRiskSuggestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiRiskSuggestionController extends Controller
{
    public function __construct(private AiRiskSuggestionService $service) {}

    public function generate(Request $request, string $supplierId): JsonResponse
    {
        $force = (bool) $request->input('force', false);
        $token = $request->bearerToken() ?? '';

        $result = $this->service->getSuggestion($supplierId, $force, $token);

        return response()->json($result);
    }
}
