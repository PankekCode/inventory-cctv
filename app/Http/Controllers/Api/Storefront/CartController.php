<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\AddToCartRequest;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(
        protected CartService $cartService
    ) {}

    public function show(Request $request): JsonResponse
    {
        $user = auth('sanctum')->user();
        $guestToken = $request->query('guest_token');

        $cart = $this->cartService->getOrCreate($user, $guestToken);

        return response()->json([
            'data' => $cart->load(['items.variant.product', 'items.variant.components.item']),
        ]);
    }

    public function addItem(AddToCartRequest $request): JsonResponse
    {
        $user = auth('sanctum')->user();
        $guestToken = $request->validated('guest_token');

        $cart = $this->cartService->getOrCreate($user, $guestToken);
        $updatedCart = $this->cartService->addItem(
            $cart,
            (int) $request->validated('product_variant_id'),
            (int) $request->validated('quantity')
        );

        return response()->json([
            'message' => 'Produk berhasil ditambahkan ke keranjang.',
            'data' => $updatedCart,
        ]);
    }

    public function updateItem(Request $request, int $itemId): JsonResponse
    {
        $request->validate([
            'quantity' => ['required', 'integer', 'min:0'],
            'guest_token' => ['nullable', 'string'],
        ]);

        $user = auth('sanctum')->user();
        $guestToken = $request->input('guest_token');

        $cart = $this->cartService->getOrCreate($user, $guestToken);
        $cartItem = $this->findCartItem($cart, $itemId);
        $updatedCart = $this->cartService->updateItem($cart, $cartItem->product_variant_id, (int) $request->input('quantity'));

        return response()->json([
            'message' => 'Jumlah produk berhasil diperbarui.',
            'data' => $updatedCart,
        ]);
    }

    public function removeItem(Request $request, int $itemId): JsonResponse
    {
        $user = auth('sanctum')->user();
        $guestToken = $request->query('guest_token') ?: $request->input('guest_token');

        $cart = $this->cartService->getOrCreate($user, $guestToken);
        $cartItem = $this->findCartItem($cart, $itemId);
        $updatedCart = $this->cartService->updateItem($cart, $cartItem->product_variant_id, 0);

        return response()->json([
            'message' => 'Produk berhasil dihapus dari keranjang.',
            'data' => $updatedCart,
        ]);
    }

    private function findCartItem(\App\Models\Cart $cart, int $itemId): \App\Models\CartItem
    {
        $item = $cart->items()->where('id', $itemId)->first();

        if ($item) {
            return $item;
        }

        if (\App\Models\CartItem::where('id', $itemId)->exists()) {
            abort(404, 'Item tidak ditemukan di keranjang.');
        }

        return $cart->items()->where('product_variant_id', $itemId)->firstOrFail();
    }
}
