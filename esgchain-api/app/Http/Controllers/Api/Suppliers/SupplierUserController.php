<?php

namespace App\Http\Controllers\Api\Suppliers;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\InviteSupplierContactRequest;
use App\Models\Supplier;
use App\Services\User\SupplierUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierUserController extends Controller
{
    public function __construct(private readonly SupplierUserService $service) {}

    public function index(Request $request, Supplier $supplier): JsonResponse
    {
        $user = $request->user();

        // admin 可查任一供應商；supplier/sup_esg 角色僅能查詢自己所屬供應商，
        // 不揭露該供應商是否存在帳號資料（統一回傳 403，不區分原因）。
        if (!$user->hasRole('admin')) {
            if (!$user->hasAnyRole(['supplier', 'sup_esg']) || $user->supplier_id !== $supplier->id) {
                return response()->json(['success' => false, 'message' => '無權限'], 403);
            }
        }

        $users = $this->service->listBySupplier($supplier)->map(function ($u) {
            $arr = $u->toArray();
            $arr['roles'] = $u->getRoleNames();
            return $arr;
        });

        return response()->json(['success' => true, 'data' => $users, 'message' => '']);
    }

    public function store(InviteSupplierContactRequest $request, Supplier $supplier): JsonResponse
    {
        $user = $this->service->invite($supplier, $request->validated(), $request->user()->email);

        $arr = $user->toArray();
        $arr['roles'] = $user->getRoleNames();

        return response()->json(['success' => true, 'data' => $arr, 'message' => '聯絡人帳號已建立'], 201);
    }
}
