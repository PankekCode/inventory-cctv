<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Category;
use App\Models\InventoryReservation;
use App\Models\Item;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class P0FixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_out_respects_available_stock_including_reserved(): void
    {
        $admin = User::create([
            'name' => 'Admin Staff',
            'email' => 'admin@example.com',
            'password' => bcrypt('secret123'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $category = Category::create(['code' => 'CCTV', 'name' => 'CCTV']);
        $supplier = Supplier::create(['code' => 'EZ', 'name' => 'EZVIZ']);

        $item = Item::create([
            'category_id' => $category->id,
            'supplier_id' => $supplier->id,
            'code' => 'EZ-100',
            'name' => 'Test Item',
            'purchase_price' => 100000,
            'stock' => 10,
            'stock_reserved' => 8,
            'unit' => 'Pcs',
        ]);

        // Available stock is 10 - 8 = 2. Manual stock out of 5 must fail with 422
        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/stock-out', [
                'item_id' => $item->id,
                'quantity' => 5,
                'movement_date' => now()->toDateString(),
                'note' => 'Excessive stock out test',
            ]);

        $response->assertStatus(422);

        // Stock out of 2 should succeed
        $successResponse = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/stock-out', [
                'item_id' => $item->id,
                'quantity' => 2,
                'movement_date' => now()->toDateString(),
                'note' => 'Valid stock out test',
            ]);

        $successResponse->assertStatus(200);
        $this->assertEquals(8, $item->fresh()->stock);
    }

    public function test_cart_item_update_is_isolated_to_own_cart(): void
    {
        $product = Product::create([
            'name' => 'Product 1',
            'slug' => 'product-1',
            'is_published' => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-001',
            'name' => 'Variant 1',
            'price' => 100000,
            'is_active' => true,
        ]);

        $guestTokenA = (string) Str::uuid();
        $guestTokenB = (string) Str::uuid();

        // Add same variant to Cart A and Cart B
        $this->postJson('/api/storefront/cart/items', [
            'guest_token' => $guestTokenA,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ]);

        $this->postJson('/api/storefront/cart/items', [
            'guest_token' => $guestTokenB,
            'product_variant_id' => $variant->id,
            'quantity' => 2,
        ]);

        $cartA = Cart::where('guest_token', $guestTokenA)->first();
        $cartAItem = $cartA->items->first();

        // Cart B user tries to update item using Cart A's item ID -> should fail 404
        $updateResponse = $this->putJson('/api/storefront/cart/items/' . $cartAItem->id, [
            'guest_token' => $guestTokenB,
            'quantity' => 5,
        ]);

        $updateResponse->assertStatus(404);
    }

    public function test_non_admin_users_and_guests_are_rejected_from_admin_and_import_routes(): void
    {
        $customer = User::create([
            'name' => 'Regular Customer',
            'email' => 'customer@example.com',
            'password' => bcrypt('secret123'),
            'role' => 'customer',
            'is_active' => true,
        ]);

        // Unauthenticated guest -> 401
        $this->getJson('/api/categories')->assertStatus(401);
        $this->getJson('/api/suppliers')->assertStatus(401);
        $this->getJson('/api/items')->assertStatus(401);
        $this->getJson('/api/dashboard')->assertStatus(401);
        $this->postJson('/api/import/supplier')->assertStatus(401);

        // Authenticated customer (non-admin) -> 403
        $this->actingAs($customer, 'sanctum')->getJson('/api/categories')->assertStatus(403);
        $this->actingAs($customer, 'sanctum')->getJson('/api/suppliers')->assertStatus(403);
        $this->actingAs($customer, 'sanctum')->getJson('/api/items')->assertStatus(403);
        $this->actingAs($customer, 'sanctum')->getJson('/api/dashboard')->assertStatus(403);
        $this->actingAs($customer, 'sanctum')->postJson('/api/stock-in', [])->assertStatus(403);
        $this->actingAs($customer, 'sanctum')->postJson('/api/import/item', [])->assertStatus(403);
    }

    public function test_admin_user_can_access_all_admin_endpoints(): void
    {
        $admin = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@hablun.com',
            'password' => bcrypt('secret123'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'sanctum')->getJson('/api/categories')->assertStatus(200);
        $this->actingAs($admin, 'sanctum')->getJson('/api/suppliers')->assertStatus(200);
        $this->actingAs($admin, 'sanctum')->getJson('/api/items')->assertStatus(200);
        $this->actingAs($admin, 'sanctum')->getJson('/api/dashboard')->assertStatus(200);
    }

    public function test_cancelling_paid_order_restores_inventory_stock(): void
    {
        $supplier = Supplier::create(['code' => 'EZ', 'name' => 'EZVIZ']);
        $category = Category::create(['code' => 'CCTV', 'name' => 'CCTV']);

        $item = Item::create([
            'supplier_id' => $supplier->id,
            'category_id' => $category->id,
            'code' => 'EZ-200',
            'name' => 'Item EZVIZ',
            'purchase_price' => 200000,
            'stock' => 10,
            'stock_reserved' => 0,
            'unit' => 'Pcs',
        ]);

        $order = Order::create([
            'public_id' => (string) Str::uuid(),
            'unique_order_code' => 'ORD-TEST-001',
            'customer_name' => 'Buyer',
            'installation_address' => 'Jl. Test No. 1',
            'payment_method' => 'qris',
            'status' => 'order_received',
            'payment_status' => 'paid',
            'subtotal' => 250000,
            'grand_total' => 250000,
        ]);

        // Create committed reservation (simulating completed payment)
        $item->decrement('stock', 2);
        InventoryReservation::create([
            'order_id' => $order->id,
            'item_id' => $item->id,
            'quantity' => 2,
            'status' => 'committed',
            'committed_at' => now(),
        ]);

        $this->assertEquals(8, $item->fresh()->stock);

        // Now cancel the order via OrderService
        /** @var OrderService $orderService */
        $orderService = app(OrderService::class);
        $orderService->transition($order, 'cancelled', 'Cancelled by admin');

        $this->assertEquals(10, $item->fresh()->stock);
        $this->assertDatabaseHas('stock_movements', [
            'item_id' => $item->id,
            'type' => 'IN',
            'quantity' => 2,
            'reference' => 'ORD-TEST-001',
        ]);
    }
}
