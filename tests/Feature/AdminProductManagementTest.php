<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Item;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminProductManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin User',
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

    public function test_customer_cannot_access_admin_product_endpoints(): void
    {
        // Customer receives 403 on admin routes
        $this->actingAs($this->customer, 'sanctum')->getJson('/api/products')->assertStatus(403);
        $this->actingAs($this->customer, 'sanctum')->postJson('/api/products', ['name' => 'Unauthorized'])->assertStatus(403);
        $this->actingAs($this->customer, 'sanctum')->getJson('/api/brands')->assertStatus(403);
        $this->actingAs($this->customer, 'sanctum')->postJson('/api/brands', ['name' => 'Unauthorized'])->assertStatus(403);
    }

    public function test_admin_can_create_update_and_delete_product(): void
    {
        $brand = Brand::create(['name' => 'EZVIZ', 'slug' => 'ezviz']);

        // 1. Create Product
        $createResponse = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/products', [
                'name' => 'CCTV EZVIZ H6C',
                'brand_id' => $brand->id,
                'product_type' => 'wireless',
                'short_description' => 'Kamera CCTV Wi-Fi Smart Home',
                'description' => 'Deskripsi lengkap kamera CCTV H6C.',
                'is_featured' => true,
                'is_published' => true,
            ]);

        $createResponse->assertStatus(201)
            ->assertJsonPath('data.name', 'CCTV EZVIZ H6C')
            ->assertJsonPath('data.slug', 'cctv-ezviz-h6c');

        $productId = $createResponse->json('data.id');

        // 2. Update Product
        $updateResponse = $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/products/' . $productId, [
                'name' => 'CCTV EZVIZ H6C Pro',
                'short_description' => 'Updated short description',
            ]);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('data.name', 'CCTV EZVIZ H6C Pro')
            ->assertJsonPath('data.slug', 'cctv-ezviz-h6c-pro');

        // 3. Deactivate / Delete Product
        $deleteResponse = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson('/api/products/' . $productId);

        $deleteResponse->assertStatus(200);
        $this->assertDatabaseMissing('products', ['id' => $productId]);
    }

    public function test_admin_can_create_and_update_variant_with_sku_and_price_validation(): void
    {
        $product = Product::create([
            'name' => 'CCTV IMOU Ranger 2',
            'slug' => 'cctv-imou-ranger-2',
            'is_published' => true,
        ]);

        // 4. Create Variant
        $createResponse = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/products/' . $product->id . '/variants', [
                'sku' => 'SKU-IMOU-R2-3MP',
                'name' => 'IMOU Ranger 2 3MP',
                'price' => 420000.00,
                'camera_count' => 1,
                'installation_included' => false,
                'is_stock_managed' => true,
                'is_active' => true,
            ]);

        $createResponse->assertStatus(201)
            ->assertJsonPath('data.sku', 'SKU-IMOU-R2-3MP')
            ->assertJsonPath('data.price', '420000.00');

        $variantId = $createResponse->json('data.id');

        // 6. Duplicate SKU Validation -> 422
        $duplicateSkuResponse = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/products/' . $product->id . '/variants', [
                'sku' => 'SKU-IMOU-R2-3MP',
                'name' => 'Duplicate Variant',
                'price' => 500000.00,
            ]);
        $duplicateSkuResponse->assertStatus(422)
            ->assertJsonValidationErrors(['sku']);

        // 7. Negative Personal Price Validation -> 422
        $invalidPriceResponse = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/products/' . $product->id . '/variants', [
                'sku' => 'SKU-IMOU-R2-NEGATIVE',
                'name' => 'Invalid Price Variant',
                'price' => -100.00,
            ]);
        $invalidPriceResponse->assertStatus(422)
            ->assertJsonValidationErrors(['price']);

        // 5. Update Variant
        $updateResponse = $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/variants/' . $variantId, [
                'price' => 450000.00,
                'installation_included' => true,
            ]);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('data.price', '450000.00')
            ->assertJsonPath('data.installation_included', true);
    }

    public function test_admin_can_manage_product_images_and_features(): void
    {
        Storage::fake('public');

        $product = Product::create([
            'name' => 'CCTV Dahua Hero',
            'slug' => 'cctv-dahua-hero',
            'is_published' => true,
        ]);

        // 8. Product Images
        $file = UploadedFile::fake()->image('hero.jpg', 600, 600);
        $imageResponse = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/products/' . $product->id . '/images', [
                'image' => $file,
                'alt_text' => 'Kamera Dahua Hero',
                'is_primary' => true,
            ]);

        $imageResponse->assertStatus(201)
            ->assertJsonPath('data.is_primary', true);

        // 9. Product Features
        $featureResponse = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/products/' . $product->id . '/features', [
                'title' => 'Human Detection',
                'description' => 'Mendeteksi gerak manusia secara presisi',
                'icon' => 'user-check',
            ]);

        $featureResponse->assertStatus(201)
            ->assertJsonPath('data.title', 'Human Detection');
    }

    public function test_admin_can_manage_bundle_components_and_duplicates_are_rejected(): void
    {
        $supplier = Supplier::create(['code' => 'EZ', 'name' => 'EZVIZ']);
        $category = Category::create(['code' => 'CCTV', 'name' => 'CCTV']);

        $itemCamera = Item::create([
            'supplier_id' => $supplier->id,
            'category_id' => $category->id,
            'code' => 'CAM-001',
            'name' => 'Kamera EZVIZ',
            'purchase_price' => 300000,
            'stock' => 50,
            'unit' => 'Pcs',
        ]);

        $itemDvr = Item::create([
            'supplier_id' => $supplier->id,
            'category_id' => $category->id,
            'code' => 'DVR-001',
            'name' => 'DVR 4 Channel',
            'purchase_price' => 500000,
            'stock' => 10,
            'unit' => 'Pcs',
        ]);

        $product = Product::create([
            'name' => 'Paket CCTV Rumah 4 Kamera',
            'slug' => 'paket-cctv-rumah-4-kamera',
            'is_published' => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'PKT-4CAM-EZ',
            'name' => 'Paket 4 Kamera EZVIZ',
            'price' => 2500000.00,
            'is_active' => true,
        ]);

        // 10 & 11. Add Bundle Component referencing physical inventory Item
        $comp1Response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/variants/' . $variant->id . '/components', [
                'item_id' => $itemCamera->id,
                'quantity' => 4,
            ]);

        $comp1Response->assertStatus(201)
            ->assertJsonPath('data.item_id', $itemCamera->id)
            ->assertJsonPath('data.quantity', 4);

        // 12. Re-adding duplicate component is rejected -> 422
        $duplicateResponse = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/variants/' . $variant->id . '/components', [
                'item_id' => $itemCamera->id,
                'quantity' => 2,
            ]);

        $duplicateResponse->assertStatus(422)
            ->assertJsonValidationErrors(['item_id']);

        // Add second component (DVR)
        $comp2Response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/variants/' . $variant->id . '/components', [
                'item_id' => $itemDvr->id,
                'quantity' => 1,
            ]);

        $comp2Response->assertStatus(201);

        // 14. Verify storefront product detail retrieves bundle components
        $storefrontResponse = $this->getJson('/api/storefront/products/' . $product->slug);
        $storefrontResponse->assertStatus(200)
            ->assertJsonCount(2, 'data.variants.0.components');

        // Update component
        $compId = $comp1Response->json('data.id');
        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/components/' . $compId, ['quantity' => 6])
            ->assertStatus(200)
            ->assertJsonPath('data.quantity', 6);

        // Delete component
        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson('/api/components/' . $compId)
            ->assertStatus(200);
    }

    public function test_admin_can_manage_brands_crud(): void
    {
        // 1. Create brand
        $createRes = $this->actingAs($this->admin, 'sanctum')->postJson('/api/brands', [
            'name' => 'Uniview',
            'is_active' => true,
        ]);
        $createRes->assertStatus(201)
            ->assertJsonPath('data.name', 'Uniview')
            ->assertJsonPath('data.slug', 'uniview');

        $brandId = $createRes->json('data.id');

        // 2. List brands
        $this->actingAs($this->admin, 'sanctum')->getJson('/api/brands')
            ->assertStatus(200)
            ->assertJsonFragment(['name' => 'Uniview']);

        // 3. Show brand
        $this->actingAs($this->admin, 'sanctum')->getJson('/api/brands/' . $brandId)
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Uniview');

        // 4. Update brand
        $this->actingAs($this->admin, 'sanctum')->putJson('/api/brands/' . $brandId, [
            'name' => 'Uniview International',
        ])->assertStatus(200)
            ->assertJsonPath('data.name', 'Uniview International');

        // 5. Delete brand
        $this->actingAs($this->admin, 'sanctum')->deleteJson('/api/brands/' . $brandId)
            ->assertStatus(200);

        $this->assertDatabaseMissing('brands', ['id' => $brandId]);
    }

    public function test_admin_can_update_and_delete_product_image_and_feature(): void
    {
        Storage::fake('public');

        $product = Product::create([
            'name' => 'CCTV Test Media',
            'slug' => 'cctv-test-media',
            'is_published' => true,
        ]);

        $file = UploadedFile::fake()->image('test.jpg', 400, 400);
        $imgRes = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/products/' . $product->id . '/images', [
                'image' => $file,
                'alt_text' => 'Initial Alt',
            ]);
        $imgId = $imgRes->json('data.id');

        // Update image
        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/images/' . $imgId, ['alt_text' => 'Updated Alt'])
            ->assertStatus(200)
            ->assertJsonPath('data.alt_text', 'Updated Alt');

        // Delete image
        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson('/api/images/' . $imgId)
            ->assertStatus(200);

        // Feature
        $featRes = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/products/' . $product->id . '/features', [
                'title' => 'Initial Feature',
            ]);
        $featId = $featRes->json('data.id');

        // Update feature
        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/features/' . $featId, ['title' => 'Updated Feature'])
            ->assertStatus(200)
            ->assertJsonPath('data.title', 'Updated Feature');

        // Delete feature
        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson('/api/features/' . $featId)
            ->assertStatus(200);
    }
}
