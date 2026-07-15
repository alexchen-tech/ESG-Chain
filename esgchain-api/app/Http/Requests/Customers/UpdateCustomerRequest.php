<?php

namespace App\Http\Requests\Customers;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerRequest extends FormRequest
{
    public const EU_COUNTRIES = StoreCustomerRequest::EU_COUNTRIES;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $customerId  = $this->route('customer')?->id ?? $this->route('customer');
        $countryCode = strtoupper($this->input('country_code', ''));

        return [
            'code'          => 'sometimes|string|max:50|unique:customers,code,' . $customerId,
            'name'          => 'sometimes|string|max:255',
            'country_code'  => 'sometimes|string|size:2',
            'customer_type' => 'sometimes|in:brand,retailer,distributor,agent,oem',
            'eori_number'   => [
                'nullable', 'string', 'max:20',
                'regex:/^[A-Z]{2}[A-Z0-9]{1,15}$/',
                function ($attribute, $value, $fail) use ($countryCode) {
                    if ($value && $countryCode && strtoupper(substr($value, 0, 2)) !== $countryCode) {
                        $fail('EORI 前兩碼必須與國家代碼一致。');
                    }
                },
            ],
            'vat_number'   => 'nullable|string|max:50',
            'address'      => 'nullable|string',
            'website'      => 'nullable|string|max:255',
            'notes'        => 'nullable|string',
            'status'       => 'nullable|in:active,inactive',
        ];
    }
}
