<?php

namespace App\Http\Controllers\Api\Suppliers;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\SupplierContact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierProfileController extends Controller
{
    public function update(Request $request, Supplier $supplier): JsonResponse
    {
        $validated = $request->validate([
            'sustainability_email' => ['required', 'email'],
            'address'              => ['required', 'string', 'max:500'],
        ]);

        // 更新或建立永續主管聯絡人
        $contact = $supplier->contacts()->where('is_primary', true)->first();
        if ($contact) {
            $contact->update(['email' => $validated['sustainability_email']]);
        } else {
            SupplierContact::create([
                'supplier_id' => $supplier->id,
                'name'        => $supplier->name,
                'email'       => $validated['sustainability_email'],
                'is_primary'  => true,
            ]);
        }

        $supplier->update([
            'address'          => $validated['address'],
            'profile_completed' => true,
            'onboarding_stage' => 'invited',
        ]);

        return response()->json([
            'success' => true,
            'message' => '主檔資料已補齊，供應商已正式啟動',
            'data'    => $supplier->fresh()->load('contacts'),
        ]);
    }
}
