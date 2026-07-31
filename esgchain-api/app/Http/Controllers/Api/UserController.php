<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\CreateUserRequest;
use App\Http\Requests\User\UpdateUserRolesRequest;
use App\Models\User;
use App\Services\User\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(private readonly UserService $service) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['role', 'is_active', 'q', 'page', 'per_page']);
        $paginated = $this->service->list($filters);

        $items = collect($paginated->items())->map(function (User $user) {
            $arr = $user->toArray();
            $arr['roles'] = $user->getRoleNames();
            return $arr;
        })->all();

        return response()->json([
            'success' => true,
            'data' => $items,
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
            ],
            'message' => '',
        ]);
    }

    public function store(CreateUserRequest $request): JsonResponse
    {
        $user = $this->service->create($request->validated(), $request->user()->email);

        $arr = $user->toArray();
        $arr['roles'] = $user->getRoleNames();

        return response()->json(['success' => true, 'data' => $arr, 'message' => '使用者已建立'], 201);
    }

    public function updateRoles(UpdateUserRolesRequest $request, User $user): JsonResponse
    {
        $updated = $this->service->updateRoles(
            $user,
            $request->validated()['roles'],
            $request->user()->email,
            $request->validated()['reason'] ?? null
        );

        $arr = $updated->toArray();
        $arr['roles'] = $updated->getRoleNames();

        return response()->json(['success' => true, 'data' => $arr, 'message' => '角色已更新']);
    }

    public function toggleActive(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $result = $this->service->setActive(
            $user,
            $validated['is_active'],
            $request->user()->email,
            $validated['reason'] ?? null
        );

        $arr = $result['user']->toArray();
        $arr['roles'] = $result['user']->getRoleNames();

        return response()->json([
            'success' => true,
            'data' => $arr,
            'warnings' => $result['warnings'],
            'message' => $validated['is_active'] ? '帳號已啟用' : '帳號已停用',
        ]);
    }

    public function resetPassword(Request $request, User $user): JsonResponse
    {
        $newPassword = $this->service->resetPassword($user, $request->user()->email);

        return response()->json([
            'success' => true,
            'data' => ['new_password' => $newPassword],
            'message' => '密碼已重設，請立即轉告使用者（此密碼僅顯示一次）',
        ]);
    }
}
