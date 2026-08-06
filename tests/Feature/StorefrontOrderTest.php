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

    public function test_can_track_order_by_unique_order_code(): void
    {
        $order = Order::create([
            'public_id' => (string) Str::uuid(),
            'unique_order_code' => 'HBL-2026-0001',
            'customer_name' => 'Budi Santoso',
            'installation_address' => 'Jl. Sudirman No. 45',
            'payment_method' => 'qris',
            'subtotal' => 450000,
            'grand_total' => 450000,
            'status' => 'awaiting_payment',
        ]);

        $response = $this->getJson('/api/storefront/orders/track/HBL-2026-0001');

        $response->assertStatus(200)
            ->assertJsonPath('data.unique_order_code', 'HBL-2026-0001')
            ->assertJsonPath('data.customer_name', 'Budi Santoso');
    }

    public function test_authenticated_user_can_list_view_and_cancel_orders(): void
    {
        $user = User::create([
            'name' => 'Siti Rahma',
            'email' => 'siti@example.com',
            'phone_e164' => '+6281987654321',
            'password' => bcrypt('password123'),
        ]);

        $order = Order::create([
            'public_id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'unique_order_code' => 'HBL-2026-0002',
            'customer_name' => 'Siti Rahma',
            'installation_address' => 'Jl. Gatot Subroto No. 12',
            'payment_method' => 'bank_transfer',
            'subtotal' => 900000,
            'grand_total' => 900000,
            'status' => 'awaiting_payment',
        ]);

        $listResponse = $this->actingAs($user, 'sanctum')
            ->getJson('/api/storefront/orders');

        $listResponse->assertStatus(200)
            ->assertJsonPath('data.0.unique_order_code', 'HBL-2026-0002');

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
