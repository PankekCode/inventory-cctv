<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class PhoneNumberService
{
    /**
     * Store all customer phones in an E.164-like format so guest orders can
     * reliably be linked to an account created later with the same number.
     */
    public function normalize(string $phone): string
    {
        $clean = preg_replace('/[^0-9+]/', '', trim($phone));

        if ($clean === null || $clean === '') {
            throw ValidationException::withMessages([
                'phone' => ['Nomor WhatsApp wajib diisi.'],
            ]);
        }

        $digits = ltrim($clean, '+');

        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        }

        if (!str_starts_with($digits, '62')) {
            throw ValidationException::withMessages([
                'phone' => ['Gunakan nomor WhatsApp Indonesia yang valid.'],
            ]);
        }

        $normalized = '+'.$digits;

        if (!preg_match('/^\\+62[0-9]{8,13}$/', $normalized)) {
            throw ValidationException::withMessages([
                'phone' => ['Nomor WhatsApp tidak valid.'],
            ]);
        }

        return $normalized;
    }
}
