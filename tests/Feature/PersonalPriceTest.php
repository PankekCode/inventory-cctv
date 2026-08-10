<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Item;
use App\Models\PhoneVerification;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Services\CheckoutService;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class PersonalPriceTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_and_product_detail_returns_personal_price(): void
    {
        $product = Product::create([
            'name' => 'CCTV EZVIZ H6C',
            'slug' => 'cctv-ezviz-h6c',
            'product_type' => 'wireless',
            'is_published' => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-EZ-H6C-PERSONAL',
            'name' => 'EZVIZ H6C 2MP',
            'price' => 384000.00, // Personal price
            'is_active' => true,
        ]);

        // 1 & 2. Catalog index returns Personal price
        $catalogResponse = $this->getJson('/api/storefront/products');
        $catalogResponse->assertStatus(200)
            ->assertJsonPath('data.0.variants.0.price', '384000.00');

        // 3. Product detail returns Personal price
        $detailResponse = $this->getJson('/api/storefront/products/' . $product->slug);
        $detailResponse->assertStatus(200)
            ->assertJsonPath('data.variants.0.price', '384000.00');
    }

    public function test_cart_displays_personal_price(): void
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
            'name' => 'IMOU Ranger 2 3MP',
            'price' => 450000.00, // Personal price
            'is_active' => true,
        ]);

        $guestToken = (string) Str::uuid();

        $this->postJson('/api/storefront/cart/items', [
            'guest_token' => $guestToken,
            'product_variant_id' => $variant->id,
            'quantity' => 2,
        ]);

        // 4. Cart returns Personal price
        $cartResponse = $this->getJson('/api/storefront/cart?guest_token=' . $guestToken);
        $cartResponse->assertStatus(200)
            ->assertJsonPath('data.items.0.variant.price', '450000.00');
    }

    public function test_checkout_calculates_totals_using_personal_price_and_ignores_client_tampering(): void
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
            'sku' => 'EZ-001',
            'name' => 'Unit Saja',
            'price' => 384000.00, // Personal selling price
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

        // Client attempts price tampering by passing fake price/subtotal/grand_total in request
        $response = $this->postJson('/api/storefront/checkout', [
            'customer_name' => 'Guest Buyer',
            'phone' => '08123456789',
            'verification_id' => $verification->public_id,
            'installation_address' => 'Jl. Merdeka No. 10',
            'payment_method' => 'qris',
            'price' => 1.00, // Tampered client payload
            'subtotal' => 1.00, // Tampered client payload
            'grand_total' => 1.00, // Tampered client payload
            'items' => [
                [
                    'product_variant_id' => $variant->id,
                    'quantity' => 2,
                    'unit_price' => 1.00, // Tampered client line price
                ],
            ],
        ]);

        // 5, 6, 7 & 8. Backend calculates order item unit_price=384000 and grand_total=768000
        $response->assertStatus(201)
            ->assertJsonPath('data.items.0.unit_price', '384000.00')
            ->assertJsonPath('data.items.0.line_total', '768000.00')
            ->assertJsonPath('data.subtotal', '768000.00')
            ->assertJsonPath('data.grand_total', '768000.00');

        $this->assertDatabaseHas('orders', [
            'subtotal' => 768000.00,
            'grand_total' => 768000.00,
        ]);

        $this->assertDatabaseHas('order_items', [
            'unit_price' => 384000.00,
            'line_total' => 768000.00,
        ]);
    }

    public function test_quantity_increase_does_not_trigger_tiered_price_discount(): void
    {
        $product = Product::create([
            'name' => 'CCTV Dahua Cooper',
            'slug' => 'cctv-dahua-cooper',
            'product_type' => 'wired',
            'is_published' => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'DH-COOPER',
            'name' => 'Dahua Cooper 2MP',
            'price' => 250000.00, // Personal price
            'is_active' => true,
            'is_stock_managed' => false,
        ]);

        // 9 & 10. priceForQuantity returns variant price even with high quantity
        $this->assertEquals('250000.00', $variant->priceForQuantity(1));
        $this->assertEquals('250000.00', $variant->priceForQuantity(10));
        $this->assertEquals('250000.00', $variant->priceForQuantity(100));

        /** @var CheckoutService $checkoutService */
        $checkoutService = app(CheckoutService::class);
        $lines = invokeprivate($checkoutService, 'resolveLines', [[
            ['product_variant_id' => $variant->id, 'quantity' => 10],
        ]]);

        $this->assertEquals(250000.00, $lines[0]['unit_price']);
        $this->assertEquals(2500000.00, $lines[0]['line_total']);
    }
}

function invokeprivate($object, $methodName, array $parameters = [])
{
    $reflection = new \ReflectionClass(get_class($object));
    $method = $reflection->getMethod($methodName);
    $method->setAccessible(true);
    return $method->invokeArgs($object, $parameters);
}
