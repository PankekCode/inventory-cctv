<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\JsonResponse;

class AuthService
{
    public function login(array $credentials): array
    {
        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email atau password salah.'],
            ]);
        }

        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Akun ini tidak aktif.'],
            ]);
        }

        $abilities = $user->isAdmin() ? ['admin', 'customer'] : ['customer'];
        $token = $user->createToken('inventory-api', $abilities)->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
    }
}
