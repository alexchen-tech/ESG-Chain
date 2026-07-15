<?php

namespace App\Http\Controllers\Api\Customers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customers\StoreCustomerContactRequest;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Services\Customers\CustomerContactService;
use Illuminate\Http\JsonResponse;

class CustomerContactController extends Controller
{
    public function __construct(private readonly CustomerContactService $service) {}

    public function store(StoreCustomerContactRequest $request, Customer $customer): JsonResponse
    {
        $contact = $this->service->addContact($customer, $request->validated());

        return response()->json(['success' => true, 'data' => $contact], 201);
    }

    public function destroy(Customer $customer, CustomerContact $contact): JsonResponse
    {
        $this->service->removeContact($contact);

        return response()->json(['success' => true, 'message' => '聯絡人已刪除']);
    }
}
