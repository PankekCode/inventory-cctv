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

    public function test_authenticated_customer_can_get_profile_me(): void
    {
        $user = User::create([
            'name' => 'Jane Customer',
            'email' => 'jane@example.com',
            'phone_e164' => '+6281987654321',
            'password' => bcrypt('password123'),
            'role' => 'customer',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/storefront/auth/me');

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Jane Customer')
            ->assertJsonPath('data.email', 'jane@example.com');
    }

    public function test_authenticated_customer_can_update_profile(): void
    {
        $user = User::create([
            'name' => 'Jane Old Name',
            'email' => 'jane.old@example.com',
            'phone_e164' => '+6281987654322',
            'password' => bcrypt('password123'),
            'role' => 'customer',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user, 'sanctum')->putJson('/api/storefront/auth/profile', [
            'name' => 'Jane New Name',
            'email' => 'jane.new@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Jane New Name')
            ->assertJsonPath('data.email', 'jane.new@example.com');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Jane New Name',
            'email' => 'jane.new@example.com',
        ]);
    }

    public function test_authenticated_customer_can_change_password_and_invalid_current_password_is_rejected(): void
    {
        $user = User::create([
            'name' => 'Jane Password Test',
            'email' => 'jane.pw@example.com',
            'phone_e164' => '+6281987654323',
            'password' => bcrypt('OldPassword123'),
            'role' => 'customer',
            'is_active' => true,
        ]);

        // Wrong current password -> 422
        $invalidResponse = $this->actingAs($user, 'sanctum')->putJson('/api/storefront/auth/change-password', [
            'current_password' => 'WrongPassword',
            'new_password' => 'NewSecurePassword123',
            'new_password_confirmation' => 'NewSecurePassword123',
        ]);

        $invalidResponse->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);

        // Correct password change -> 200
        $validResponse = $this->actingAs($user, 'sanctum')->putJson('/api/storefront/auth/change-password', [
            'current_password' => 'OldPassword123',
            'new_password' => 'NewSecurePassword123',
            'new_password_confirmation' => 'NewSecurePassword123',
        ]);

        $validResponse->assertStatus(200)
            ->assertJsonPath('message', 'Kata sandi berhasil diperbarui.');

        $this->assertTrue(Hash::check('NewSecurePassword123', $user->fresh()->password));
    }

    public function test_authenticated_customer_can_logout(): void
    {
        $user = User::create([
            'name' => 'Jane Logout Test',
            'email' => 'jane.logout@example.com',
            'phone_e164' => '+6281987654324',
            'password' => bcrypt('password123'),
            'role' => 'customer',
            'is_active' => true,
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/storefront/auth/logout');

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Logout berhasil.');

        $this->assertCount(0, $user->fresh()->tokens);
    }
}
