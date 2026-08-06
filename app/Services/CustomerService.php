<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PhoneVerification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerService
{
    public function authenticateByVerification(
        PhoneVerification $verification,
        ?string $name = null,
        ?string $password = null,
    ): array {
        return DB::transaction(function () use ($verification, $name, $password): array {
            $user = User::where('phone_e164', $verification->phone_e164)
                ->lockForUpdate()
                ->first();

            if (!$user) {
                $user = User::create([
                    'name' => $name ?: 'Pelanggan Hablun',
                    'phone_e164' => $verification->phone_e164,
                    'phone_verified_at' => now(),
                    'password' => $password ?: Str::password(32),
                    'role' => 'customer',
                    'is_active' => true,
                ]);
            } else {
                $changes = [
                    'phone_verified_at' => now(),
                ];

                if ($name) {
                    $changes['name'] = $name;
                }

                if ($password) {
                    $changes['password'] = $password;
                }

                $user->update($changes);
            }

            Order::whereNull('user_id')
                ->where('guest_phone_e164', $verification->phone_e164)
                ->update(['user_id' => $user->id]);

            $token = $user->createToken('customer-api', ['customer'])->plainTextToken;

            return [
                'user' => $user->fresh(),
                'token' => $token,
            ];
        });
    }

    public function updateProfile(User $user, array $data): User
    {
        $user->update($data);

        return $user->fresh();
    }

    public function changePassword(User $user, string $currentPassword, string $newPassword): void
    {
        if (!Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Kata sandi lama tidak sesuai.'],
            ]);
        }

        $user->update(['password' => $newPassword]);
    }
}
