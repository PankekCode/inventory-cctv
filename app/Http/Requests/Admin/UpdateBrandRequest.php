<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $brandId = $this->route('brand')?->id ?? $this->route('brand');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:100', Rule::unique('brands', 'name')->ignore($brandId)],
            'slug' => ['sometimes', 'nullable', 'string', 'max:120', Rule::unique('brands', 'slug')->ignore($brandId)],
            'logo_path' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
