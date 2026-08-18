<?php

declare(strict_types=1);

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $warehouseParam = $this->route('warehouse');
        $warehouseId = $warehouseParam instanceof \Illuminate\Database\Eloquent\Model ? $warehouseParam->id : $warehouseParam;

        return [
            'branch_id'      => ['required', 'exists:branches,id'],
            'warehouse_code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('warehouses')
                    ->where('branch_id', $this->input('branch_id'))
                    ->ignore($warehouseId),
            ],
            'name'           => ['required', 'string', 'max:255'],
            'address'        => ['nullable', 'string'],
            'status'         => ['nullable', 'string', 'in:active,inactive'],
        ];
    }
}
