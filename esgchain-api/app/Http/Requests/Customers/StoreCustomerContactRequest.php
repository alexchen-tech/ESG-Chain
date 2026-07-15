<?php

namespace App\Http\Requests\Customers;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'       => 'required|string|max:100',
            'email'      => 'required|email|max:150|unique:customer_contacts,email',
            'phone'      => 'nullable|string|max:50',
            'title'      => 'nullable|string|max:100',
            'is_primary' => 'nullable|boolean',
        ];
    }
}
