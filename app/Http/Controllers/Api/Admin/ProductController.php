<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Product::query()
            ->with(['brand', 'categories', 'images'])
            ->withCount('variants');

        if ($request->has('published')) {
            $query->where('is_published', filter_var($request->query('published'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->has('brand_id')) {
            $query->where('brand_id', $request->query('brand_id'));
        }

        if ($request->has('search')) {
            $search = trim($request->query('search'));
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('short_description', 'like', "%{$search}%"));
        }

        $products = $query->latest('id')->paginate(min(max((int) ($request->query('per_page', 15)), 1), 100));

        return response()->json($products);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $data = $request->validated();
        $categoryIds = $data['category_ids'] ?? [];
        unset($data['category_ids']);

        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);

        if (!empty($data['is_published']) && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        $product = Product::create($data);

        if (!empty($categoryIds)) {
            $product->categories()->sync($categoryIds);
        }

        return response()->json([
            'message' => 'Produk berhasil ditambahkan.',
            'data' => $product->load('brand', 'categories'),
        ], 201);
    }

    public function show(Product $product): JsonResponse
    {
        return response()->json([
            'data' => $product->load([
                'brand',
                'categories',
                'images',
                'features',
                'variants.components.item',
            ]),
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $data = $request->validated();
        $categoryIds = $data['category_ids'] ?? null;
        unset($data['category_ids']);

        if (isset($data['name']) && !isset($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        if (isset($data['is_published']) && $data['is_published'] && !$product->published_at) {
            $data['published_at'] = now();
        }

        $product->update($data);

        if ($categoryIds !== null) {
            $product->categories()->sync($categoryIds);
        }

        return response()->json([
            'message' => 'Produk berhasil diperbarui.',
            'data' => $product->fresh()->load('brand', 'categories', 'images', 'features', 'variants'),
        ]);
    }

    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return response()->json([
            'message' => 'Produk berhasil dihapus.',
        ]);
    }
}
