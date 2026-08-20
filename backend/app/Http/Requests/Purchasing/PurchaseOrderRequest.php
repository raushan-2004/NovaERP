<?php

declare(strict_types=1);

namespace App\Http\Requests\Purchasing;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseOrderRequest extends FormRequest
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
            'purchase_request_id'     => ['nullable', 'integer', 'exists:purchase_requests,id'],
            'order_date'              => ['required', 'date'],
            'expected_delivery_date'  => ['nullable', 'date', 'after_or_equal:order_date'],
            'notes'                   => ['nullable', 'string', 'max:2000'],
            'lines'                   => ['required', 'array', 'min:1'],
            'lines.*.product_id'      => ['required', 'integer', 'exists:products,id'],
            'lines.*.unit_id'         => ['required', 'integer', 'exists:units,id'],
            'lines.*.quantity'        => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price'      => ['required', 'numeric', 'gte:0'],
            'lines.*.tax_rate'        => ['nullable', 'numeric', 'between:0,1'],
        ];
    }
}
