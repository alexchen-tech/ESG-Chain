<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Models\SasbDisclosureTopic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SasbDisclosureTopicController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $topics = SasbDisclosureTopic::query()
            ->when($request->industry_id, fn($q, $v) => $q->where('sasb_industry_id', $v))
            ->when($request->esg_category, fn($q, $v) => $q->where('esg_category', $v))
            ->orderBy('esg_category')
            ->orderBy('topic_name')
            ->get();

        return response()->json(['success' => true, 'data' => $topics]);
    }
}
