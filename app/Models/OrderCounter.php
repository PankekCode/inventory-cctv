<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderCounter extends Model
{
    protected $fillable = [
        'year',
        'last_number',
    ];
}
