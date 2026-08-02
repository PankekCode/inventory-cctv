<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Enums\StockMovementType;

class StockMovement extends Model
{
    protected $fillable = [
        'item_id',
        'user_id',
        'type',
        'quantity',
        'price',
        'movement_date',
        'reference',
        'note'
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

   protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'movement_date' => 'date',
            'price' => 'decimal:2',
        ];
    }
}