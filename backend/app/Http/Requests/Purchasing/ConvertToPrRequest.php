<?php

declare(strict_types=1);

namespace App\Http\Requests\Purchasing;

use Illuminate\Foundation\Http\FormRequest;

class ConvertToPrRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id'              => ['required', 'integer', 'exists:companies,id'],
            'branch_id'               => ['required', 'integer', 'exists:branches,id'],
            'supplier_id'             => ['required', 'integer', 'exists:suppliers,id'],
            'order_date'              => ['required', 'date'],
            'expected_delivery_date'  => ['nullable', 'date', 'after_or_equal:order_date'],
            'notes'                   => ['nullable', 'string', 'max:2000'],
            'unit_price_map'          => ['required', 'array'],
            'unit_price_map.*'        => ['required', 'numeric', 'gte:0'],
            'tax_rate_map'            => ['nullable', 'array'],
            'tax_rate_map.*'          => ['nullable', 'numeric', 'between:0,1'],
        ];
    }
}
