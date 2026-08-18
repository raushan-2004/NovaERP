<?php

declare(strict_types=1);

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $unitParam = $this->route('unit');
        $unitId = $unitParam instanceof \Illuminate\Database\Eloquent\Model ? $unitParam->id : $unitParam;

        return [
            'name'         => ['required', 'string', 'max:255'],
            'abbreviation' => ['required', 'string', 'max:20', Rule::unique('units')->ignore($unitId)],
            'status'       => ['nullable', 'string', 'in:active,inactive'],
        ];
    }
}
