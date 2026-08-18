<?php

declare(strict_types=1);

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $productParam = $this->route('product');
        $productId = $productParam instanceof \Illuminate\Database\Eloquent\Model ? $productParam->id : $productParam;

        return [
            'sku'             => ['required', 'string', 'max:100', Rule::unique('products')->ignore($productId)],
            'name'            => ['required', 'string', 'max:255'],
            'description'     => ['nullable', 'string'],
            'category_id'     => ['required', 'exists:categories,id'],
            'brand_id'        => ['required', 'exists:brands,id'],
            'unit_id'         => ['required', 'exists:units,id'],
            'product_type'    => ['required', 'string', 'in:raw_material,finished_good,service'],
            'status'          => ['nullable', 'string', 'in:active,inactive'],
            'track_inventory' => ['nullable', 'boolean'],
        ];
    }
}
