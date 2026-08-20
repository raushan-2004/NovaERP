<?php

declare(strict_types=1);

namespace App\Http\Requests\Purchasing;

use Illuminate\Foundation\Http\FormRequest;

class GoodsReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'purchase_order_id'              => ['required', 'integer', 'exists:purchase_orders,id'],
            'warehouse_id'                   => ['required', 'integer', 'exists:warehouses,id'],
            'received_date'                  => ['required', 'date'],
            'notes'                          => ['nullable', 'string', 'max:2000'],
            'lines'                          => ['required', 'array', 'min:1'],
            'lines.*.purchase_order_line_id' => ['required', 'integer', 'exists:purchase_order_lines,id'],
            'lines.*.quantity_received'      => ['required', 'numeric', 'gt:0'],
            'lines.*.notes'                  => ['nullable', 'string', 'max:500'],
        ];
    }
}
