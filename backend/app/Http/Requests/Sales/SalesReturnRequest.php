<?php

declare(strict_types=1);

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class SalesReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'delivery_id' => 'required|exists:deliveries,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'returned_date' => 'required|date',
            'reason' => 'required|string|max:255',
            'lines' => 'required|array|min:1',
            'lines.*.delivery_line_id' => 'required|exists:delivery_lines,id',
            'lines.*.quantity' => 'required|numeric|gt:0',
            'lines.*.notes' => 'nullable|string|max:255',
        ];
    }
}
