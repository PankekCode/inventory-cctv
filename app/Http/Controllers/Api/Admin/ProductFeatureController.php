<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductFeature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductFeatureController extends Controller
{
    public function store(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $feature = $product->features()->create([
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'icon' => $request->input('icon'),
            'sort_order' => $request->input('sort_order', 0),
        ]);

        return response()->json([
            'message' => 'Fitur produk berhasil ditambahkan.',
            'data' => $feature,
        ], 201);
    }

    public function update(Request $request, ProductFeature $feature): JsonResponse
    {
        $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:100'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $feature->update($request->only(['title', 'description', 'icon', 'sort_order']));

        return response()->json([
            'message' => 'Fitur produk berhasil diperbarui.',
            'data' => $feature->fresh(),
        ]);
    }

    public function destroy(ProductFeature $feature): JsonResponse
    {
        $feature->delete();

        return response()->json([
            'message' => 'Fitur produk berhasil dihapus.',
        ]);
    }
}
