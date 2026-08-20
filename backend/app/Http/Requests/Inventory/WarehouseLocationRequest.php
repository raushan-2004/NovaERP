<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Database\Eloquent\Model;

class WarehouseLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $param = $this->route('warehouse_location');
        $id    = $param instanceof Model ? $param->id : $param;

        return [
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'code'         => ['required', 'string', 'max:50'],
            'name'         => ['required', 'string', 'max:255'],
            'status'       => ['sometimes', 'in:active,inactive'],
        ];
    }
}
