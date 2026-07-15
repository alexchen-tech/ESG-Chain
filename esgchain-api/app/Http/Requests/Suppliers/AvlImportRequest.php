<?php

namespace App\Http\Requests\Suppliers;

use Illuminate\Foundation\Http\FormRequest;

class AvlImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => '請上傳 CSV 檔案',
            'file.mimes'    => '僅支援 CSV 格式',
        ];
    }
}
