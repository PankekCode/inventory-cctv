<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StorefrontOrderTest extends TestCase
{
    use RefreshDatabase;

    // ─────────────────────────────────────────────────────────────────────────
    // Guest order tracking — phone gate (Decision 14.3, 14.5)
    // ─────────────────────────────────────────────────────────────────────────

    public function test_guest_tracking_requires_phone_parameter(): void
    {
        $order = Order::create([
            'public_id'            => (string) Str::uuid(),
            'order_code'           => 'HBL-260820-A1B2C3',
            'guest_phone_e164'     => '+6281234567890',
            'customer_name'        => 'Budi Santoso',
            'installation_address' => 'Jl. Merdeka 10',
            'payment_method'       => 'qris',
            'subtotal'             => 450000,
            'grand_total'          => 450000,
            'status'               => 'awaiting_payment',
        ]);

        // No phone param → 422
        $response = $this->getJson('/api/storefront/orders/track/' . $order->order_code);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_guest_tracking_with_wrong_phone_returns_404(): void
    {
        $order = Order::create([
            'public_id'            => (string) Str::uuid(),
            'order_code'           => 'HBL-260820-D4E5F6',
            'guest_phone_e164'     => '+6281234567890',
            'customer_name'        => 'Budi Santoso',
            'installation_address' => 'Jl. Merdeka 10',
            'payment_method'       => 'qris',
            'subtotal'             => 450000,
            'grand_total'          => 450000,
            'status'               => 'awaiting_payment',
        ]);

        // Wrong phone → 404 (not 403 — don't confirm order exists)
        $response = $this->getJson(
            '/api/storefront/orders/track/' . $order->order_code . '?phone=08999999999'
        );

        $response->assertStatus(404);
    }

    public function test_guest_tracking_with_correct_phone_returns_order_data(): void
    {
        $order = Order::create([
            'public_id'            => (string) Str::uuid(),
            'order_code'           => 'HBL-260820-G7H8J9',
            'guest_phone_e164'     => '+6281234567890',
            'customer_name'        => 'Budi Santoso',
            'installation_address' => 'Jl. Sudirman No. 45',
            'payment_method'       => 'qris',
            'subtotal'             => 450000,
            'grand_total'          => 450000,
            'status'               => 'awaiting_payment',
            'payment_status'       => 'pending',
        ]);

        // Correct phone → 200 with tracking data
        $response = $this->getJson(
            '/api/storefront/orders/track/' . $order->order_code . '?phone=081234567890'
        );

        $response->assertStatus(200)
            ->assertJsonPath('data.order_code', 'HBL-260820-G7H8J9')
            ->assertJsonPath('data.customer_name', 'Budi Santoso')
            ->assertJsonPath('data.status', 'awaiting_payment');

        // guest_phone_e164 must NOT be in the response
        $this->assertArrayNotHasKey('guest_phone_e164', $response->json('data'));
        $this->assertArrayNotHasKey('customer_email', $response->json('data'));
    }

    public function test_guest_tracking_nonexistent_code_returns_404(): void
    {
        $response = $this->getJson(
            '/api/storefront/orders/track/HBL-260820-ZZZZZZ?phone=081234567890'
        );

        $response->assertStatus(404);
    }

    public function test_authenticated_order_tracking_without_token_returns_minimal_data(): void
    {
        $user = User::create([
            'name'     => 'Siti Rahma',
            'email'    => 'siti@example.com',
            'phone_e164' => '+6281987654321',
            'password' => bcrypt('password123'),
        ]);

        $order = Order::create([
            'public_id'            => (string) Str::uuid(),
            'user_id'              => $user->id,
            'order_code'           => 'HBL-260820-K7L8M9',
            'customer_name'        => 'Siti Rahma',
            'installation_address' => 'Jl. Gatot Subroto No. 12',
            'payment_method'       => 'bank_transfer',
            'subtotal'             => 900000,
            'grand_total'          => 900000,
            'status'               => 'awaiting_payment',
            'payment_status'       => 'pending',
        ]);

        // No token → returns minimal public data only (Decision 14.3)
        $response = $this->getJson('/api/storefront/orders/track/' . $order->order_code);

        $response->assertStatus(200)
            ->assertJsonPath('data.order_code', 'HBL-260820-K7L8M9')
            ->assertJsonPath('data.status', 'awaiting_payment')
            ->assertJsonPath('data.payment_status', 'pending');

        // Full fields must NOT be present in minimal response
        $this->assertArrayNotHasKey('customer_name', $response->json('data'));
        $this->assertArrayNotHasKey('installation_address', $response->json('data'));
        $this->assertArrayNotHasKey('grand_total', $response->json('data'));
        $this->assertArrayNotHasKey('items', $response->json('data'));
    }

    public function test_qris_payload_hidden_once_payment_is_confirmed(): void
    {
        $order = Order::create([
            'public_id'            => (string) Str::uuid(),
            'order_code'           => 'HBL-260820-P3Q4R5',
            'guest_phone_e164'     => '+6281234567890',
            'customer_name'        => 'Citra Dewi',
            'installation_address' => 'Jl. Diponegoro No. 7',
            'payment_method'       => 'qris',
            'subtotal'             => 600000,
            'grand_total'          => 600000,
            'status'               => 'order_received',
            'payment_status'       => 'paid',  // ← already paid
        ]);

        $order->payments()->create([
            'idempotency_key'    => (string) Str::uuid(),
            'gateway'            => 'sandbox',
            'method'             => 'qris',
            'provider_reference' => 'SBX-PAID-TEST',
            'amount'             => 600000,
            'status'             => 'paid',
            'qris_payload'       => 'HABLUN-SANDBOX-QRIS|SBX-PAID-TEST|600000',
            'expires_at'         => now()->addHour(),
        ]);

        $response = $this->getJson(
            '/api/storefront/orders/track/' . $order->order_code . '?phone=081234567890'
        );

        $response->assertStatus(200);

        // qris_payload must be null once payment_status is not "pending"
        $this->assertNull($response->json('data.qris_payload'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Guest invoice tracking — phone gate (Decision 14.7)
    // ─────────────────────────────────────────────────────────────────────────

    public function test_guest_invoice_requires_phone_parameter(): void
    {
        $order = Order::create([
            'public_id'            => (string) Str::uuid(),
            'order_code'           => 'HBL-260820-S6T7U8',
            'guest_phone_e164'     => '+6281234567890',
            'customer_name'        => 'Citra Dewi',
            'installation_address' => 'Jl. Diponegoro',
            'payment_method'       => 'qris',
            'subtotal'             => 450000,
            'grand_total'          => 450000,
            'status'               => 'order_received',
            'payment_status'       => 'paid',
        ]);

        // No phone param → 422
        $this->getJson('/api/storefront/orders/track/' . $order->order_code . '/invoice')
            ->assertStatus(422);
    }

    public function test_guest_invoice_with_wrong_phone_returns_404(): void
    {
        $order = Order::create([
            'public_id'            => (string) Str::uuid(),
            'order_code'           => 'HBL-260820-V9W2X3',
            'guest_phone_e164'     => '+6281234567890',
            'customer_name'        => 'Citra Dewi',
            'installation_address' => 'Jl. Diponegoro',
            'payment_method'       => 'qris',
            'subtotal'             => 450000,
            'grand_total'          => 450000,
            'status'               => 'order_received',
            'payment_status'       => 'paid',
        ]);

        $this->getJson(
            '/api/storefront/orders/track/' . $order->order_code . '/invoice?phone=08999999999'
        )->assertStatus(404);
    }

    public function test_guest_invoice_with_correct_phone_downloads_invoice(): void
    {
        $order = Order::create([
            'public_id'            => (string) Str::uuid(),
            'order_code'           => 'HBL-260820-Y4Z5A6',
            'guest_phone_e164'     => '+6281234567890',
            'customer_name'        => 'Citra Dewi',
            'installation_address' => 'Jl. Diponegoro No. 10',
            'payment_method'       => 'qris',
            'subtotal'             => 450000,
            'grand_total'          => 450000,
            'status'               => 'order_received',
            'payment_status'       => 'paid',
        ]);

        $this->getJson(
            '/api/storefront/orders/track/' . $order->order_code . '/invoice?phone=081234567890'
        )->assertStatus(200);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Authenticated customer order management
    // ─────────────────────────────────────────────────────────────────────────

    public function test_authenticated_user_can_list_view_and_cancel_orders(): void
    {
        $user = User::create([
            'name'       => 'Siti Rahma',
            'email'      => 'siti2@example.com',
            'phone_e164' => '+6281987654322',
            'password'   => bcrypt('password123'),
        ]);

        $order = Order::create([
            'public_id'            => (string) Str::uuid(),
            'user_id'              => $user->id,
            'order_code'           => 'HBL-260820-B7C8D9',
            'customer_name'        => 'Siti Rahma',
            'installation_address' => 'Jl. Gatot Subroto No. 12',
            'payment_method'       => 'bank_transfer',
            'subtotal'             => 900000,
            'grand_total'          => 900000,
            'status'               => 'awaiting_payment',
        ]);

        $listResponse = $this->actingAs($user, 'sanctum')
            ->getJson('/api/storefront/orders');

        $listResponse->assertStatus(200)
            ->assertJsonPath('data.0.order_code', 'HBL-260820-B7C8D9');

        $detailResponse = $this->actingAs($user, 'sanctum')
            ->getJson('/api/storefront/orders/' . $order->id);

        $detailResponse->assertStatus(200)
            ->assertJsonPath('data.id', $order->id);

        $cancelResponse = $this->actingAs($user, 'sanctum')
            ->postJson('/api/storefront/orders/' . $order->id . '/cancel', [
                'note' => 'Ingin mengubah alamat',
            ]);

        $cancelResponse->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');
    }
}
