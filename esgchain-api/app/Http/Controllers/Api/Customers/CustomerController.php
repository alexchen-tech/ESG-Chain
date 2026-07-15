<?php

namespace App\Http\Controllers\Api\Customers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customers\StoreCustomerRequest;
use App\Http\Requests\Customers\UpdateCustomerRequest;
use App\Models\Customer;
use App\Services\Customers\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function __construct(private readonly CustomerService $service) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->service->list($request->only(['search', 'status', 'customer_type', 'per_page']));

        return response()->json([
            'success'    => true,
            'data'       => $paginator->items(),
            'pagination' => [
                'total'        => $paginator->total(),
                'per_page'     => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
            ],
        ]);
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        ['customer' => $customer, 'warnings' => $warnings] =
            $this->service->create($request->validated(), $request->user()->id);

        return response()->json([
            'success'  => true,
            'data'     => $customer->load('contacts'),
            'warnings' => $warnings,
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $customer = Customer::with('contacts')->findOrFail($id);

        return response()->json(['success' => true, 'data' => $customer]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        $updated = $this->service->update($customer, $request->validated());

        return response()->json(['success' => true, 'data' => $updated]);
    }

    public function destroy(Customer $customer): JsonResponse
    {
        $this->service->destroy($customer);

        return response()->json(['success' => true, 'message' => '客戶已刪除']);
    }
}
