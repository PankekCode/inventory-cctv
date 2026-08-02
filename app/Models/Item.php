<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Item extends Model
{
    protected $fillable = [
        'category_id',
        'supplier_id',
        'code',
        'name',
        'model',
        'serial_model',
        'description',
        'purchase_price',
        'stock',
        'minimum_stock',
        'unit'
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    protected function casts(): array
    {
        return [
            'purchase_price' => 'decimal:2',
        ];
    }

    public function price(): HasOne
    {
        return $this->hasOne(ItemPrice::class);
    }


    public function serialNumbers(): HasMany
    {
        return $this->hasMany(ItemSerialNumber::class);
    }
}