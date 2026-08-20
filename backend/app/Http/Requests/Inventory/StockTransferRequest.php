<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class StockTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from_warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'to_warehouse_id'   => ['required', 'integer', 'exists:warehouses,id', 'different:from_warehouse_id'],
            'product_id'        => ['required', 'integer', 'exists:products,id'],
            'quantity'          => ['required', 'numeric', 'gt:0'],
            'notes'             => ['nullable', 'string', 'max:2000'],
        ];
    }
}
