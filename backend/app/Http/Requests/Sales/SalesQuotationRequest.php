<?php

declare(strict_types=1);

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class SalesQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => 'required|exists:customers,id',
            'branch_id' => 'required|exists:branches,id',
            'company_id' => 'required|exists:companies,id',
            'quotation_date' => 'required|date',
            'valid_until' => 'nullable|date|after_or_equal:quotation_date',
            'notes' => 'nullable|string',
            'lines' => 'required|array|min:1',
            'lines.*.product_id' => 'required|exists:products,id',
            'lines.*.quantity' => 'required|numeric|gt:0',
            'lines.*.unit_id' => 'required|exists:units,id',
            'lines.*.unit_price' => 'required|numeric|gte:0',
            'lines.*.discount' => 'nullable|numeric|gte:0',
            'lines.*.tax_rate' => 'nullable|numeric|between:0,1',
        ];
    }
}
