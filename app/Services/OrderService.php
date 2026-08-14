<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    /**
     * @var array<string, array<int, string>>
     */
    private const TRANSITIONS = [
        'awaiting_payment'       => ['cancelled'],
        'order_received'         => ['installation_in_progress', 'cancelled'],
        'installation_in_progress' => ['completed'],
    ];

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
                'installation_in_progress' => 'Proses pemasangan',
                'completed'                => 'Pemasangan selesai',
                'cancelled'                => 'Pesanan dibatalkan',
                default                    => 'Status pesanan diperbarui',
            };

            if ($status === 'cancelled') {
                app(PaymentService::class)->releaseReservations($order);
            }

            $order->update([
                'status'       => $status,
                'cancelled_at' => $status === 'cancelled' ? now() : $order->cancelled_at,
            ]);
            $order->statusHistories()->create([
                'actor_id'   => $actorId,
                'status'     => $status,
                'title'      => $title,
                'note'       => $note,
                'occurred_at' => now(),
            ]);

            return $order->fresh()->load('items', 'payments', 'statusHistories');
        });
    }
}
