<?php

declare(strict_types=1);

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $customerParam = $this->route('customer');
        $customerId = $customerParam instanceof \Illuminate\Database\Eloquent\Model ? $customerParam->id : $customerParam;

        return [
            'company_id'       => ['required', 'exists:companies,id'],
            'customer_code'    => ['required', 'string', 'max:50', Rule::unique('customers')->ignore($customerId)],
            'name'             => ['required', 'string', 'max:255'],
            'email'            => ['nullable', 'email', 'max:255'],
            'phone'            => ['nullable', 'string', 'max:50'],
            'billing_address'  => ['nullable', 'string'],
            'shipping_address' => ['nullable', 'string'],
            'status'           => ['nullable', 'string', 'in:active,inactive'],
        ];
    }
}
