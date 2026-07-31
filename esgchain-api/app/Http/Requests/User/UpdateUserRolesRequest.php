<?php

namespace App\Http\Requests\User;

use App\Services\User\UserService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRolesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [Rule::in(UserService::INTERNAL_ROLES)],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
