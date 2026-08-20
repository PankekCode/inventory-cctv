<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Item;
use App\Models\Order;
use App\Models\Supplier;
use App\Models\User;
use App\Services\InvoiceService;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class InvoiceAndReportingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $customer;
    private User $otherCustomer;

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
            'name' => 'Customer A',
            'email' => 'customera@hablun.com',
            'password' => bcrypt('secret123'),
            'role' => 'customer',
            'is_active' => true,
        ]);

        $this->otherCustomer = User::create([
            'name' => 'Customer B',
            'email' => 'customerb@hablun.com',
            'password' => bcrypt('secret123'),
            'role' => 'customer',
            'is_active' => true,
        ]);
    }

    public function test_invoice_authorization_and_data_integrity(): void
    {
        $order = Order::create([
            'public_id' => (string) Str::uuid(),
            'user_id' => $this->customer->id,
            'order_code' => 'ORD-INV-001',
            'customer_name' => 'Customer A',
            'customer_email' => 'customera@hablun.com',
            'installation_address' => 'Jl. Kebon Sirih No. 12',
            'payment_method' => 'qris',
            'status' => 'order_received',
            'payment_status' => 'paid',
            'subtotal' => 450000.00,
            'installation_fee' => 0.00,
            'tax_amount' => 0.00,
            'grand_total' => 450000.00,
            'currency' => 'IDR',
        ]);

        $order->items()->create([
            'product_name' => 'EZVIZ H6C 2MP',
            'variant_name' => 'Unit Saja',
            'sku' => 'SKU-EZ-H6C',
            'quantity' => 1,
            'unit_price' => 450000.00,
            'line_total' => 450000.00,
        ]);

        // 1. Customer can access own invoice
        $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/storefront/orders/' . $order->id . '/invoice')
            ->assertStatus(200);

        // 2. Customer cannot access another customer's invoice -> 404
        $this->actingAs($this->otherCustomer, 'sanctum')
            ->getJson('/api/storefront/orders/' . $order->id . '/invoice')
            ->assertStatus(404);

        // 3. Admin can access invoice -> 200
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/orders/' . $order->id . '/invoice')
            ->assertStatus(200);

        // 4. Unauthenticated access to authenticated customer's invoice -> 403
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/storefront/orders/track/' . $order->order_code . '/invoice')
            ->assertStatus(403);

        // 5, 6, 7. InvoiceService data integrity: historical price, tax = 0, total matches
        /** @var InvoiceService $invoiceService */
        $invoiceService = app(InvoiceService::class);
        $data = $invoiceService->getInvoiceData($order);

        $this->assertEquals(450000.00, $data['totals']['grand_total']);
        $this->assertEquals(0.00, $data['totals']['tax_amount']);
        $this->assertEquals(450000.00, $data['items'][0]['unit_price']);
    }

    public function test_reporting_authorization_date_filtering_and_metrics(): void
    {
        // 9. Customer cannot access sales report -> 403
        $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/dashboard')
            ->assertStatus(403);

        // 8. Admin can access report -> 200
        $reportRes = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/dashboard?period=this_month');
        $reportRes->assertStatus(200);

        // Create completed order
        Order::create([
            'public_id' => (string) Str::uuid(),
            'order_code' => 'ORD-REP-COMPLETED',
            'customer_name' => 'Buyer Completed',
            'installation_address' => 'Jl. Merdeka',
            'payment_method' => 'qris',
            'status' => 'completed',
            'payment_status' => 'paid',
            'subtotal' => 1000000.00,
            'grand_total' => 1000000.00,
            'created_at' => now(),
        ]);

        // Create cancelled order (should NOT count as completed sales)
        Order::create([
            'public_id' => (string) Str::uuid(),
            'order_code' => 'ORD-REP-CANCELLED',
            'customer_name' => 'Buyer Cancelled',
            'installation_address' => 'Jl. Merdeka',
            'payment_method' => 'qris',
            'status' => 'cancelled',
            'payment_status' => 'cancelled',
            'subtotal' => 500000.00,
            'grand_total' => 500000.00,
            'created_at' => now(),
        ]);

        // 10, 11, 12, 13, 14. Report metrics & filtering
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/dashboard?period=today');

        $response->assertStatus(200)
            ->assertJsonPath('data.sales.total_sales', 1000000) // 1,000,000 paid sales
            ->assertJsonPath('data.sales.completed_orders', 1)
            ->assertJsonPath('data.sales.cancelled_orders', 1);

        // 15. Inventory statistics respect available_stock = stock - stock_reserved
        $supplier = Supplier::create(['code' => 'SUP-REP', 'name' => 'Supplier Rep']);
        $category = Category::create(['code' => 'CAT-REP', 'name' => 'Category Rep']);

        Item::create([
            'supplier_id' => $supplier->id,
            'category_id' => $category->id,
            'code' => 'ITEM-REP-01',
            'name' => 'Item Reporting',
            'purchase_price' => 100000,
            'stock' => 20,
            'stock_reserved' => 5,
            'unit' => 'Pcs',
        ]);

        $invResponse = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/dashboard');

        $invResponse->assertStatus(200)
            ->assertJsonPath('data.inventory.total_stock', 20)
            ->assertJsonPath('data.inventory.total_stock_reserved', 5)
            ->assertJsonPath('data.inventory.total_available_stock', 15);
    }
}
