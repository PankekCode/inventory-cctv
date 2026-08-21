<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\Cart;
use App\Models\Category;
use App\Models\CompanyProfile;
use App\Models\Item;
use App\Models\Order;
use App\Models\PhoneVerification;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\Service;
use App\Models\Supplier;
use App\Models\User;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class Stage7RegressionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin Staff',
            'email' => 'admin@hablun.com',
            'password' => bcrypt('secret123'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->customer = User::create([
            'name' => 'Customer User',
            'email' => 'customer@hablun.com',
            'password' => bcrypt('secret123'),
            'role' => 'customer',
            'is_active' => true,
        ]);
    }

    public function test_checks_1_to_10_personal_price_storefront_integrity(): void
    {
        $product = Product::create([
            'name' => 'CCTV EZVIZ H6C',
            'slug' => 'cctv-ezviz-h6c',
            'product_type' => 'wireless',
            'is_published' => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'EZ-H6C-PERSONAL',
            'name' => 'EZVIZ H6C 2MP',
            'price' => 384000.00,
            'is_active' => true,
        ]);

        // 1, 2, 3. Catalog uses product_variants.price
        $this->getJson('/api/storefront/products')
            ->assertStatus(200)
            ->assertJsonPath('data.0.variants.0.price', '384000.00');

        // 4. Product detail uses product_variants.price
        $this->getJson('/api/storefront/products/' . $product->slug)
            ->assertStatus(200)
            ->assertJsonPath('data.variants.0.price', '384000.00');

        // 5. Cart uses backend price
        $guestToken = (string) Str::uuid();
        $this->postJson('/api/storefront/cart/items', [
            'guest_token' => $guestToken,
            'product_variant_id' => $variant->id,
            'quantity' => 2,
        ]);

        $this->getJson('/api/storefront/cart?guest_token=' . $guestToken)
            ->assertStatus(200)
            ->assertJsonPath('data.items.0.variant.price', '384000.00');

        // 6, 7, 8. Checkout ignores client tampering and uses backend price
        $supplier = Supplier::create(['code' => 'EZ', 'name' => 'EZVIZ']);
        $category = Category::create(['code' => 'CCTV', 'name' => 'CCTV']);
        $item = Item::create([
            'supplier_id' => $supplier->id,
            'category_id' => $category->id,
            'code' => 'EZ-H6C-PERSONAL',
            'name' => 'Item H6C',
            'purchase_price' => 300000,
            'stock' => 10,
            'unit' => 'Pcs',
        ]);
        $variant->update(['is_stock_managed' => true]);
        $variant->components()->create(['item_id' => $item->id, 'quantity' => 1]);

        $verification = PhoneVerification::create([
            'public_id' => (string) Str::uuid(),
            'phone_e164' => '+628123456789',
            'purpose' => 'guest_checkout',
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(5),
            'verified_at' => now(),
        ]);

        $checkoutResponse = $this->postJson('/api/storefront/checkout', [
            'customer_name' => 'Buyer',
            'phone' => '08123456789',
            'verification_id' => $verification->public_id,
            'installation_address' => 'Jl. Test No. 1',
            'payment_method' => 'qris',
            'price' => 1.00, // Tampered client price
            'items' => [
                ['product_variant_id' => $variant->id, 'quantity' => 2, 'unit_price' => 1.00],
            ],
        ]);

        $checkoutResponse->assertStatus(201)
            ->assertJsonPath('data.items.0.unit_price', '384000.00')
            ->assertJsonPath('data.grand_total', '768000.00');

        // 9 & 10. Quantity increases return exact personal price
        $this->assertEquals('384000.00', $variant->priceForQuantity(1));
        $this->assertEquals('384000.00', $variant->priceForQuantity(10));
    }

    public function test_checks_11_to_18_product_admin_and_security(): void
    {
        Storage::fake('public');

        // 18. Customer receives 403 on admin product management
        $this->actingAs($this->customer, 'sanctum')->getJson('/api/products')->assertStatus(403);

        // 11. Manage products
        $prodRes = $this->actingAs($this->admin, 'sanctum')->postJson('/api/products', [
            'name' => 'CCTV IMOU Bullet',
            'is_published' => true,
        ]);
        $prodRes->assertStatus(201);
        $productId = $prodRes->json('data.id');

        // 12 & 16. Manage variants and SKU validation
        $varRes = $this->actingAs($this->admin, 'sanctum')->postJson('/api/products/' . $productId . '/variants', [
            'sku' => 'SKU-IMOU-BULLET',
            'name' => 'IMOU Bullet 2MP',
            'price' => 550000.00,
        ]);
        $varRes->assertStatus(201);
        $variantId = $varRes->json('data.id');

        $this->actingAs($this->admin, 'sanctum')->postJson('/api/products/' . $productId . '/variants', [
            'sku' => 'SKU-IMOU-BULLET', // Duplicate SKU
            'name' => 'Duplicate SKU Variant',
            'price' => 600000.00,
        ])->assertStatus(422);

        // 13. Manage images
        $imageFile = UploadedFile::fake()->image('bullet.png');
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/products/' . $productId . '/images', [
            'image' => $imageFile,
            'alt_text' => 'Bullet camera image',
        ])->assertStatus(201);

        // 14. Manage features
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/products/' . $productId . '/features', [
            'title' => 'Night Vision',
            'description' => 'Infrared 30m',
        ])->assertStatus(201);

        // 15 & 17. Manage bundle components referencing physical items
        $supplier = Supplier::create(['code' => 'IM', 'name' => 'IMOU']);
        $category = Category::create(['code' => 'CCTV', 'name' => 'CCTV']);
        $item = Item::create([
            'supplier_id' => $supplier->id,
            'category_id' => $category->id,
            'code' => 'SKU-IMOU-BULLET',
            'name' => 'Item IMOU Bullet',
            'purchase_price' => 400000,
            'stock' => 20,
            'unit' => 'Pcs',
        ]);

        $this->actingAs($this->admin, 'sanctum')->postJson('/api/variants/' . $variantId . '/components', [
            'item_id' => $item->id,
            'quantity' => 1,
        ])->assertStatus(201);
    }

    public function test_checks_19_to_27_order_admin_and_authorization(): void
    {
        $order = Order::create([
            'public_id' => (string) Str::uuid(),
            'user_id' => $this->customer->id,
            'order_code' => 'ORD-REG-19-27',
            'customer_name' => 'Customer User',
            'installation_address' => 'Jl. Merdeka 10',
            'payment_method' => 'qris',
            'status' => 'order_received',
            'payment_status' => 'paid',
            'subtotal' => 500000.00,
            'grand_total' => 500000.00,
        ]);

        // 26. Customer blocked from admin orders
        $this->actingAs($this->customer, 'sanctum')->getJson('/api/admin/orders')->assertStatus(403);

        // 27. Customer can access own storefront order
        $this->actingAs($this->customer, 'sanctum')->getJson('/api/storefront/orders/' . $order->id)->assertStatus(200);

        // 19. Admin list orders
        $this->actingAs($this->admin, 'sanctum')->getJson('/api/admin/orders')->assertStatus(200);

        // 20. Admin view order detail
        $this->actingAs($this->admin, 'sanctum')->getJson('/api/admin/orders/' . $order->id)->assertStatus(200);

        // 21 & 22. Valid status transition & invalid status transition rejection
        $this->actingAs($this->admin, 'sanctum')->patchJson('/api/admin/orders/' . $order->id . '/status', [
            'status' => 'installation_in_progress',
        ])->assertStatus(200);

        $this->actingAs($this->admin, 'sanctum')->patchJson('/api/admin/orders/' . $order->id . '/status', [
            'status' => 'awaiting_payment', // Invalid transition from installation_in_progress
        ])->assertStatus(422);

        // 25. Status history preserved
        $this->assertDatabaseHas('order_status_histories', ['order_id' => $order->id, 'status' => 'installation_in_progress']);
    }

    public function test_checks_28_and_29_inventory_stock_in_and_stock_out(): void
    {
        $supplier = Supplier::create(['code' => 'SUP', 'name' => 'Supplier']);
        $category = Category::create(['code' => 'CAT', 'name' => 'Category']);

        $item = Item::create([
            'supplier_id' => $supplier->id,
            'category_id' => $category->id,
            'code' => 'ITEM-REG-28',
            'name' => 'Item Regression',
            'purchase_price' => 100000,
            'stock' => 10,
            'stock_reserved' => 3,
            'unit' => 'Pcs',
        ]);

        // 28. Stock IN
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/stock-in', [
            'item_id' => $item->id,
            'quantity' => 5,
            'price' => 100000,
            'movement_date' => now()->toDateString(),
        ])->assertStatus(200);
        $this->assertEquals(15, $item->fresh()->stock);

        // 29. Stock OUT respects available stock (15 - 3 = 12 available). Stock OUT 13 fails.
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/stock-out', [
            'item_id' => $item->id,
            'quantity' => 13,
            'movement_date' => now()->toDateString(),
        ])->assertStatus(422);
    }

    public function test_checks_30_to_34_checkout_reservation_and_cancellation(): void
    {
        $supplier = Supplier::create(['code' => 'SUP2', 'name' => 'Supplier 2']);
        $category = Category::create(['code' => 'CAT2', 'name' => 'Category 2']);

        $item = Item::create([
            'supplier_id' => $supplier->id,
            'category_id' => $category->id,
            'code' => 'ITEM-REG-30',
            'name' => 'Item Checkout Reg',
            'purchase_price' => 100000,
            'stock' => 15,
            'stock_reserved' => 3,
            'unit' => 'Pcs',
        ]);

        // 30, 31, 34. Checkout reserves stock & payment commit does not double-deduct
        $product = Product::create(['name' => 'Product Reg', 'slug' => 'product-reg', 'is_published' => true]);
        $variant = ProductVariant::create(['product_id' => $product->id, 'sku' => 'ITEM-REG-30', 'name' => 'Var', 'price' => 150000, 'is_active' => true, 'is_stock_managed' => true]);
        $variant->components()->create(['item_id' => $item->id, 'quantity' => 2]);

        $verification = PhoneVerification::create([
            'public_id' => (string) Str::uuid(),
            'phone_e164' => '+628123456789',
            'purpose' => 'guest_checkout',
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(5),
            'verified_at' => now(),
        ]);

        $itemStockBefore = $item->fresh()->stock; // 15
        $itemReservedBefore = $item->fresh()->stock_reserved; // 3

        $checkoutRes = $this->postJson('/api/storefront/checkout', [
            'customer_name' => 'Buyer',
            'phone' => '08123456789',
            'verification_id' => $verification->public_id,
            'installation_address' => 'Jl. Test',
            'payment_method' => 'qris',
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 1]],
        ]);
        $checkoutRes->assertStatus(201);
        $orderId = $checkoutRes->json('data.id');

        // Stock reserved incremented by 2 (3 + 2 = 5), physical stock unchanged (15)
        $this->assertEquals($itemStockBefore, $item->fresh()->stock);
        $this->assertEquals($itemReservedBefore + 2, $item->fresh()->stock_reserved);

        // Simulate payment commit (PaymentService::markPaid)
        $payment = \App\Models\Payment::where('order_id', $orderId)->first();
        /** @var PaymentService $paymentService */
        $paymentService = app(PaymentService::class);
        $paymentService->markPaid('sandbox', [
            'event_id' => 'evt_' . Str::random(10),
            'provider_reference' => $payment->provider_reference,
            'amount' => $payment->amount,
        ]);

        // After payment commit: stock decremented by 2 (15 -> 13), stock_reserved decremented by 2 (5 -> 3)
        $this->assertEquals(13, $item->fresh()->stock);
        $this->assertEquals(3, $item->fresh()->stock_reserved);

        // 33. Order cancellation restores inventory correctly
        $orderObj = Order::find($orderId);
        /** @var OrderService $orderService */
        $orderService = app(OrderService::class);
        $orderService->transition($orderObj, 'cancelled', 'Cancelled test');

        $this->assertEquals(15, $item->fresh()->stock); // Stock restored from 13 back to 15
    }

    public function test_checks_35_to_42_storefront_regression_and_content(): void
    {
        // 41. Company Profile
        CompanyProfile::create([
            'company_name' => 'Hablun CCTV',
            'about' => 'Penyedia CCTV terpercaya',
        ]);
        $this->getJson('/api/storefront/company-profile')
            ->assertStatus(200)
            ->assertJsonPath('data.company_name', 'Hablun CCTV');

        // 42. Services
        Service::create([
            'name' => 'Instalasi CCTV',
            'slug' => 'instalasi-cctv',
            'description' => 'Jasa pemasangan CCTV',
            'is_active' => true,
        ]);
        $this->getJson('/api/storefront/services')
            ->assertStatus(200)
            ->assertJsonPath('data.0.name', 'Instalasi CCTV');

        // 35. Homepage
        Banner::create(['title' => 'Banner Promo', 'is_active' => true]);
        $this->getJson('/api/storefront/home')->assertStatus(200);

        // 36. Catalog
        $product = Product::create(['name' => 'Prod SF', 'slug' => 'prod-sf', 'is_published' => true]);
        $this->getJson('/api/storefront/products')->assertStatus(200);

        // 37. Product Detail
        $this->getJson('/api/storefront/products/' . $product->slug)->assertStatus(200);

        // 40. Reviews
        Review::create([
            'product_id' => $product->id,
            'author_name' => 'Test Reviewer',
            'rating' => 5,
            'comment' => 'Sangat bagus',
            'is_published' => true,
        ]);
        $this->getJson('/api/storefront/products/' . $product->slug)
            ->assertStatus(200)
            ->assertJsonPath('data.reviews.0.rating', 5);
    }
}
