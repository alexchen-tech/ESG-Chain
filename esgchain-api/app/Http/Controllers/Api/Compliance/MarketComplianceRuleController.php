<?php

namespace App\Http\Controllers\Api\Compliance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Compliance\StoreMarketComplianceRuleRequest;
use App\Http\Requests\Compliance\UpdateMarketComplianceRuleRequest;
use App\Models\MarketComplianceRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketComplianceRuleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = MarketComplianceRule::query();

        if ($request->filled('market')) {
            $query->where('market', $request->input('market'));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $rules = $query->orderBy('market')->orderBy('doc_type')->get();

        return response()->json(['data' => $rules]);
    }

    public function store(StoreMarketComplianceRuleRequest $request): JsonResponse
    {
        $rule = MarketComplianceRule::create($request->validated());

        return response()->json(['data' => $rule], 201);
    }

    public function update(UpdateMarketComplianceRuleRequest $request, MarketComplianceRule $marketComplianceRule): JsonResponse
    {
        $marketComplianceRule->update($request->validated());

        return response()->json(['data' => $marketComplianceRule->fresh()]);
    }

    public function destroy(MarketComplianceRule $marketComplianceRule): JsonResponse
    {
        $marketComplianceRule->update(['is_active' => false]);

        return response()->json(['message' => '規則已停用。']);
    }
}
