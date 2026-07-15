<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login(
            $request->email,
            $request->password
        );

        if (!$result) {
            return response()->json([
                'success' => false,
                'error_code' => 'UNAUTHORIZED',
                'message' => '帳號或密碼錯誤',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'data' => $result,
            'message' => '登入成功',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->authService->me($request->user()),
            'message' => '',
        ]);
    }

    public function refresh(Request $request): JsonResponse
    {
        $request->validate(['refresh_token' => ['required', 'string']]);

        $result = $this->authService->refresh($request->refresh_token);

        if (!$result) {
            return response()->json([
                'success' => false,
                'error_code' => 'UNAUTHORIZED',
                'message' => 'Refresh Token 無效或已過期',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'data' => $result,
            'message' => 'Token 已換發',
        ]);
    }

    public function logout(): JsonResponse
    {
        $this->authService->logout();

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => '已登出',
        ]);
    }
}
