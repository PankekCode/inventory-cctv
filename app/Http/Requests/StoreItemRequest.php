<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => [
                'required',
                'exists:categories,id',
            ],

            'supplier_id' => [
                'required',
                'exists:suppliers,id',
            ],

            'code' => [
                'required',
                'string',
                'max:100',
                'unique:items,code'
            ],

            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'model' => [
                'nullable',
                'string',
                'max:100',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'purchase_price' => [
                'required',
                'numeric',
                'min:0',
            ],

            'minimum_stock' => [
                'required',
                'integer',
                'min:0',
            ],

            'unit' => [
                'required',
                'string',
                'max:30',
            ],
        ];
    }
}