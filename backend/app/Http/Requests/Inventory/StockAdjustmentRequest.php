<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class StockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id'        => ['required', 'integer', 'exists:products,id'],
            'warehouse_id'      => ['required', 'integer', 'exists:warehouses,id'],
            'adjusted_quantity' => ['required', 'numeric', 'not_in:0'],
            'reason'            => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }
}
