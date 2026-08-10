<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreComponentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $variant = $this->route('variant');
        $variantId = is_object($variant) ? $variant->id : $variant;

        return [
            'item_id' => [
                'required',
                'exists:items,id',
                Rule::unique('product_variant_components', 'item_id')->where('product_variant_id', $variantId),
            ],
            'quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}
