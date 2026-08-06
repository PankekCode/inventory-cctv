<?php

namespace Tests\Feature;

use App\Models\PhoneVerification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class StorefrontOtpAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_request_otp(): void
    {
        config(['commerce.otp.expose_code' => true]);

        $response = $this->postJson('/api/storefront/otp/send', [
            'phone' => '08123456789',
            'purpose' => 'registration',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'verification_id',
                    'phone',
                    'expires_at',
                    'otp_code',
                ],
            ]);
    }

    public function test_can_verify_otp(): void
    {
        $verification = PhoneVerification::create([
            'public_id' => (string) Str::uuid(),
            'phone_e164' => '+628123456789',
            'purpose' => 'registration',
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(5),
        ]);

        $response = $this->postJson('/api/storefront/otp/verify', [
            'verification_id' => $verification->public_id,
            'code' => '123456',
            'purpose' => 'registration',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.verification_id', $verification->public_id);
    }

    public function test_can_login_with_verified_otp_and_autolink_guest_orders(): void
    {
        $verification = PhoneVerification::create([
            'public_id' => (string) Str::uuid(),
            'phone_e164' => '+628123456789',
            'purpose' => 'login',
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(5),
            'verified_at' => now(),
        ]);

        $response = $this->postJson('/api/storefront/auth/login-otp', [
            'verification_id' => $verification->public_id,
            'name' => 'John Doe',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'user',
                    'token',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'phone_e164' => '+628123456789',
            'name' => 'John Doe',
        ]);
    }
}
