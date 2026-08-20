<?php

declare(strict_types=1);

namespace App\Http\Requests\Purchasing;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id'    => ['required', 'integer', 'exists:companies,id'],
            'branch_id'     => ['required', 'integer', 'exists:branches,id'],
            'required_date' => ['nullable', 'date'],
            'notes'         => ['nullable', 'string', 'max:2000'],
            'lines'         => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'lines.*.unit_id'    => ['required', 'integer', 'exists:units,id'],
            'lines.*.quantity'   => ['required', 'numeric', 'gt:0'],
            'lines.*.notes'      => ['nullable', 'string', 'max:500'],
        ];
    }
}
