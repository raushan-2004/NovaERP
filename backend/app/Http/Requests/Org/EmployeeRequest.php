<?php

declare(strict_types=1);

namespace App\Http\Requests\Org;

use App\Models\Branch;
use App\Models\Department;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $employeeParam = $this->route('employee');
        $employeeId = $employeeParam instanceof \Illuminate\Database\Eloquent\Model ? $employeeParam->id : $employeeParam;

        return [
            'employee_code'     => [
                'required',
                'string',
                'max:50',
                Rule::unique('employees')->ignore($employeeId),
            ],
            'first_name'        => ['required', 'string', 'max:100'],
            'last_name'         => ['required', 'string', 'max:100'],
            'email'             => [
                'required',
                'email',
                'max:255',
                Rule::unique('employees')->ignore($employeeId),
            ],
            'phone'             => ['nullable', 'string', 'max:50'],
            'date_of_birth'     => ['nullable', 'date'],
            'joining_date'      => ['required', 'date'],
            'designation'       => ['required', 'string', 'max:100'],
            'employment_status' => ['nullable', 'string', 'in:active,inactive,terminated,suspended'],
            'company_id'        => ['required', 'exists:companies,id'],
            'branch_id'         => ['required', 'exists:branches,id'],
            'department_id'     => ['required', 'exists:departments,id'],
            'user_id'           => [
                'nullable',
                'exists:users,id',
                Rule::unique('employees')->ignore($employeeId),
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $companyId = $this->input('company_id');
            $branchId = $this->input('branch_id');
            $departmentId = $this->input('department_id');

            if ($companyId && $branchId) {
                $branch = Branch::find($branchId);
                if ($branch && (int) $branch->company_id !== (int) $companyId) {
                    $validator->errors()->add('branch_id', 'The branch company_id must match the employee company_id.');
                }
            }

            if ($companyId && $departmentId) {
                $department = Department::find($departmentId);
                if ($department && (int) $department->company_id !== (int) $companyId) {
                    $validator->errors()->add('department_id', 'The department company_id must match the employee company_id.');
                }
            }

            if ($branchId && $departmentId) {
                $department = Department::find($departmentId);
                if ($department && (int) $department->branch_id !== (int) $branchId) {
                    $validator->errors()->add('department_id', 'The department branch_id must match the employee branch_id.');
                }
            }
        });
    }
}
