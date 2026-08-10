<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductVariantRequest;
use App\Http\Requests\Admin\UpdateProductVariantRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductVariantController extends Controller
{
    public function index(Request $request, ?Product $product = null): JsonResponse
    {
        $query = ProductVariant::query()->with(['product', 'components.item']);

        if ($product && $product->exists) {
            $query->where('product_id', $product->id);
        } elseif ($request->has('product_id')) {
            $query->where('product_id', $request->query('product_id'));
        }

        if ($request->has('active')) {
            $query->where('is_active', filter_var($request->query('active'), FILTER_VALIDATE_BOOLEAN));
        }

        $variants = $query->orderBy('sort_order')->orderBy('id')->get();

        return response()->json([
            'data' => $variants,
        ]);
    }

    public function store(StoreProductVariantRequest $request, ?Product $product = null): JsonResponse
    {
        $data = $request->validated();

        if ($product && $product->exists) {
            $data['product_id'] = $product->id;
        }

        if (empty($data['product_id'])) {
            return response()->json([
                'message' => 'Product ID wajib ditentukan.',
            ], 422);
        }

        $variant = ProductVariant::create($data);

        return response()->json([
            'message' => 'Varian produk berhasil ditambahkan.',
            'data' => $variant->load('components.item'),
        ], 201);
    }

    public function show(ProductVariant $variant): JsonResponse
    {
        return response()->json([
            'data' => $variant->load(['product', 'components.item']),
        ]);
    }

    public function update(UpdateProductVariantRequest $request, ProductVariant $variant): JsonResponse
    {
        $data = $request->validated();

        $variant->update($data);

        return response()->json([
            'message' => 'Varian produk berhasil diperbarui.',
            'data' => $variant->fresh()->load('components.item'),
        ]);
    }

    public function destroy(ProductVariant $variant): JsonResponse
    {
        $variant->delete();

        return response()->json([
            'message' => 'Varian produk berhasil dihapus.',
        ]);
    }
}
