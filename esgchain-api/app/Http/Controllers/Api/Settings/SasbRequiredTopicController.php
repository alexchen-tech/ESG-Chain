<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Services\Settings\SasbRequiredTopicService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SasbRequiredTopicController extends Controller
{
    public function __construct(private readonly SasbRequiredTopicService $service) {}

    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->service->getAll()]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sasb_industry_code' => ['required', 'string', 'max:20'],
            'tag_slug'           => ['required', 'string', 'max:80'],
            'rationale'          => ['nullable', 'string'],
        ]);

        $topic = $this->service->create($validated);

        return response()->json(['data' => $topic], 201);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->service->delete($id);

        return response()->json(['message' => '已刪除']);
    }
}
