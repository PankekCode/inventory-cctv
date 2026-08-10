<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductImageController extends Controller
{
    public function store(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'is_primary' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $path = $request->file('image')->store('products', 'public');
        $isPrimary = filter_var($request->input('is_primary', false), FILTER_VALIDATE_BOOLEAN);

        if ($isPrimary) {
            $product->images()->update(['is_primary' => false]);
        }

        $image = $product->images()->create([
            'path' => $path,
            'alt_text' => $request->input('alt_text'),
            'sort_order' => $request->input('sort_order', 0),
            'is_primary' => $isPrimary || $product->images()->count() === 0,
        ]);

        return response()->json([
            'message' => 'Gambar produk berhasil diunggah.',
            'data' => $image,
        ], 201);
    }

    public function update(Request $request, ProductImage $image): JsonResponse
    {
        $request->validate([
            'alt_text' => ['nullable', 'string', 'max:255'],
            'is_primary' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        if ($request->has('is_primary') && filter_var($request->input('is_primary'), FILTER_VALIDATE_BOOLEAN)) {
            ProductImage::where('product_id', $image->product_id)->update(['is_primary' => false]);
            $image->is_primary = true;
        }

        if ($request->has('alt_text')) {
            $image->alt_text = $request->input('alt_text');
        }

        if ($request->has('sort_order')) {
            $image->sort_order = (int) $request->input('sort_order');
        }

        $image->save();

        return response()->json([
            'message' => 'Detail gambar berhasil diperbarui.',
            'data' => $image,
        ]);
    }

    public function destroy(ProductImage $image): JsonResponse
    {
        if (Storage::disk('public')->exists($image->path)) {
            Storage::disk('public')->delete($image->path);
        }

        $productId = $image->product_id;
        $wasPrimary = $image->is_primary;

        $image->delete();

        if ($wasPrimary) {
            $next = ProductImage::where('product_id', $productId)->first();
            $next?->update(['is_primary' => true]);
        }

        return response()->json([
            'message' => 'Gambar produk berhasil dihapus.',
        ]);
    }
}
