<?php

declare(strict_types=1);

namespace App\Http\Requests\Purchasing;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'goods_receipt_id'                  => ['required', 'integer', 'exists:goods_receipts,id'],
            'return_date'                       => ['required', 'date'],
            'reason'                            => ['required', 'string', 'min:5', 'max:2000'],
            'lines'                             => ['required', 'array', 'min:1'],
            'lines.*.goods_receipt_line_id'     => ['required', 'integer', 'exists:goods_receipt_lines,id'],
            'lines.*.quantity_returned'         => ['required', 'numeric', 'gt:0'],
            'lines.*.notes'                     => ['nullable', 'string', 'max:500'],
        ];
    }
}
