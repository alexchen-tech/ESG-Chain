<?php

namespace App\Services\Customers;

use App\Http\Requests\Customers\StoreCustomerRequest;
use App\Models\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CustomerService
{
    public function list(array $filters): LengthAwarePaginator
    {
        $query = Customer::withCount(['contacts', 'tradeGoods']);

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('code', 'like', '%' . $filters['search'] . '%');
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['customer_type'])) {
            $query->where('customer_type', $filters['customer_type']);
        }

        return $query->orderBy('name')->paginate($filters['per_page'] ?? 20);
    }

    public function create(array $data, string $createdBy): array
    {
        $data['created_by'] = $createdBy;
        $customer = Customer::create($data);

        $warnings = [];
        if ($this->isEuCountry($customer->country_code) && empty($customer->eori_number)) {
            $warnings[] = '歐盟進口商建議填寫 EORI Number 以符合 CBAM 申報要求。';
        }

        return ['customer' => $customer, 'warnings' => $warnings];
    }

    public function update(Customer $customer, array $data): Customer
    {
        $customer->update($data);
        return $customer->fresh();
    }

    public function destroy(Customer $customer): void
    {
        $customer->delete();
    }

    public function isEuCountry(string $countryCode): bool
    {
        return in_array(strtoupper($countryCode), StoreCustomerRequest::EU_COUNTRIES, true);
    }
}
