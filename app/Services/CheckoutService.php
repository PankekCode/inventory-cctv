<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\InventoryReservation;
use App\Models\Item;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PhoneVerification;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    public function __construct(
        private readonly PhoneNumberService $phoneNumbers,
        private readonly OrderCodeService $orderCodes,
        private readonly PaymentGatewayService $paymentGateway,
    ) {
    }

    public function checkout(array $data, ?User $user = null): Order
    {
        $phone = $user?->phone_e164
            ? $this->phoneNumbers->normalize($user->phone_e164)
            : $this->phoneNumbers->normalize($data['phone']);

        $this->assertPaymentMethod($data['payment_method'], $user);

        if ($user && (!$user->phone_verified_at || $user->phone_e164 !== $phone)) {
            throw ValidationException::withMessages([
                'phone' => ['Nomor akun harus diverifikasi sebelum checkout.'],
            ]);
        }

        return DB::transaction(function () use ($data, $user, $phone): Order {
            if (!$user) {
                $this->consumeGuestVerification(
                    $data['verification_id'] ?? null,
                    $phone,
                );
            }

            $lines = $this->resolveLines($data['items']);
            $subtotal = array_reduce(
                $lines,
                fn (float $total, array $line): float => $total + $line['line_total'],
                0.0
            );

            // PPN must remain zero. Installation price is part of a selected
            // variant, never an extra line added by the client.
            $order = Order::create([
                'public_id' => (string) Str::uuid(),
                'user_id' => $user?->id,
                'unique_order_code' => $this->orderCodes->next(),
                'guest_phone_e164' => $user ? null : $phone,
                'customer_name' => $data['customer_name'],
                'customer_email' => $data['customer_email'] ?? $user?->email,
                'installation_address' => $data['installation_address'],
                'installation_city' => $data['installation_city'] ?? null,
                'installation_date' => $data['installation_date'] ?? null,
                'installation_time_slot' => $data['installation_time_slot'] ?? null,
                'customer_note' => $data['customer_note'] ?? null,
                'status' => 'awaiting_payment',
                'payment_status' => 'pending',
                'payment_method' => $data['payment_method'],
                'subtotal' => $subtotal,
                'installation_fee' => 0,
                'tax_amount' => 0,
                'grand_total' => $subtotal,
                'currency' => 'IDR',
            ]);

            $requirements = [];

            foreach ($lines as $line) {
                /** @var ProductVariant $variant */
                $variant = $line['variant'];
                $orderItem = $order->items()->create([
                    'product_id' => $variant->product_id,
                    'product_variant_id' => $variant->id,
                    'product_name' => $variant->product->name,
                    'variant_name' => $variant->name,
                    'sku' => $variant->sku,
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'line_total' => $line['line_total'],
                    'installation_included' => $variant->installation_included,
                    'configuration' => $variant->configuration,
                ]);

                if (!$variant->is_stock_managed) {
                    continue;
                }

                if ($variant->components->isEmpty()) {
                    throw ValidationException::withMessages([
                        'items' => ["Varian {$variant->name} belum dipetakan ke stok inventori."],
                    ]);
                }

                foreach ($variant->components as $component) {
                    $quantity = $component->quantity * $line['quantity'];
                    $requirements[$component->item_id] = ($requirements[$component->item_id] ?? 0) + $quantity;
                    $line['requirements'][] = [
                        'order_item_id' => $orderItem->id,
                        'item_id' => $component->item_id,
                        'quantity' => $quantity,
                    ];
                }

                foreach ($line['requirements'] ?? [] as $requirement) {
                    InventoryReservation::create([
                        'order_id' => $order->id,
                        'order_item_id' => $requirement['order_item_id'],
                        'item_id' => $requirement['item_id'],
                        'quantity' => $requirement['quantity'],
                        'status' => 'reserved',
                    ]);
                }
            }

            $this->reserveInventory($requirements, $order);

            $intent = $this->paymentGateway->create($order);
            $order->update(['payment_expires_at' => $intent['expires_at']]);

            $payment = Payment::create([
                'order_id' => $order->id,
                'gateway' => $intent['gateway'],
                'method' => $order->payment_method,
                'status' => 'pending',
                'provider_reference' => $intent['provider_reference'],
                'idempotency_key' => (string) Str::uuid(),
                'amount' => $order->grand_total,
                'currency' => $order->currency,
                'payment_url' => $intent['payment_url'],
                'qris_payload' => $intent['qris_payload'],
                'expires_at' => $intent['expires_at'],
            ]);

            $order->statusHistories()->create([
                'status' => 'awaiting_payment',
                'title' => 'Menunggu pembayaran',
                'note' => 'Pesanan dibuat dan stok yang relevan telah direservasi.',
                'occurred_at' => now(),
            ]);

            return $order->fresh()->load([
                'items.product',
                'items.variant',
                'payments',
                'statusHistories',
                'technician',
            ]);
        });
    }

    /**
     * @return array<int, array{variant: ProductVariant,quantity:int,unit_price:float,line_total:float,requirements:array}>
     */
    private function resolveLines(array $items): array
    {
        $quantities = [];

        foreach ($items as $item) {
            $variantId = (int) $item['product_variant_id'];
            $quantities[$variantId] = ($quantities[$variantId] ?? 0) + (int) $item['quantity'];
        }

        $variants = ProductVariant::query()
            ->whereIn('id', array_keys($quantities))
            ->where('is_active', true)
            ->with(['product', 'components.item'])
            ->get()
            ->keyBy('id');

        if ($variants->count() !== count($quantities)) {
            throw ValidationException::withMessages([
                'items' => ['Salah satu varian produk tidak tersedia.'],
            ]);
        }

        return collect($quantities)
            ->map(function (int $quantity, int $variantId) use ($variants): array {
                /** @var ProductVariant $variant */
                $variant = $variants->get($variantId);
                $unitPrice = (float) $variant->price;

                return [
                    'variant' => $variant,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => round($unitPrice * $quantity, 2),
                    'requirements' => [],
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param array<int, int> $requirements
     */
    private function reserveInventory(array $requirements, Order $order): void
    {
        if ($requirements === []) {
            return;
        }

        $items = Item::query()
            ->whereIn('id', array_keys($requirements))
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($requirements as $itemId => $quantity) {
            $item = $items->get($itemId);

            if (!$item || $item->available_stock < $quantity) {
                throw new InsufficientStockException();
            }
        }

        $expiresAt = now()->addMinutes((int) config('commerce.payment.pending_minutes'));

        foreach ($requirements as $itemId => $quantity) {
            $items->get($itemId)->increment('stock_reserved', $quantity);
        }

        $order->reservations()->update(['expires_at' => $expiresAt]);
    }

    private function consumeGuestVerification(?string $verificationId, string $phone): void
    {
        if (!$verificationId) {
            throw ValidationException::withMessages([
                'verification_id' => ['Verifikasi WhatsApp diperlukan untuk checkout sebagai tamu.'],
            ]);
        }

        $verification = PhoneVerification::query()
            ->where('public_id', $verificationId)
            ->where('purpose', 'guest_checkout')
            ->lockForUpdate()
            ->first();

        if (
            !$verification
            || $verification->phone_e164 !== $phone
            || !$verification->isUsable()
        ) {
            throw ValidationException::withMessages([
                'verification_id' => ['Verifikasi WhatsApp tidak valid atau sudah kedaluwarsa.'],
            ]);
        }

        $verification->update(['consumed_at' => now()]);
    }

    private function assertPaymentMethod(string $method, ?User $user): void
    {
        if (!$user && $method !== 'qris') {
            throw ValidationException::withMessages([
                'payment_method' => ['Checkout tanpa login hanya mendukung QRIS.'],
            ]);
        }

        if (!in_array($method, config('commerce.payment.allowed_methods'), true)) {
            throw ValidationException::withMessages([
                'payment_method' => ['Metode pembayaran tidak tersedia.'],
            ]);
        }
    }
}
