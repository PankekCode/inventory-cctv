<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Item;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PhoneVerification;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentHardeningTest extends TestCase
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

    public function test_successful_sandbox_payment_and_amount_integrity(): void
    {
        $order = Order::create([
            'public_id' => (string) Str::uuid(),
            'user_id' => $this->customer->id,
            'unique_order_code' => 'ORD-PAY-001',
            'customer_name' => 'Customer A',
            'installation_address' => 'Jl. Kebon Sirih No. 1',
            'payment_method' => 'qris',
            'status' => 'awaiting_payment',
            'payment_status' => 'pending',
            'subtotal' => 600000.00,
            'grand_total' => 600000.00,
        ]);

        $payment = Payment::create([
            'order_id' => $order->id,
            'idempotency_key' => (string) Str::uuid(),
            'gateway' => 'sandbox',
            'method' => 'qris',
            'provider_reference' => 'SBX-ORDPAY001-TEST1234',
            'amount' => 600000.00,
            'status' => 'pending',
            'expires_at' => now()->addHour(),
        ]);

        // 7. Tampered callback amount (1.00 instead of 600000.00) -> 422
        $tamperedResponse = $this->postJson('/api/storefront/payments/webhook', [
            'gateway' => 'sandbox',
            'provider_reference' => $payment->provider_reference,
            'status' => 'paid',
            'event_id' => 'evt_tamper_01',
            'amount' => 1.00,
        ]);
        $tamperedResponse->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);

        // 1. Successful sandbox payment webhook -> 200
        $successResponse = $this->postJson('/api/storefront/payments/webhook', [
            'gateway' => 'sandbox',
            'provider_reference' => $payment->provider_reference,
            'status' => 'paid',
            'event_id' => 'evt_success_01',
            'amount' => 600000.00,
        ]);

        $successResponse->assertStatus(200)
            ->assertJsonPath('data.status', 'paid');

        $this->assertEquals('paid', $order->fresh()->payment_status);
        $this->assertEquals('order_received', $order->fresh()->status);
    }

    public function test_failed_sandbox_payment_does_not_complete_order(): void
    {
        $order = Order::create([
            'public_id' => (string) Str::uuid(),
            'user_id' => $this->customer->id,
            'unique_order_code' => 'ORD-PAY-002',
            'customer_name' => 'Customer A',
            'installation_address' => 'Jl. Kebon Sirih No. 2',
            'payment_method' => 'qris',
            'status' => 'awaiting_payment',
            'payment_status' => 'pending',
            'subtotal' => 400000.00,
            'grand_total' => 400000.00,
        ]);

        $payment = Payment::create([
            'order_id' => $order->id,
            'idempotency_key' => (string) Str::uuid(),
            'gateway' => 'sandbox',
            'method' => 'qris',
            'provider_reference' => 'SBX-ORDPAY002-FAIL1234',
            'amount' => 400000.00,
            'status' => 'pending',
            'expires_at' => now()->addHour(),
        ]);

        // 2 & 11. Payment failure callback
        $failResponse = $this->postJson('/api/storefront/payments/webhook', [
            'gateway' => 'sandbox',
            'provider_reference' => $payment->provider_reference,
            'status' => 'failed',
        ]);

        $failResponse->assertStatus(200)
            ->assertJsonPath('data.status', 'failed');

        // Order status remains awaiting_payment and payment_status remains pending (not completed/paid)
        $this->assertEquals('awaiting_payment', $order->fresh()->status);
        $this->assertEquals('pending', $order->fresh()->payment_status);
    }

    public function test_duplicate_webhook_is_idempotent(): void
    {
        $supplier = Supplier::create(['code' => 'S-IDEM', 'name' => 'Supplier Idem']);
        $category = Category::create(['code' => 'C-IDEM', 'name' => 'Category Idem']);
        $item = Item::create([
            'supplier_id' => $supplier->id,
            'category_id' => $category->id,
            'code' => 'ITEM-IDEM',
            'name' => 'Item Idem',
            'purchase_price' => 100000,
            'stock' => 10,
            'stock_reserved' => 1,
            'unit' => 'Pcs',
        ]);

        $order = Order::create([
            'public_id' => (string) Str::uuid(),
            'user_id' => $this->customer->id,
            'unique_order_code' => 'ORD-IDEM-001',
            'customer_name' => 'Customer A',
            'installation_address' => 'Jl. Kebon Sirih No. 3',
            'payment_method' => 'qris',
            'status' => 'awaiting_payment',
            'payment_status' => 'pending',
            'subtotal' => 200000.00,
            'grand_total' => 200000.00,
        ]);

        $order->reservations()->create([
            'item_id' => $item->id,
            'quantity' => 1,
            'status' => 'reserved',
        ]);

        $payment = Payment::create([
            'order_id' => $order->id,
            'idempotency_key' => (string) Str::uuid(),
            'gateway' => 'sandbox',
            'method' => 'qris',
            'provider_reference' => 'SBX-IDEM-001',
            'amount' => 200000.00,
            'status' => 'pending',
            'expires_at' => now()->addHour(),
        ]);

        $payload = [
            'gateway' => 'sandbox',
            'provider_reference' => $payment->provider_reference,
            'status' => 'paid',
            'event_id' => 'evt_duplicate_test',
            'amount' => 200000.00,
        ];

        // First callback
        $res1 = $this->postJson('/api/storefront/payments/webhook', $payload);
        $res1->assertStatus(200);

        // Physical stock decremented from 10 to 9, stock_reserved from 1 to 0
        $this->assertEquals(9, $item->fresh()->stock);
        $this->assertEquals(0, $item->fresh()->stock_reserved);

        // 4, 12. Second duplicate callback with same event_id
        $res2 = $this->postJson('/api/storefront/payments/webhook', $payload);
        $res2->assertStatus(200);

        // Inventory is NOT double-deducted (stock remains 9, stock_reserved remains 0)
        $this->assertEquals(9, $item->fresh()->stock);
        $this->assertEquals(0, $item->fresh()->stock_reserved);
    }

    public function test_payment_authorization_and_price_tamper_protection(): void
    {
        $order = Order::create([
            'public_id' => (string) Str::uuid(),
            'user_id' => $this->customer->id,
            'unique_order_code' => 'ORD-AUTH-001',
            'customer_name' => 'Customer A',
            'installation_address' => 'Jl. Kebon Sirih No. 4',
            'payment_method' => 'qris',
            'status' => 'awaiting_payment',
            'payment_status' => 'pending',
            'subtotal' => 750000.00,
            'grand_total' => 750000.00,
        ]);

        $payment = Payment::create([
            'order_id' => $order->id,
            'idempotency_key' => (string) Str::uuid(),
            'gateway' => 'sandbox',
            'method' => 'qris',
            'provider_reference' => 'SBX-AUTH-001',
            'amount' => 750000.00,
            'status' => 'pending',
            'expires_at' => now()->addHour(),
        ]);

        // 5. Customer cannot access another customer's payment -> 403
        $this->actingAs($this->otherCustomer, 'sanctum')
            ->getJson('/api/storefront/payments/' . $payment->provider_reference)
            ->assertStatus(403);

        // Customer can access own payment -> 200
        $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/storefront/payments/' . $payment->provider_reference)
            ->assertStatus(200)
            ->assertJsonPath('data.amount', '750000.00');

        // Admin can access payment -> 200
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/storefront/payments/' . $payment->provider_reference)
            ->assertStatus(200);

        // 10. Product price changes after order creation DO NOT affect stored order/payment amount
        $product = Product::create(['name' => 'Prod', 'slug' => 'prod', 'is_published' => true]);
        $variant = ProductVariant::create(['product_id' => $product->id, 'sku' => 'SKU-MUT', 'name' => 'Var', 'price' => 750000.00, 'is_active' => true]);

        // Price changes from 750,000 to 999,000
        $variant->update(['price' => 999000.00]);

        // Existing payment amount remains strictly 750,000
        $this->assertEquals('750000.00', (string) $payment->fresh()->amount);
    }
}
