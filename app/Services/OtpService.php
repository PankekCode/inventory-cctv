<?php

namespace App\Services;

use App\Models\PhoneVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OtpService
{
    public function __construct(
        private readonly PhoneNumberService $phoneNumbers,
        private readonly WhatsappService $whatsapp,
    ) {
    }

    /**
     * @return array{verification: PhoneVerification, code: string}
     */
    public function request(string $phone, string $purpose, ?Request $request = null): array
    {
        $phone = $this->phoneNumbers->normalize($phone);
        $key = 'otp-send:'.sha1($purpose.'|'.$phone);
        $cooldown = (int) config('commerce.otp.cooldown_seconds');

        if (RateLimiter::tooManyAttempts($key, 1)) {
            throw ValidationException::withMessages([
                'phone' => ["Tunggu {$cooldown} detik sebelum meminta kode baru."],
            ]);
        }

        // Invalidate any previous unverified OTPs for this phone + purpose
        // so only the newest OTP is ever usable (Decision 14.2).
        PhoneVerification::where('phone_e164', $phone)
            ->where('purpose', $purpose)
            ->whereNull('verified_at')
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->update(['expires_at' => now()]);

        RateLimiter::hit($key, $cooldown);

        $length = max(4, min(8, (int) config('commerce.otp.length')));
        $code = str_pad(
            (string) random_int(0, (10 ** $length) - 1),
            $length,
            '0',
            STR_PAD_LEFT
        );

        $verification = PhoneVerification::create([
            'public_id' => (string) Str::uuid(),
            'phone_e164' => $phone,
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes((int) config('commerce.otp.ttl_minutes')),
            'ip_address' => $request?->ip(),
            'user_agent' => Str::limit((string) $request?->userAgent(), 500, ''),
        ]);

        $this->whatsapp->sendOtp($phone, $code, $purpose);

        return compact('verification', 'code');
    }

    public function verify(string $publicId, string $code, string $purpose): PhoneVerification
    {
        $verification = PhoneVerification::where('public_id', $publicId)
            ->where('purpose', $purpose)
            ->latest('id')
            ->first();

        if (!$verification || $verification->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'verification_id' => ['Kode verifikasi sudah kedaluwarsa atau tidak ditemukan.'],
            ]);
        }

        if ($verification->verified_at !== null) {
            throw ValidationException::withMessages([
                'verification_id' => ['Kode verifikasi sudah digunakan.'],
            ]);
        }

        if ($verification->attempts >= (int) config('commerce.otp.max_attempts')) {
            throw ValidationException::withMessages([
                'code' => ['Terlalu banyak percobaan. Silakan minta kode baru.'],
            ]);
        }

        $verification->increment('attempts');
        $verification->refresh();

        if (!Hash::check($code, $verification->code_hash)) {
            throw ValidationException::withMessages([
                'code' => ['Kode verifikasi tidak sesuai.'],
            ]);
        }

        $verification->update(['verified_at' => now()]);

        return $verification->fresh();
    }
}
