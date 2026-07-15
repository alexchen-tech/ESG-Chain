<?php

namespace App\Http\Requests\Customers;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
{
    // EU 成員國 ISO 代碼
    public const EU_COUNTRIES = [
        'AT','BE','BG','CY','CZ','DE','DK','EE','ES','FI',
        'FR','GR','HR','HU','IE','IT','LT','LU','LV','MT',
        'NL','PL','PT','RO','SE','SI','SK',
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $countryCode = strtoupper($this->input('country_code', ''));
        $isEu        = in_array($countryCode, self::EU_COUNTRIES, true);

        return [
            'code'          => 'required|string|max:50|unique:customers,code',
            'name'          => 'required|string|max:255',
            'country_code'  => 'required|string|size:2',
            'customer_type' => 'required|in:brand,retailer,distributor,agent,oem',
            'eori_number'   => [
                'nullable', 'string', 'max:20',
                'regex:/^[A-Z]{2}[A-Z0-9]{1,15}$/',
                function ($attribute, $value, $fail) use ($countryCode) {
                    if ($value && strtoupper(substr($value, 0, 2)) !== $countryCode) {
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

    public function isEuCustomer(): bool
    {
        return in_array(strtoupper($this->input('country_code', '')), self::EU_COUNTRIES, true);
    }
}
