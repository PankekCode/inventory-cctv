<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_fetch_company_profile_and_services(): void
    {
        CompanyProfile::create([
            'company_name' => 'Hablun CCTV Indonesia',
            'about' => 'Penyedia solutif kamera keamanan CCTV terbaik.',
        ]);

        Service::create([
            'name' => 'Maintenance & Service CCTV',
            'slug' => 'maintenance-cctv',
            'is_active' => true,
        ]);

        $profileResponse = $this->getJson('/api/storefront/company-profile');
        $profileResponse->assertStatus(200)
            ->assertJsonPath('data.company_name', 'Hablun CCTV Indonesia');

        $servicesResponse = $this->getJson('/api/storefront/services');
        $servicesResponse->assertStatus(200)
            ->assertJsonPath('data.0.slug', 'maintenance-cctv');
    }
}
