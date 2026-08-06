<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Technician;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    /**
     * @var array<string, array<int, string>>
     */
    private const TRANSITIONS = [
        'awaiting_payment' => ['cancelled'],
        'order_received' => ['technician_scheduled', 'cancelled'],
        'technician_scheduled' => ['technician_en_route', 'cancelled'],
        'technician_en_route' => ['installation_in_progress'],
        'installation_in_progress' => ['completed'],
    ];

    public function assignTechnician(Order $order, Technician $technician, ?int $actorId = null): Order
    {
        return DB::transaction(function () use ($order, $technician, $actorId): Order {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->payment_status !== 'paid') {
                throw ValidationException::withMessages([
                    'order' => ['Teknisi hanya dapat ditugaskan untuk pesanan yang sudah dibayar.'],
                ]);
            }

            $order->update([
                'technician_id' => $technician->id,
                'status' => 'technician_scheduled',
            ]);
            $order->statusHistories()->create([
                'actor_id' => $actorId,
                'status' => 'technician_scheduled',
                'title' => 'Penjadwalan teknisi',
                'note' => "Teknisi {$technician->name} telah ditugaskan.",
                'occurred_at' => now(),
            ]);

            return $order->fresh()->load('technician', 'items', 'payments', 'statusHistories');
        });
    }

    public function transition(Order $order, string $status, ?string $note, ?int $actorId = null): Order
    {
        return DB::transaction(function () use ($order, $status, $note, $actorId): Order {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $allowed = self::TRANSITIONS[$order->status] ?? [];

            if (!in_array($status, $allowed, true)) {
                throw ValidationException::withMessages([
                    'status' => ['Perubahan status pesanan tidak diizinkan.'],
                ]);
            }

            $title = match ($status) {
                'technician_en_route' => 'Teknisi dalam perjalanan',
                'installation_in_progress' => 'Proses pemasangan',
                'completed' => 'Pemasangan selesai',
                'cancelled' => 'Pesanan dibatalkan',
                default => 'Status pesanan diperbarui',
            };

            $order->update([
                'status' => $status,
                'cancelled_at' => $status === 'cancelled' ? now() : $order->cancelled_at,
            ]);
            $order->statusHistories()->create([
                'actor_id' => $actorId,
                'status' => $status,
                'title' => $title,
                'note' => $note,
                'occurred_at' => now(),
            ]);

            return $order->fresh()->load('technician', 'items', 'payments', 'statusHistories');
        });
    }
}
