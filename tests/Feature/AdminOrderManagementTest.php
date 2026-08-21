<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Item;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminOrderManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'phone' => '08123456789',
            'role' => 'admin',
            'password' => bcrypt('password'),
        ]);

        $this->customer = User::create([
            'name' => 'Customer User',
            'email' => 'cust@example.com',
            'phone' => '08987654321',
            'role' => 'customer',
            'password' => bcrypt('password'),
        ]);
    }

    public function test_admin_can_list_orders_and_filter_by_status_and_search(): void
    {
        Order::create([
            'public_id' => (string) Str::uuid(),
            'order_code' => 'ORD-2026-0001',
            'customer_name' => 'Budi Customer',
            'customer_email' => 'budi@example.com',
            'installation_address' => 'Jl. Sudirman No. 1',
            'payment_method' => 'qris',
            'status' => 'order_received',
            'payment_status' => 'paid',
            'subtotal' => 1500000.00,
            'grand_total' => 1500000.00,
        ]);

        Order::create([
            'public_id' => (string) Str::uuid(),
            'order_code' => 'ORD-2026-0002',
            'customer_name' => 'Siti Aminah',
            'customer_email' => 'siti@example.com',
            'installation_address' => 'Jl. Thamrin No. 2',
            'payment_method' => 'qris',
            'status' => 'installation_in_progress',
            'payment_status' => 'paid',
            'subtotal' => 2000000.00,
            'grand_total' => 2000000.00,
        ]);

        // 1. List orders -> 200
        $response = $this->actingAs($this->admin, 'sanctum')->getJson('/api/admin/orders');
        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'current_page', 'total'])
            ->assertJsonCount(2, 'data');

        // 2. Filter by status -> only matching status returned
        $filteredResponse = $this->actingAs($this->admin, 'sanctum')->getJson('/api/admin/orders?status=order_received');
        $filteredResponse->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'order_received');

        // 3. Search query matches order_code or customer name
        $searchResponse = $this->actingAs($this->admin, 'sanctum')->getJson('/api/admin/orders?search=ORD-2026-0002');
        $searchResponse->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.customer_name', 'Siti Aminah');
    }

    public function test_admin_can_view_order_details_and_update_status(): void
    {
        $order = Order::create([
            'public_id' => (string) Str::uuid(),
            'order_code' => 'ORD-2026-0003',
            'customer_name' => 'Ahmad',
            'installation_address' => 'Jl. Gatot Subroto No. 1',
            'payment_method' => 'qris',
            'status' => 'order_received',
            'payment_status' => 'paid',
            'subtotal' => 800000.00,
            'grand_total' => 800000.00,
        ]);

        // 4. View details -> 200 with OrderResource structure
        $detailResponse = $this->actingAs($this->admin, 'sanctum')->getJson('/api/admin/orders/' . $order->id);
        $detailResponse->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'public_id',
                    'order_code',
                    'customer_name',
                    'status',
                    'payment_status',
                    'items',
                    'payments',
                    'status_history',
                ],
            ]);

        // 5. Valid status update -> 200 and logs to status history
        $updateResponse = $this->actingAs($this->admin, 'sanctum')
            ->patchJson('/api/admin/orders/' . $order->id . '/status', [
                'status' => 'installation_in_progress',
                'note' => 'Teknisi tiba di lokasi.',
            ]);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('data.status', 'installation_in_progress');

        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'status' => 'installation_in_progress',
            'note' => 'Teknisi tiba di lokasi.',
        ]);

        // 6. Invalid status transition -> 422
        $invalidResponse = $this->actingAs($this->admin, 'sanctum')
            ->patchJson('/api/admin/orders/' . $order->id . '/status', [
                'status' => 'awaiting_payment',
            ]);

        $invalidResponse->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_customer_order_access_remains_restricted_to_own_orders(): void
    {
        $otherCustomer = User::create([
            'name' => 'Other Customer',
            'email' => 'other@hablun.com',
            'password' => bcrypt('secret123'),
            'role' => 'customer',
            'is_active' => true,
        ]);

        $orderCustomer = Order::create([
            'public_id' => (string) Str::uuid(),
            'user_id' => $this->customer->id,
            'order_code' => 'ORD-CUST-1',
            'customer_name' => 'Customer User',
            'installation_address' => 'Alamat Customer',
            'payment_method' => 'qris',
            'status' => 'awaiting_payment',
            'subtotal' => 300000.00,
            'grand_total' => 300000.00,
        ]);

        // 10. Customer can view own order on storefront endpoint
        $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/storefront/orders/' . $orderCustomer->id)
            ->assertStatus(200);

        // Other customer trying to view customer's order gets 404
        $this->actingAs($otherCustomer, 'sanctum')
            ->getJson('/api/storefront/orders/' . $orderCustomer->id)
            ->assertStatus(404);
    }
}
