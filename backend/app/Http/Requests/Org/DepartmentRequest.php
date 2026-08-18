<?php

declare(strict_types=1);

namespace App\Http\Requests\Org;

use App\Models\Branch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $deptParam = $this->route('department');
        $departmentId = $deptParam instanceof \Illuminate\Database\Eloquent\Model ? $deptParam->id : $deptParam;

        return [
            'company_id'      => ['required', 'exists:companies,id'],
            'branch_id'       => ['required', 'exists:branches,id'],
            'name'            => ['required', 'string', 'max:255'],
            'department_code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('departments')
                    ->where('branch_id', $this->input('branch_id'))
                    ->ignore($departmentId),
            ],
            'status'          => ['nullable', 'string', 'in:active,inactive'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $companyId = $this->input('company_id');
            $branchId = $this->input('branch_id');

            if ($companyId && $branchId) {
                $branch = Branch::find($branchId);
                if ($branch && (int) $branch->company_id !== (int) $companyId) {
                    $validator->errors()->add('branch_id', 'The branch company_id must match the department company_id.');
                }
            }
        });
    }
}
