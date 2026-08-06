<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Item;
use App\Models\PhoneVerification;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class StorefrontCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_checkout_rejects_non_qris_payment(): void
    {
        $product = Product::create([
            'name' => 'CCTV H6C',
            'slug' => 'cctv-h6c',
            'product_type' => 'wireless',
            'is_published' => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-H6C-UNIT-1',
            'name' => 'Unit Saja',
            'price' => 450000,
            'is_active' => true,
            'is_stock_managed' => false,
        ]);

        $response = $this->postJson('/api/storefront/checkout', [
            'customer_name' => 'Guest Buyer',
            'phone' => '08123456789',
            'verification_id' => (string) Str::uuid(),
            'installation_address' => 'Jl. Merdeka No. 10',
            'payment_method' => 'bank_transfer',
            'items' => [
                ['product_variant_id' => $variant->id, 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['payment_method']);
    }

    public function test_guest_checkout_success_with_qris_and_otp(): void
    {
        $supplier = Supplier::create(['code' => 'EZ', 'name' => 'EZVIZ']);
        $category = Category::create(['code' => 'CCTV', 'name' => 'CCTV']);

        $item = Item::create([
            'supplier_id' => $supplier->id,
            'category_id' => $category->id,
            'code' => 'EZ-001',
            'name' => 'Item H6C',
            'purchase_price' => 300000,
            'stock' => 10,
            'unit' => 'Pcs',
        ]);

        $product = Product::create([
            'name' => 'CCTV H6C',
            'slug' => 'cctv-h6c',
            'product_type' => 'wireless',
            'is_published' => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-H6C-UNIT-2',
            'name' => 'Unit Saja',
            'price' => 450000,
            'is_active' => true,
            'is_stock_managed' => true,
        ]);

        $variant->components()->create([
            'item_id' => $item->id,
            'quantity' => 1,
        ]);

        $verification = PhoneVerification::create([
            'public_id' => (string) Str::uuid(),
            'phone_e164' => '+628123456789',
            'purpose' => 'guest_checkout',
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(5),
            'verified_at' => now(),
        ]);

        $response = $this->postJson('/api/storefront/checkout', [
            'customer_name' => 'Guest Buyer',
            'phone' => '08123456789',
            'verification_id' => $verification->public_id,
            'installation_address' => 'Jl. Merdeka No. 10',
            'payment_method' => 'qris',
            'items' => [
                ['product_variant_id' => $variant->id, 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.payment_method', 'qris')
            ->assertJsonPath('data.tax_amount', '0.00');

        $this->assertDatabaseHas('orders', [
            'customer_name' => 'Guest Buyer',
            'payment_method' => 'qris',
            'guest_phone_e164' => '+628123456789',
        ]);
    }
}
