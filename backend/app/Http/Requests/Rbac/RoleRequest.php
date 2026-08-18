<?php

declare(strict_types=1);

namespace App\Http\Requests\Rbac;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $roleParam = $this->route('role');
        $roleId = $roleParam instanceof \Illuminate\Database\Eloquent\Model ? $roleParam->id : $roleParam;

        return [
            'name'          => ['required', 'string', 'max:100', Rule::unique('roles')->ignore($roleId)],
            'description'   => ['nullable', 'string', 'max:255'],
            'status'        => ['nullable', 'string', 'in:active,inactive'],
            'permissions'   => ['nullable', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
            'users'         => ['nullable', 'array'],
            'users.*'       => ['integer', 'exists:users,id'],
        ];
    }
}
