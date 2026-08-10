<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['nullable', 'exists:products,id'],
            'sku' => ['required', 'string', 'max:100', 'unique:product_variants,sku'],
            'name' => ['required', 'string', 'max:255'],
            'variant_type' => ['nullable', 'string', 'max:40'],
            'camera_count' => ['nullable', 'integer', 'min:0'],
            'price' => ['required', 'numeric', 'min:0'], // Personal price
            'installation_included' => ['nullable', 'boolean'],
            'is_stock_managed' => ['nullable', 'boolean'],
            'warranty_months' => ['nullable', 'integer', 'min:0'],
            'configuration' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
