<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\ChangePasswordRequest;
use App\Http\Requests\Storefront\PhoneAuthRequest;
use App\Http\Requests\Storefront\UpdateProfileRequest;
use App\Models\PhoneVerification;
use App\Services\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CustomerAuthController extends Controller
{
    public function __construct(
        protected CustomerService $customerService
    ) {}

    public function loginWithOtp(PhoneAuthRequest $request): JsonResponse
    {
        $verification = PhoneVerification::where('public_id', $request->validated('verification_id'))
            ->whereNotNull('verified_at')
            ->first();

        if (!$verification || !$verification->isUsable()) {
            throw ValidationException::withMessages([
                'verification_id' => ['Verifikasi OTP tidak valid atau sudah kedaluwarsa.'],
            ]);
        }

        $result = $this->customerService->authenticateByVerification(
            $verification,
            $request->validated('name'),
            $request->validated('password')
        );

        return response()->json([
            'message' => 'Autentikasi berhasil.',
            'data' => $result,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('addresses');

        return response()->json([
            'data' => $user,
        ]);
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $this->customerService->updateProfile(
            $request->user(),
            $request->validated()
        );

        return response()->json([
            'message' => 'Profil berhasil diperbarui.',
            'data' => $user,
        ]);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $this->customerService->changePassword(
            $request->user(),
            $request->validated('current_password'),
            $request->validated('new_password')
        );

        return response()->json([
            'message' => 'Kata sandi berhasil diperbarui.',
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout berhasil.',
        ]);
    }
}
