<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\CatalogCategory;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_fetch_homepage_data(): void
    {
        $response = $this->getJson('/api/storefront/home');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'banners',
                    'categories',
                    'featured_products',
                    'brands',
                ],
            ]);
    }

    public function test_can_list_products_and_filter(): void
    {
        $brand = Brand::create([
            'name' => 'EZVIZ',
            'slug' => 'ezviz',
        ]);

        $category = CatalogCategory::create([
            'name' => 'Wireless',
            'slug' => 'wireless',
        ]);

        $product = Product::create([
            'brand_id' => $brand->id,
            'name' => 'CCTV Wireless H6C 3MP',
            'slug' => 'cctv-wireless-h6c-3mp',
            'product_type' => 'wireless',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $product->categories()->attach($category->id);

        $response = $this->getJson('/api/storefront/products?brand=ezviz');

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'CCTV Wireless H6C 3MP']);
    }

    public function test_can_fetch_single_product_detail(): void
    {
        $product = Product::create([
            'name' => 'CCTV Analog Dahua 4 Cam',
            'slug' => 'cctv-analog-dahua-4-cam',
            'product_type' => 'analog',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $response = $this->getJson('/api/storefront/products/' . $product->slug);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'CCTV Analog Dahua 4 Cam');
    }

    public function test_can_fetch_catalog_categories(): void
    {
        CatalogCategory::create([
            'name' => 'IP Camera',
            'slug' => 'ip-camera',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/storefront/categories');

        $response->assertStatus(200)
            ->assertJsonFragment(['slug' => 'ip-camera']);
    }

    public function test_can_fetch_catalog_brands(): void
    {
        Brand::create([
            'name' => 'Hikvision',
            'slug' => 'hikvision',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/storefront/brands');

        $response->assertStatus(200)
            ->assertJsonFragment(['slug' => 'hikvision']);
    }
}
