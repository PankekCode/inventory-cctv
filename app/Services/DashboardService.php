<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Models\Category;
use App\Models\Item;
use App\Models\Order;
use App\Models\StockMovement;
use App\Models\Supplier;
use Carbon\Carbon;

class DashboardService
{
    public function summary(array $filters = []): array
    {
        [$startDate, $endDate, $periodType] = $this->resolveDateRange($filters);

        $salesQuery = Order::query()->whereBetween('created_at', [$startDate, $endDate]);

        $totalSales = (float) Order::query()
            ->where('payment_status', 'paid')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('grand_total');

        $totalOrders = (clone $salesQuery)->count();
        $completedOrders = (clone $salesQuery)->where('status', 'completed')->count();
        $pendingOrders = (clone $salesQuery)->whereIn('status', [
            'awaiting_payment',
            'order_received',
            'technician_scheduled',
            'technician_en_route',
            'installation_in_progress',
        ])->count();
        $cancelledOrders = (clone $salesQuery)->where('status', 'cancelled')->count();

        $salesByDate = Order::query()
            ->where('payment_status', 'paid')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count, SUM(grand_total) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => (string) $row->date,
                'count' => (int) $row->count,
                'total' => (float) $row->total,
            ]);

        $stockInQuery = StockMovement::query()
            ->where('type', StockMovementType::IN)
            ->whereBetween('movement_date', [$startDate->toDateString(), $endDate->toDateString()]);

        $stockOutQuery = StockMovement::query()
            ->where('type', StockMovementType::OUT)
            ->whereBetween('movement_date', [$startDate->toDateString(), $endDate->toDateString()]);

        return [
            'period' => [
                'type' => $periodType,
                'from' => $startDate->toDateTimeString(),
                'to' => $endDate->toDateTimeString(),
            ],
            'sales' => [
                'total_sales' => $totalSales,
                'total_orders' => $totalOrders,
                'completed_orders' => $completedOrders,
                'pending_orders' => $pendingOrders,
                'cancelled_orders' => $cancelledOrders,
                'sales_by_date' => $salesByDate,
            ],
            'inventory' => [
                'total_items' => Item::count(),
                'total_categories' => Category::count(),
                'total_suppliers' => Supplier::count(),
                'total_stock' => (int) Item::sum('stock'),
                'total_stock_reserved' => (int) Item::sum('stock_reserved'),
                'total_available_stock' => (int) (Item::query()
                    ->selectRaw('SUM(CASE WHEN stock > stock_reserved THEN stock - stock_reserved ELSE 0 END) as avail')
                    ->value('avail') ?? 0),
                'stock_in_count' => (int) (clone $stockInQuery)->sum('quantity'),
                'stock_in_value' => (float) ((clone $stockInQuery)->selectRaw('SUM(quantity * price) as val')->value('val') ?? 0),
                'stock_out_count' => (int) (clone $stockOutQuery)->sum('quantity'),
                'stock_out_value' => (float) ((clone $stockOutQuery)->selectRaw('SUM(quantity * price) as val')->value('val') ?? 0),
            ],
        ];
    }

    private function resolveDateRange(array $filters): array
    {
        $period = $filters['period'] ?? 'this_month';

        if ($period === 'today') {
            return [Carbon::today()->startOfDay(), Carbon::today()->endOfDay(), 'today'];
        }

        if ($period === 'this_week') {
            return [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek(), 'this_week'];
        }

        if ($period === 'custom' && !empty($filters['date_from']) && !empty($filters['date_to'])) {
            $from = Carbon::parse($filters['date_from'])->startOfDay();
            $to = Carbon::parse($filters['date_to'])->endOfDay();

            return [$from, $to, 'custom'];
        }

        return [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth(), 'this_month'];
    }
}