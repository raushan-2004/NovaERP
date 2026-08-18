<?php

declare(strict_types=1);

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $categoryParam = $this->route('category');
        $categoryId = $categoryParam instanceof \Illuminate\Database\Eloquent\Model ? $categoryParam->id : $categoryParam;

        return [
            'name'        => ['required', 'string', 'max:255'],
            'code'        => ['required', 'string', 'max:50', Rule::unique('categories')->ignore($categoryId)],
            'description' => ['nullable', 'string'],
            'status'      => ['nullable', 'string', 'in:active,inactive'],
        ];
    }
}
