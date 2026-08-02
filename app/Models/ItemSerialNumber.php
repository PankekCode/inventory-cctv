<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemSerialNumber extends Model
{

    protected $fillable = [
        'item_id',
        'serial_number',
        'status',
    ];


    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

}