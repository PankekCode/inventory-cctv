<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemPrice extends Model
{
    protected $fillable = [
        'item_id',
        'price',
    ];


    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
        ];
    }


    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}