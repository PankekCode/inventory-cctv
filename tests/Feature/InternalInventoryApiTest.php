<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Item;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InternalInventoryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_via_email(): void
    {
        $user = User::create([
            'name' => 'Admin Staff',
            'email' => 'admin@example.com',
            'password' => bcrypt('secret123'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => ['user', 'token'],
            ]);
    }

    public function test_can_manage_categories_suppliers_items_and_stock(): void
    {
        $admin = User::create([
            'name' => 'Admin Staff',
            'email' => 'admin@example.com',
            'password' => bcrypt('secret123'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        // Category CRUD
        $catResponse = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/categories', [
                'code' => 'CCTV',
                'name' => 'Kamera CCTV',
            ]);
        $catResponse->assertStatus(201);
        $categoryId = $catResponse->json('data.id');

        // Supplier CRUD
        $supResponse = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/suppliers', [
                'code' => 'HIK',
                'name' => 'Hikvision',
                'phone' => '081234567890',
                'address' => 'Jakarta, Indonesia',
            ]);
        $supResponse->assertStatus(201);
        $supplierId = $supResponse->json('data.id');

        // Item CRUD
        $itemResponse = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/items', [
                'category_id' => $categoryId,
                'supplier_id' => $supplierId,
                'code' => 'HIK-001',
                'name' => 'Kamera Outdoor 2MP',
                'purchase_price' => 350000,
                'minimum_stock' => 5,
                'stock' => 20,
                'unit' => 'Pcs',
            ]);
        $itemResponse->assertStatus(201);
        $itemId = $itemResponse->json('data.id');

        // Stock In
        $stockInResponse = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/stock-in', [
                'item_id' => $itemId,
                'quantity' => 10,
                'price' => 350000,
                'movement_date' => now()->toDateString(),
                'note' => 'Pasokan baru dari distributor',
            ]);
        $stockInResponse->assertStatus(200);

        // Stock Out
        $stockOutResponse = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/stock-out', [
                'item_id' => $itemId,
                'quantity' => 5,
                'movement_date' => now()->toDateString(),
                'note' => 'Diambil untuk instalasi project',
            ]);
        $stockOutResponse->assertStatus(200);

        // Stock Movements Index & Show
        $movementIndex = $this->actingAs($admin, 'sanctum')->getJson('/api/stock-movements');
        $movementIndex->assertStatus(200);
        $movementId = $movementIndex->json('data.0.id');

        $this->actingAs($admin, 'sanctum')->getJson('/api/stock-movements/' . $movementId)
            ->assertStatus(200)
            ->assertJsonPath('data.item.id', $itemId);

        // Dashboard
        $dashboardResponse = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/dashboard');
        $dashboardResponse->assertStatus(200);

        // Category Read / Update
        $this->actingAs($admin, 'sanctum')->getJson('/api/categories')->assertStatus(200);
        $this->actingAs($admin, 'sanctum')->getJson('/api/categories/' . $categoryId)->assertStatus(200);
        $this->actingAs($admin, 'sanctum')->putJson('/api/categories/' . $categoryId, ['name' => 'Kamera CCTV Pro'])->assertStatus(200);

        // Supplier Read / Update
        $this->actingAs($admin, 'sanctum')->getJson('/api/suppliers')->assertStatus(200);
        $this->actingAs($admin, 'sanctum')->getJson('/api/suppliers/' . $supplierId)->assertStatus(200);
        $this->actingAs($admin, 'sanctum')->putJson('/api/suppliers/' . $supplierId, [
            'name' => 'Hikvision Indonesia',
            'phone' => '081234567890',
            'address' => 'Jakarta, Indonesia',
        ])->assertStatus(200);

        // Item Read / Update
        $this->actingAs($admin, 'sanctum')->getJson('/api/items')->assertStatus(200);
        $this->actingAs($admin, 'sanctum')->getJson('/api/items/' . $itemId)->assertStatus(200);
        $this->actingAs($admin, 'sanctum')->putJson('/api/items/' . $itemId, [
            'category_id' => $categoryId,
            'supplier_id' => $supplierId,
            'code' => 'HIK-001',
            'name' => 'Kamera Outdoor 2MP v2',
            'purchase_price' => 360000,
            'minimum_stock' => 5,
            'unit' => 'Pcs',
        ])->assertStatus(200);

        // Admin Logout
        $token = $admin->createToken('admin-token')->plainTextToken;
        $logoutRes = $this->withHeader('Authorization', 'Bearer ' . $token)->postJson('/api/auth/logout');
        $logoutRes->assertStatus(200)
            ->assertJsonPath('message', 'Logout berhasil.');
    }
}
