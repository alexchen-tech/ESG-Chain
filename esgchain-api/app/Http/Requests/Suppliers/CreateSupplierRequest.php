<?php

namespace App\Http\Requests\Suppliers;

use Illuminate\Foundation\Http\FormRequest;

class CreateSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', 'unique:suppliers,code'],
            'country' => ['nullable', 'string', 'size:2'],
            'industry' => ['nullable', 'string', 'max:100'],
            'tier' => ['nullable', 'integer', 'in:1,2,3'],
            'group_id' => ['nullable', 'uuid', 'exists:supplier_groups,id'],
            'address' => ['nullable', 'string'],
            'website' => ['nullable', 'url'],
            'contacts' => ['nullable', 'array'],
            'contacts.*.name' => ['required_with:contacts', 'string'],
            'contacts.*.email' => ['required_with:contacts', 'email', 'unique:supplier_contacts,email'],
            'contacts.*.phone' => ['nullable', 'string'],
            'contacts.*.title' => ['nullable', 'string'],
            'contacts.*.is_primary' => ['nullable', 'boolean'],
        ];
    }
}
