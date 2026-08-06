<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StorefrontCartTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_add_item_and_retrieve_cart(): void
    {
        $product = Product::create([
            'name' => 'CCTV EZVIZ H6C',
            'slug' => 'cctv-ezviz-h6c',
            'product_type' => 'wireless',
            'is_published' => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-EZ-H6C-1',
            'name' => 'Unit Saja',
            'price' => 450000,
            'is_active' => true,
        ]);

        $guestToken = (string) Str::uuid();

        $addResponse = $this->postJson('/api/storefront/cart/items', [
            'guest_token' => $guestToken,
            'product_variant_id' => $variant->id,
            'quantity' => 2,
        ]);

        $addResponse->assertStatus(200)
            ->assertJsonPath('message', 'Produk berhasil ditambahkan ke keranjang.');

        $getCartResponse = $this->getJson('/api/storefront/cart?guest_token=' . $guestToken);

        $getCartResponse->assertStatus(200)
            ->assertJsonPath('data.guest_token', $guestToken)
            ->assertJsonCount(1, 'data.items');
    }

    public function test_can_update_and_remove_cart_item(): void
    {
        $product = Product::create([
            'name' => 'CCTV IMOU Ranger 2',
            'slug' => 'cctv-imou-ranger-2',
            'product_type' => 'wireless',
            'is_published' => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-IMOU-R2',
            'name' => 'Unit Saja',
            'price' => 380000,
            'is_active' => true,
        ]);

        $guestToken = (string) Str::uuid();

        $this->postJson('/api/storefront/cart/items', [
            'guest_token' => $guestToken,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ]);

        $cartResponse = $this->getJson('/api/storefront/cart?guest_token=' . $guestToken);
        $cartItemId = $cartResponse->json('data.items.0.id');

        $updateResponse = $this->putJson('/api/storefront/cart/items/' . $cartItemId, [
            'guest_token' => $guestToken,
            'quantity' => 3,
        ]);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('data.items.0.quantity', 3);

        $deleteResponse = $this->deleteJson('/api/storefront/cart/items/' . $cartItemId . '?guest_token=' . $guestToken);

        $deleteResponse->assertStatus(200)
            ->assertJsonCount(0, 'data.items');
    }
}
