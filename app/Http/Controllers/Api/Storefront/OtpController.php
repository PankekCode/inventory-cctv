<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\SendOtpRequest;
use App\Http\Requests\Storefront\VerifyOtpRequest;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;

class OtpController extends Controller
{
    public function __construct(
        protected OtpService $otpService
    ) {}

    public function send(SendOtpRequest $request): JsonResponse
    {
        $result = $this->otpService->request(
            $request->validated('phone'),
            $request->validated('purpose'),
            $request
        );

        $data = [
            'verification_id' => $result['verification']->public_id,
            'phone' => $result['verification']->phone_e164,
            'expires_at' => $result['verification']->expires_at,
        ];

        if (config('commerce.otp.expose_code')) {
            $data['otp_code'] = $result['code'];
        }

        return response()->json([
            'message' => 'Kode OTP berhasil dikirim via WhatsApp.',
            'data' => $data,
        ]);
    }

    public function verify(VerifyOtpRequest $request): JsonResponse
    {
        $verification = $this->otpService->verify(
            $request->validated('verification_id'),
            $request->validated('code'),
            $request->validated('purpose')
        );

        return response()->json([
            'message' => 'Nomor WhatsApp berhasil diverifikasi.',
            'data' => [
                'verification_id' => $verification->public_id,
                'phone' => $verification->phone_e164,
                'verified_at' => $verification->verified_at,
            ],
        ]);
    }
}
