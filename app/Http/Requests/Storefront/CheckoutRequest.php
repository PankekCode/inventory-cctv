<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isGuest = !auth('sanctum')->check();

        return [
            'customer_name' => ['required', 'string', 'max:255'],
            'phone' => [$isGuest ? 'required' : 'nullable', 'string', 'max:25'],
            'verification_id' => [$isGuest ? 'required' : 'nullable', 'string'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'installation_address' => ['required', 'string'],
            'installation_city' => ['nullable', 'string', 'max:100'],
            'installation_date' => ['nullable', 'date'],
            'installation_time_slot' => ['nullable', 'string', 'max:100'],
            'customer_note' => ['nullable', 'string', 'max:1000'],
            'payment_method' => ['required', 'string', 'in:qris,bank_transfer,gopay,ovo,shopeepay'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}
