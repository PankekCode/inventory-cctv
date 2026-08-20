<?php

namespace Tests\Feature;

use App\Models\PhoneVerification;
use App\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Tests for OTP service behaviour after Decision 14.2:
 * requesting a new OTP must invalidate all previous unverified OTPs
 * for the same phone + purpose.
 */
class OtpServiceTest extends TestCase
{
    use RefreshDatabase;

    private OtpService $otp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->otp = app(OtpService::class);
    }

    public function test_new_otp_request_soft_expires_previous_unverified_otp(): void
    {
        $phone   = '081234567890';
        $purpose = 'guest_checkout';

        // First OTP request
        $first = $this->otp->request($phone, $purpose);

        $this->assertDatabaseHas('phone_verifications', [
            'public_id' => $first['verification']->public_id,
        ]);

        // Advance past the rate-limit cooldown so the second request is allowed
        $this->travel(65)->seconds();

        // Second OTP request for the same phone + purpose
        $second = $this->otp->request($phone, $purpose);

        // The first verification must now be expired (expires_at <= now)
        $firstRecord = PhoneVerification::where('public_id', $first['verification']->public_id)->first();
        $this->assertNotNull($firstRecord);
        $this->assertTrue(
            $firstRecord->expires_at->isPast(),
            'Previous unverified OTP was not soft-expired after new request.'
        );

        // The second verification is still valid (expires_at in the future)
        $secondRecord = PhoneVerification::where('public_id', $second['verification']->public_id)->first();
        $this->assertNotNull($secondRecord);
        $this->assertTrue(
            $secondRecord->expires_at->isFuture(),
            'Newest OTP should still be valid.'
        );
    }

    public function test_new_otp_does_not_invalidate_already_verified_otp(): void
    {
        // Simulate an already-verified OTP (verified_at set) for a different purpose
        $existing = PhoneVerification::create([
            'public_id'   => (string) \Illuminate\Support\Str::uuid(),
            'phone_e164'  => '+6281234567890',
            'purpose'     => 'login',
            'code_hash'   => Hash::make('123456'),
            'expires_at'  => now()->addMinutes(5),
            'verified_at' => now(),  // ← already verified
        ]);

        $this->travel(65)->seconds();

        // New OTP for a different purpose — should NOT touch the verified login record
        $this->otp->request('081234567890', 'guest_checkout');

        // The verified login record must remain intact
        $existing->refresh();
        $this->assertTrue(
            $existing->expires_at->isFuture(),
            'A verified OTP for a different purpose should not be expired by a new request.'
        );
    }

    public function test_otp_send_endpoint_returns_verification_id_and_no_code(): void
    {
        $response = $this->postJson('/api/storefront/otp/send', [
            'phone'   => '081234567890',
            'purpose' => 'guest_checkout',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['verification_id', 'phone', 'expires_at']]);

        // OTP code must NOT be in data response (unless OTP_EXPOSE_CODE=true)
        $this->assertArrayNotHasKey('code', $response->json('data'));
    }

    public function test_otp_verify_endpoint_sets_verified_at(): void
    {
        // Create a known verification record
        $verification = PhoneVerification::create([
            'public_id'  => (string) \Illuminate\Support\Str::uuid(),
            'phone_e164' => '+6281234567890',
            'purpose'    => 'guest_checkout',
            'code_hash'  => Hash::make('123456'),
            'expires_at' => now()->addMinutes(5),
        ]);

        $response = $this->postJson('/api/storefront/otp/verify', [
            'verification_id' => $verification->public_id,
            'code'            => '123456',
            'purpose'         => 'guest_checkout',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['verification_id', 'phone', 'verified_at']]);

        $verification->refresh();
        $this->assertNotNull($verification->verified_at);
        $this->assertNull($verification->consumed_at);
    }
}
