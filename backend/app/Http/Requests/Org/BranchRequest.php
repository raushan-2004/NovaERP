<?php

declare(strict_types=1);

namespace App\Http\Requests\Org;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $branchParam = $this->route('branch');
        $branchId = $branchParam instanceof \Illuminate\Database\Eloquent\Model ? $branchParam->id : $branchParam;

        return [
            'company_id'  => ['required', 'exists:companies,id'],
            'name'        => ['required', 'string', 'max:255'],
            'branch_code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('branches')
                    ->where('company_id', $this->input('company_id'))
                    ->ignore($branchId),
            ],
            'address'     => ['required', 'string'],
            'phone'       => ['nullable', 'string', 'max:50'],
            'email'       => ['nullable', 'email', 'max:255'],
            'status'      => ['nullable', 'string', 'in:active,inactive'],
        ];
    }
}
