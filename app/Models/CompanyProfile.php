<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyProfile extends Model
{
    protected $fillable = [
        'company_name',
        'about',
        'vision',
        'mission',
        'statistics',
        'contacts',
        'social_links',
    ];

    protected function casts(): array
    {
        return [
            'statistics' => 'array',
            'contacts' => 'array',
            'social_links' => 'array',
        ];
    }
}
