<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Support\Facades\Storage;

class ProductImageController extends Controller
{
    public function store(Request $request, Product $product)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
            'alt_text' => 'nullable|string|max:255',
        ]);

        // Simpan file
        $path = $request->file('image')->store('products', 'public');

        // Simpan ke database
        ProductImage::create([
            'product_id' => $product->id,
            'path' => $path,
            'alt_text' => $request->alt_text,
            'sort_order' => 0,
            'is_primary' => false,
        ]);

        return response()->json([
            'message' => 'Image uploaded successfully'
        ]);
    }
}
