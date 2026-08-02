<?php

namespace App\Services;

use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Collection;

class StockMovementService
{
    public function index(): Collection
    {
        return StockMovement::with([
            'item',
            'user',
        ])
        ->latest()
        ->get();
    }


    public function show(StockMovement $stockMovement): StockMovement
    {
        return $stockMovement->load([
            'item',
            'user',
        ]);
    }
}