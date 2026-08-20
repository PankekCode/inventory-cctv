<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class WhatsappService
{
    public function sendOtp(string $phone, string $code, string $purpose): void
    {
        $message = "Kode verifikasi Hablun CCTV Anda: {$code}. Berlaku "
            .config('commerce.otp.ttl_minutes').' menit. Jangan berikan kode ini kepada siapa pun.';

        if (config('commerce.whatsapp.driver') === 'log') {
            if (app()->environment('local')) {
                Log::info('[DEV ONLY] WhatsApp OTP queued to log driver.', [
                    'phone'   => $phone,
                    'purpose' => $purpose,
                    'message' => $message,
                ]);
            } else {
                Log::info('WhatsApp OTP dispatched.', [
                    'phone'   => $phone,
                    'purpose' => $purpose,
                ]);
            }

            return;
        }

        if (config('commerce.whatsapp.driver') === 'webhook') {
            $endpoint = config('commerce.whatsapp.endpoint');

            if (!$endpoint) {
                throw new RuntimeException('WHATSAPP_ENDPOINT belum dikonfigurasi.');
            }

            Http::withToken((string) config('commerce.whatsapp.token'))
                ->acceptJson()
                ->post($endpoint, [
                    'to' => $phone,
                    'message' => $message,
                    'purpose' => $purpose,
                ])
                ->throw();

            return;
        }

        throw new RuntimeException('Driver WhatsApp tidak didukung.');
    }
}
