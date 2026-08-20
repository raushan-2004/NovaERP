<?php

declare(strict_types=1);

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class CustomerActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => 'required|exists:customers,id',
            'type' => 'required|string|in:call,email,meeting,note,follow-up',
            'subject' => 'required|string|max:255',
            'description' => 'nullable|string',
            'activity_date' => 'required|date',
            'follow_up_date' => 'nullable|date|after_or_equal:activity_date',
            'status' => 'required|string|in:scheduled,completed',
        ];
    }
}
