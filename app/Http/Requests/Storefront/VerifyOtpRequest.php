<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;

class VerifyOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'verification_id' => ['required', 'string'],
            'code' => ['required', 'string', 'max:10'],
            'purpose' => ['required', 'string', 'in:guest_checkout,login,registration'],
        ];
    }
}
