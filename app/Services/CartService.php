<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CartService
{
    public function getOrCreate(?User $user, ?string $guestToken): Cart
    {
        if ($user) {
            $cart = Cart::query()
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->latest()
                ->first();

            if ($cart) {
                return $cart->load('items.variant.product');
            }
        } elseif ($guestToken) {
            $cart = Cart::query()
                ->where('guest_token', $guestToken)
                ->where('status', 'active')
                ->first();

            if ($cart) {
                return $cart->load('items.variant.product');
            }
        }

        return Cart::create([
            'public_id' => (string) Str::uuid(),
            'user_id' => $user?->id,
            'guest_token' => $user ? null : ($guestToken ?: (string) Str::uuid()),
            'status' => 'active',
        ])->load('items.variant.product');
    }

    public function addItem(Cart $cart, int $variantId, int $quantity): Cart
    {
        $variant = ProductVariant::query()
            ->whereKey($variantId)
            ->where('is_active', true)
            ->first();

        if (!$variant) {
            throw ValidationException::withMessages([
                'product_variant_id' => ['Varian produk tidak tersedia.'],
            ]);
        }

        $cart->items()->updateOrCreate(
            ['product_variant_id' => $variantId],
            ['quantity' => $quantity],
        );

        return $cart->fresh()->load('items.variant.product');
    }

    public function updateItem(Cart $cart, int $variantId, int $quantity): Cart
    {
        $item = $cart->items()->where('product_variant_id', $variantId)->firstOrFail();

        if ($quantity < 1) {
            $item->delete();
        } else {
            $item->update(['quantity' => $quantity]);
        }

        return $cart->fresh()->load('items.variant.product');
    }
}
