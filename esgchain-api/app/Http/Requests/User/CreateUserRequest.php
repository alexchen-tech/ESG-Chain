<?php

namespace App\Http\Requests\User;

use App\Services\User\UserService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'role' => ['required', Rule::in(UserService::INTERNAL_ROLES)],
        ];
    }
}
