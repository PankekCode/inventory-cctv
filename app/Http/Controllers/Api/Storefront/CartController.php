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

        $cart = $this->cartService->getCart($user, $guestToken);

        return response()->json([
            'data' => $cart->load(['items.productVariant.product', 'items.productVariant.components.item']),
        ]);
    }

    public function addItem(AddToCartRequest $request): JsonResponse
    {
        $user = auth('sanctum')->user();
        $cart = $this->cartService->addItem(
            $user,
            $request->validated('guest_token'),
            (int) $request->validated('product_variant_id'),
            (int) $request->validated('quantity')
        );

        return response()->json([
            'message' => 'Produk berhasil ditambahkan ke keranjang.',
            'data' => $cart->load(['items.productVariant.product']),
        ]);
    }

    public function updateItem(Request $request, int $itemId): JsonResponse
    {
        $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
            'guest_token' => ['nullable', 'string'],
        ]);

        $user = auth('sanctum')->user();
        $cart = $this->cartService->updateItem(
            $user,
            $request->input('guest_token'),
            $itemId,
            (int) $request->input('quantity')
        );

        return response()->json([
            'message' => 'Jumlah produk berhasil diperbarui.',
            'data' => $cart->load(['items.productVariant.product']),
        ]);
    }

    public function removeItem(Request $request, int $itemId): JsonResponse
    {
        $user = auth('sanctum')->user();
        $guestToken = $request->query('guest_token') ?: $request->input('guest_token');

        $cart = $this->cartService->removeItem($user, $guestToken, $itemId);

        return response()->json([
            'message' => 'Produk berhasil dihapus dari keranjang.',
            'data' => $cart->load(['items.productVariant.product']),
        ]);
    }
}
