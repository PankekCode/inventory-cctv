<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhoneVerification extends Model
{
    protected $fillable = [
        'public_id',
        'phone_e164',
        'purpose',
        'code_hash',
        'attempts',
        'expires_at',
        'verified_at',
        'consumed_at',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'consumed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function isUsable(): bool
    {
        return $this->verified_at !== null
            && $this->consumed_at === null
            && $this->expires_at->isFuture();
    }
}
