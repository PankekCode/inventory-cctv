<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Item;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\Technician;
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

    public function test_guest_and_customer_are_rejected_from_admin_orders(): void
    {
        // 1. Guest -> 401
        $this->getJson('/api/admin/orders')->assertStatus(401);

        // 2. Customer -> 403
        $this->actingAs($this->customer, 'sanctum')->getJson('/api/admin/orders')->assertStatus(403);
        $this->actingAs($this->customer, 'sanctum')->getJson('/api/orders')->assertStatus(403);
    }

    public function test_admin_can_list_and_view_order_details(): void
    {
        $order = Order::create([
            'public_id' => (string) Str::uuid(),
            'unique_order_code' => 'ORD-2026-0001',
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'installation_address' => 'Jl. Kebon Jeruk No. 5',
            'payment_method' => 'qris',
            'status' => 'order_received',
            'payment_status' => 'paid',
            'subtotal' => 500000.00,
            'grand_total' => 500000.00,
        ]);

        // 3. List orders
        $listResponse = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/orders');

        $listResponse->assertStatus(200)
            ->assertJsonPath('data.0.unique_order_code', 'ORD-2026-0001');

        // 4. View order details
        $showResponse = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/orders/' . $order->id);

        $showResponse->assertStatus(200)
            ->assertJsonPath('data.customer_name', 'John Doe')
            ->assertJsonPath('data.payment_status', 'paid');
    }

    public function test_admin_can_update_valid_order_status_and_invalid_is_rejected(): void
    {
        $order = Order::create([
            'public_id' => (string) Str::uuid(),
            'unique_order_code' => 'ORD-2026-0002',
            'customer_name' => 'Jane Doe',
            'installation_address' => 'Jl. Sudirman No. 10',
            'payment_method' => 'qris',
            'status' => 'order_received',
            'payment_status' => 'paid',
            'subtotal' => 1000000.00,
            'grand_total' => 1000000.00,
        ]);

        // 5. Update valid status transition (order_received -> technician_scheduled)
        $validResponse = $this->actingAs($this->admin, 'sanctum')
            ->patchJson('/api/admin/orders/' . $order->id . '/status', [
                'status' => 'technician_scheduled',
                'note' => 'Jadwal instalasi telah disetujui',
            ]);

        $validResponse->assertStatus(200)
            ->assertJsonPath('data.status', 'technician_scheduled');

        // 9. Status history is recorded correctly
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'status' => 'technician_scheduled',
            'note' => 'Jadwal instalasi telah disetujui',
        ]);

        // 6. Invalid status transition (technician_scheduled -> completed directly) -> 422
        $invalidResponse = $this->actingAs($this->admin, 'sanctum')
            ->patchJson('/api/admin/orders/' . $order->id . '/status', [
                'status' => 'completed',
            ]);

        $invalidResponse->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_admin_can_assign_active_technician_and_inactive_technician_is_rejected(): void
    {
        $activeTech = Technician::create([
            'name' => 'Budi Santoso',
            'phone_e164' => '+62811111111',
            'is_active' => true,
        ]);

        $inactiveTech = Technician::create([
            'name' => 'Andi Wijaya',
            'phone_e164' => '+62822222222',
            'is_active' => false,
        ]);

        $order = Order::create([
            'public_id' => (string) Str::uuid(),
            'unique_order_code' => 'ORD-2026-0003',
            'customer_name' => 'Ahmad',
            'installation_address' => 'Jl. Gatot Subroto No. 1',
            'payment_method' => 'qris',
            'status' => 'order_received',
            'payment_status' => 'paid',
            'subtotal' => 800000.00,
            'grand_total' => 800000.00,
        ]);

        // 8. Inactive technician is rejected -> 422
        $inactiveResponse = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/orders/' . $order->id . '/assign-technician', [
                'technician_id' => $inactiveTech->id,
            ]);

        $inactiveResponse->assertStatus(422)
            ->assertJsonValidationErrors(['technician']);

        // 7. Active technician assignment succeeds -> 200
        $activeResponse = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/orders/' . $order->id . '/assign-technician', [
                'technician_id' => $activeTech->id,
            ]);

        $activeResponse->assertStatus(200)
            ->assertJsonPath('data.technician.name', 'Budi Santoso')
            ->assertJsonPath('data.status', 'technician_scheduled');
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
            'unique_order_code' => 'ORD-CUST-1',
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
