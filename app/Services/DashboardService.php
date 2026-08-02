<?php

namespace App\Services;

use App\Models\Item;
use App\Models\Category;
use App\Models\Supplier;
use App\Models\StockMovement;
use App\Enums\StockMovementType;
use Carbon\Carbon;

class DashboardService
{
    public function summary(): array
    {
        return [

            'total_items' => Item::count(),

            'total_categories' => Category::count(),

            'total_suppliers' => Supplier::count(),

            'total_stock' => (int) Item::sum('stock'),

            'stock_in_today' => (int) StockMovement::where(
                'type',
                StockMovementType::IN
            )
            ->whereDate(
                'movement_date',
                Carbon::today()
            )
            ->sum('quantity'),


            'stock_out_today' => (int) StockMovement::where(
                'type',
                StockMovementType::OUT
            )
            ->whereDate(
                'movement_date',
                Carbon::today()
            )
            ->sum('quantity'),

        ];
    }
}