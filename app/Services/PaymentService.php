<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Models\InventoryReservation;
use App\Models\Item;
use App\Models\ItemSerialNumber;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentWebhookEvent;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function markPaid(string $gateway, array $payload): Payment
    {
        return DB::transaction(function () use ($gateway, $payload): Payment {
            $eventId = (string) ($payload['event_id'] ?? '');
            $reference = (string) ($payload['provider_reference'] ?? '');
            $amount = $payload['amount'] ?? null;

            if ($eventId === '' || $reference === '' || $amount === null) {
                throw ValidationException::withMessages([
                    'payload' => ['event_id, provider_reference, dan amount wajib ada.'],
                ]);
            }

            $existingEvent = PaymentWebhookEvent::where('event_id', $eventId)->first();

            if ($existingEvent) {
                return $existingEvent->payment()->firstOrFail();
            }

            $payment = Payment::query()
                ->where('gateway', $gateway)
                ->where('provider_reference', $reference)
                ->lockForUpdate()
                ->firstOrFail();

            if (abs((float) $payment->amount - (float) $amount) > 0.009) {
                throw ValidationException::withMessages([
                    'amount' => ['Nominal callback tidak sama dengan total pesanan.'],
                ]);
            }

            $event = PaymentWebhookEvent::create([
                'gateway' => $gateway,
                'event_id' => $eventId,
                'payment_id' => $payment->id,
                'payload' => $payload,
            ]);

            if ($payment->status === 'paid') {
                $event->update(['processed_at' => now()]);

                return $payment;
            }

            if ($payment->expires_at && $payment->expires_at->isPast()) {
                throw ValidationException::withMessages([
                    'payment' => ['Pembayaran telah kedaluwarsa.'],
                ]);
            }

            $order = Order::query()
                ->whereKey($payment->order_id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->commitReservations($order);

            $payment->update([
                'status' => 'paid',
                'paid_at' => now(),
                'raw_payload' => $payload,
            ]);

            $order->update([
                'status' => 'order_received',
                'payment_status' => 'paid',
                'paid_at' => now(),
            ]);

            $order->statusHistories()->create([
                'actor_id' => $order->user_id,
                'status' => 'order_received',
                'title' => 'Pesanan diterima',
                'note' => 'Pembayaran telah terkonfirmasi.',
                'occurred_at' => now(),
            ]);

            $event->update(['processed_at' => now()]);

            return $payment->fresh()->load('order.items', 'order.statusHistories');
        });
    }

    public function releaseExpired(): int
    {
        $orders = Order::query()
            ->where('payment_status', 'pending')
            ->whereNotNull('payment_expires_at')
            ->where('payment_expires_at', '<', now())
            ->get();

        foreach ($orders as $order) {
            DB::transaction(function () use ($order): void {
                $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

                if ($order->payment_status !== 'pending') {
                    return;
                }

                $this->releaseReservations($order);
                $order->update([
                    'status' => 'payment_expired',
                    'payment_status' => 'expired',
                ]);
                $order->payments()->where('status', 'pending')->update(['status' => 'expired']);
                $order->statusHistories()->create([
                    'status' => 'payment_expired',
                    'title' => 'Pembayaran kedaluwarsa',
                    'note' => 'Stok yang direservasi telah dilepaskan.',
                    'occurred_at' => now(),
                ]);
            });
        }

        return $orders->count();
    }

    public function cancel(Order $order, ?int $actorId = null): Order
    {
        return DB::transaction(function () use ($order, $actorId): Order {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (!in_array($order->status, ['awaiting_payment', 'payment_expired', 'order_received', 'technician_scheduled'], true)) {
                throw ValidationException::withMessages([
                    'order' => ['Pesanan tidak dapat dibatalkan pada status saat ini.'],
                ]);
            }

            $this->releaseReservations($order);
            $order->update([
                'status' => 'cancelled',
                'payment_status' => $order->payment_status === 'paid' ? 'paid' : 'cancelled',
                'cancelled_at' => now(),
            ]);
            $order->payments()->where('status', 'pending')->update(['status' => 'cancelled']);
            $order->statusHistories()->create([
                'actor_id' => $actorId,
                'status' => 'cancelled',
                'title' => 'Pesanan dibatalkan',
                'occurred_at' => now(),
            ]);

            return $order->fresh()->load('items', 'payments', 'statusHistories');
        });
    }

    private function commitReservations(Order $order): void
    {
        $reservations = InventoryReservation::query()
            ->where('order_id', $order->id)
            ->where('status', 'reserved')
            ->orderBy('item_id')
            ->lockForUpdate()
            ->get();

        foreach ($reservations as $reservation) {
            $item = Item::query()->whereKey($reservation->item_id)->lockForUpdate()->firstOrFail();

            if ($item->stock < $reservation->quantity || $item->stock_reserved < $reservation->quantity) {
                throw new \App\Exceptions\InsufficientStockException();
            }

            $item->decrement('stock', $reservation->quantity);
            $item->decrement('stock_reserved', $reservation->quantity);

            StockMovement::create([
                'item_id' => $item->id,
                'user_id' => $order->user_id,
                'type' => StockMovementType::OUT,
                'quantity' => $reservation->quantity,
                'price' => $item->purchase_price,
                'movement_date' => now()->toDateString(),
                'reference' => $order->unique_order_code,
                'note' => 'Pengeluaran stok otomatis untuk pesanan terbayar.',
            ]);

            ItemSerialNumber::query()
                ->where('item_id', $item->id)
                ->where('status', 'AVAILABLE')
                ->limit($reservation->quantity)
                ->update(['status' => 'SOLD']);

            $reservation->update([
                'status' => 'committed',
                'committed_at' => now(),
            ]);
        }
    }

    public function releaseReservations(Order $order): void
    {
        $reservations = InventoryReservation::query()
            ->where('order_id', $order->id)
            ->whereIn('status', ['reserved', 'committed'])
            ->orderBy('item_id')
            ->lockForUpdate()
            ->get();

        foreach ($reservations as $reservation) {
            $item = Item::query()->whereKey($reservation->item_id)->lockForUpdate()->firstOrFail();

            if ($reservation->status === 'reserved') {
                $item->decrement('stock_reserved', min($item->stock_reserved, $reservation->quantity));
            } elseif ($reservation->status === 'committed') {
                $item->increment('stock', $reservation->quantity);
                StockMovement::create([
                    'item_id' => $item->id,
                    'user_id' => $order->user_id,
                    'type' => StockMovementType::IN,
                    'quantity' => $reservation->quantity,
                    'price' => $item->purchase_price,
                    'movement_date' => now()->toDateString(),
                    'reference' => $order->unique_order_code,
                    'note' => 'Pengembalian stok otomatis untuk pesanan dibatalkan.',
                ]);
            }

            $reservation->update([
                'status' => 'released',
                'released_at' => now(),
            ]);
        }
    }
}
