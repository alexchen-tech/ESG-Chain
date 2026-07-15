<?php

namespace App\Http\Controllers\Api\Suppliers;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\SupplierContact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierContactController extends Controller
{
    public function store(Request $request, Supplier $supplier): JsonResponse
    {
        $validated = $request->validate([
            'name'       => ['required', 'string', 'max:100'],
            'title'      => ['nullable', 'string', 'max:100'],
            'email'      => ['nullable', 'email', 'max:150'],
            'phone'      => ['nullable', 'string', 'max:50'],
            'is_primary' => ['boolean'],
        ]);

        if (!empty($validated['is_primary'])) {
            $supplier->contacts()->update(['is_primary' => false]);
        }

        $contact = $supplier->contacts()->create($validated);

        return response()->json(['success' => true, 'data' => $contact], 201);
    }

    public function update(Request $request, Supplier $supplier, SupplierContact $contact): JsonResponse
    {
        abort_if($contact->supplier_id !== $supplier->id, 404);

        $validated = $request->validate([
            'name'       => ['sometimes', 'string', 'max:100'],
            'title'      => ['sometimes', 'nullable', 'string', 'max:100'],
            'email'      => ['sometimes', 'nullable', 'email', 'max:150'],
            'phone'      => ['sometimes', 'nullable', 'string', 'max:50'],
            'is_primary' => ['sometimes', 'boolean'],
        ]);

        if (!empty($validated['is_primary'])) {
            $supplier->contacts()->where('id', '!=', $contact->id)->update(['is_primary' => false]);
        }

        $contact->update($validated);

        return response()->json(['success' => true, 'data' => $contact->fresh()]);
    }

    public function destroy(Supplier $supplier, SupplierContact $contact): JsonResponse
    {
        abort_if($contact->supplier_id !== $supplier->id, 404);
        $contact->delete();

        return response()->json(['success' => true]);
    }
}
