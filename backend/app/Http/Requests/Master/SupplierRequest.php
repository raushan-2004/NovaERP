<?php

declare(strict_types=1);

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $supplierParam = $this->route('supplier');
        $supplierId = $supplierParam instanceof \Illuminate\Database\Eloquent\Model ? $supplierParam->id : $supplierParam;

        return [
            'company_id'    => ['required', 'exists:companies,id'],
            'supplier_code' => ['required', 'string', 'max:50', Rule::unique('suppliers')->ignore($supplierId)],
            'name'          => ['required', 'string', 'max:255'],
            'email'         => ['nullable', 'email', 'max:255'],
            'phone'         => ['nullable', 'string', 'max:50'],
            'address'       => ['nullable', 'string'],
            'status'        => ['nullable', 'string', 'in:active,inactive'],
        ];
    }
}
