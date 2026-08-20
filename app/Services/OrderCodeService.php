<?php

namespace App\Services;

use App\Models\Order;
use RuntimeException;

class OrderCodeService
{
    /**
     * Alphabet for the random suffix.
     * Excludes visually ambiguous characters: 0 (zero), O (oh), 1 (one), I (eye).
     * 32 characters → 32^6 ≈ 1.07 billion combinations per calendar day.
     */
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    private const SUFFIX_LENGTH = 6;

    private const MAX_ATTEMPTS = 5;

    /**
     * Generate a unique customer-facing order code.
     *
     * Format: {PREFIX}-{YYMMDD}-{XXXXXX}
     * Example: HBL-260820-A7K3P9
     *
     * The random suffix is generated using PHP's cryptographically
     * secure random_bytes() function. The counter-based approach has
     * been replaced per Decision 14.5.
     */
    public function next(): string
    {
        $prefix  = (string) config('commerce.order_prefix', 'HBL');
        $datePart = now()->format('ymd'); // YYMMDD

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $suffix = $this->generateSuffix();
            $code   = "{$prefix}-{$datePart}-{$suffix}";

            if (!Order::where('order_code', $code)->exists()) {
                return $code;
            }
        }

        // Reaching here means 5 consecutive collisions occurred, which
        // at 1.07 billion combinations per day is astronomically improbable.
        throw new RuntimeException(
            'Gagal membuat kode pesanan unik setelah ' . self::MAX_ATTEMPTS . ' percobaan. ' .
            'Silakan coba kembali.'
        );
    }

    /**
     * Generate a cryptographically secure random suffix of SUFFIX_LENGTH characters
     * drawn from ALPHABET.
     */
    private function generateSuffix(): string
    {
        $alphabetLength = strlen(self::ALPHABET);
        $suffix         = '';

        // random_bytes() provides cryptographically secure randomness.
        // We need SUFFIX_LENGTH bytes. Each byte is mapped to an alphabet
        // character using modulo. The alphabet size (32) is a power of 2,
        // so there is zero modulo bias.
        $bytes = random_bytes(self::SUFFIX_LENGTH);

        for ($i = 0; $i < self::SUFFIX_LENGTH; $i++) {
            $suffix .= self::ALPHABET[ord($bytes[$i]) % $alphabetLength];
        }

        return $suffix;
    }
}
