<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $variantId = $this->route('variant')?->id ?? $this->route('variant');

        return [
            'sku' => ['sometimes', 'required', 'string', 'max:100', Rule::unique('product_variants', 'sku')->ignore($variantId)],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'variant_type' => ['sometimes', 'string', 'max:40'],
            'camera_count' => ['nullable', 'integer', 'min:0'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0'], // Personal price
            'installation_included' => ['sometimes', 'boolean'],
            'is_stock_managed' => ['sometimes', 'boolean'],
            'warranty_months' => ['nullable', 'integer', 'min:0'],
            'configuration' => ['nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
