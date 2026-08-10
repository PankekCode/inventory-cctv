<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreComponentRequest;
use App\Models\ProductVariant;
use App\Models\ProductVariantComponent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductVariantComponentController extends Controller
{
    public function index(ProductVariant $variant): JsonResponse
    {
        $components = $variant->components()->with('item.category', 'item.supplier')->get();

        return response()->json([
            'data' => $components,
        ]);
    }

    public function store(StoreComponentRequest $request, ProductVariant $variant): JsonResponse
    {
        $data = $request->validated();

        $component = $variant->components()->create([
            'item_id' => $data['item_id'],
            'quantity' => $data['quantity'],
        ]);

        return response()->json([
            'message' => 'Komponen inventori berhasil dipetakan ke varian.',
            'data' => $component->load('item'),
        ], 201);
    }

    public function update(Request $request, ProductVariantComponent $component): JsonResponse
    {
        $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $component->update([
            'quantity' => (int) $request->input('quantity'),
        ]);

        return response()->json([
            'message' => 'Jumlah komponen berhasil diperbarui.',
            'data' => $component->fresh()->load('item'),
        ]);
    }

    public function destroy(ProductVariantComponent $component): JsonResponse
    {
        $component->delete();

        return response()->json([
            'message' => 'Komponen berhasil dihapus dari varian.',
        ]);
    }
}
