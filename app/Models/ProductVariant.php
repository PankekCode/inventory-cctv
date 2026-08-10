<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    protected $fillable = [
        'product_id',
        'sku',
        'name',
        'variant_type',
        'camera_count',
        'price',
        'installation_included',
        'is_stock_managed',
        'warranty_months',
        'configuration',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'installation_included' => 'boolean',
            'is_stock_managed' => 'boolean',
            'configuration' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function components(): HasMany
    {
        return $this->hasMany(ProductVariantComponent::class);
    }

    public function priceTiers(): HasMany
    {
        return $this->hasMany(ProductVariantPrice::class);
    }

    public function priceForQuantity(int $quantity): string
    {
        return (string) $this->price;
    }
}
