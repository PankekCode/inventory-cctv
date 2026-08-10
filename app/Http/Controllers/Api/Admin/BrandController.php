<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBrandRequest;
use App\Http\Requests\Admin\UpdateBrandRequest;
use App\Models\Brand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BrandController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Brand::query()->withCount('products');

        if ($request->has('active')) {
            $query->where('is_active', filter_var($request->query('active'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->has('search')) {
            $query->where('name', 'like', '%' . trim($request->query('search')) . '%');
        }

        $brands = $query->orderBy('sort_order')->orderBy('name')->get();

        return response()->json([
            'data' => $brands,
        ]);
    }

    public function store(StoreBrandRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);

        $brand = Brand::create($data);

        return response()->json([
            'message' => 'Merek berhasil ditambahkan.',
            'data' => $brand,
        ], 201);
    }

    public function show(Brand $brand): JsonResponse
    {
        return response()->json([
            'data' => $brand->load('products'),
        ]);
    }

    public function update(UpdateBrandRequest $request, Brand $brand): JsonResponse
    {
        $data = $request->validated();

        if (isset($data['name']) && !isset($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $brand->update($data);

        return response()->json([
            'message' => 'Merek berhasil diperbarui.',
            'data' => $brand->fresh(),
        ]);
    }

    public function destroy(Brand $brand): JsonResponse
    {
        if ($brand->products()->exists()) {
            return response()->json([
                'message' => 'Tidak dapat menghapus merek yang masih memiliki produk terkait.',
            ], 422);
        }

        $brand->delete();

        return response()->json([
            'message' => 'Merek berhasil dihapus.',
        ]);
    }
}
