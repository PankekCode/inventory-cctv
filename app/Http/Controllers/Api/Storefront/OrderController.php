<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Resources\GuestOrderTrackingResource;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\PhoneNumberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class OrderController extends Controller
{
    public function __construct(
        protected OrderService $orderService,
        protected \App\Services\InvoiceService $invoiceService,
        protected PhoneNumberService $phoneNumbers,
    ) {}

    /**
     * Public order tracking endpoint.
     *
     * Decision 14.3 + 14.5:
     * - Guest orders (user_id = null): require ?phone= query param, normalise to E.164,
     *   compare against order.guest_phone_e164. Return 404 on any mismatch to avoid
     *   confirming that the order code exists.
     * - Authenticated orders: require a valid Sanctum token matching the order owner
     *   or an admin token. Without a valid token, return only minimal public data.
     */
    public function track(Request $request, string $code): JsonResponse
    {
        $order = Order::query()
            ->where('order_code', strtoupper(trim($code)))
            ->with([
                'items',
                'payments',
                'statusHistories',
                'technician',
            ])
            ->first();

        if ($order === null) {
            return response()->json(['message' => 'Pesanan tidak ditemukan.'], 404);
        }

        $user = auth('sanctum')->user();

        // ── Authenticated customer order ──────────────────────────────────────
        if ($order->user_id !== null) {
            // Admin can always see full data.
            if ($user && $user->isAdmin()) {
                return response()->json(['data' => new OrderResource($order)]);
            }

            // Matching authenticated customer sees full data.
            if ($user && $order->user_id === $user->id) {
                return response()->json(['data' => new OrderResource($order)]);
            }

            // No valid token → return minimal public data only (Decision 14.3).
            return response()->json([
                'data' => [
                    'order_code'     => $order->order_code,
                    'status'         => $order->status,
                    'payment_status' => $order->payment_status,
                ],
            ]);
        }

        // ── Guest order ───────────────────────────────────────────────────────
        $phone = $request->query('phone');

        if (!$phone) {
            return response()->json([
                'message' => 'Nomor WhatsApp diperlukan untuk melacak pesanan ini.',
                'errors'  => ['phone' => ['Parameter phone wajib diisi untuk pesanan guest.']],
            ], 422);
        }

        try {
            $phoneE164 = $this->phoneNumbers->normalize((string) $phone);
        } catch (\InvalidArgumentException) {
            // Treat invalid phone as a mismatch — 404 to avoid leaking order existence.
            return response()->json(['message' => 'Pesanan tidak ditemukan.'], 404);
        }

        // Phone mismatch → 404, not 403, to avoid confirming that the order code exists.
        if ($phoneE164 !== $order->guest_phone_e164) {
            return response()->json(['message' => 'Pesanan tidak ditemukan.'], 404);
        }

        return response()->json([
            'data' => new GuestOrderTrackingResource($order),
        ]);
    }

    /**
     * Public invoice download for a tracked guest order.
     *
     * Decision 14.7: requires the same order_code + phone combination as track().
     * Guest invoice cannot be downloaded by order code alone.
     */
    public function trackInvoice(Request $request, string $code): Response|JsonResponse
    {
        $order = Order::query()
            ->where('order_code', strtoupper(trim($code)))
            ->first();

        if ($order === null) {
            return response()->json(['message' => 'Pesanan tidak ditemukan.'], 404);
        }

        $user = auth('sanctum')->user();

        // Authenticated order: must have a valid matching token.
        if ($order->user_id !== null) {
            if ($user && ($user->isAdmin() || $order->user_id === $user->id)) {
                return $this->invoiceService->download($order);
            }

            return response()->json(['message' => 'Autentikasi diperlukan untuk mengakses faktur ini.'], 403);
        }

        // Guest order: require ?phone= parameter (Decision 14.7).
        $phone = $request->query('phone');

        if (!$phone) {
            return response()->json([
                'message' => 'Nomor WhatsApp diperlukan untuk mengakses faktur ini.',
                'errors'  => ['phone' => ['Parameter phone wajib diisi untuk pesanan guest.']],
            ], 422);
        }

        try {
            $phoneE164 = $this->phoneNumbers->normalize((string) $phone);
        } catch (\InvalidArgumentException) {
            return response()->json(['message' => 'Pesanan tidak ditemukan.'], 404);
        }

        if ($phoneE164 !== $order->guest_phone_e164) {
            return response()->json(['message' => 'Pesanan tidak ditemukan.'], 404);
        }

        return $this->invoiceService->download($order);
    }

    /**
     * List authenticated customer's own orders.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Order::query()
            ->where('user_id', $user->id)
            ->with(['items.product', 'payments', 'technician'])
            ->latest();

        if ($status = $request->query('status')) {
            if ($status === 'active') {
                $query->whereNotIn('status', ['completed', 'cancelled']);
            } else {
                $query->where('status', $status);
            }
        }

        return response()->json(
            $query->paginate(15)
        );
    }

    /**
     * Detail view for authenticated customer's own order.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $order = Order::query()
            ->where('user_id', $request->user()->id)
            ->where('id', $id)
            ->with([
                'items.product',
                'items.variant',
                'payments',
                'statusHistories',
                'technician',
            ])
            ->firstOrFail();

        return response()->json([
            'data' => $order,
        ]);
    }

    /**
     * Invoice download for authenticated customer's own order.
     */
    public function invoice(Request $request, int $id): Response
    {
        $order = Order::query()
            ->where('user_id', $request->user()->id)
            ->where('id', $id)
            ->firstOrFail();

        return $this->invoiceService->download($order);
    }

    /**
     * Customer cancels their own order.
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $order = Order::query()
            ->where('user_id', $request->user()->id)
            ->where('id', $id)
            ->firstOrFail();

        $note        = $request->input('note', 'Dibatalkan oleh pelanggan');
        $updatedOrder = $this->orderService->transition($order, 'cancelled', $note, $request->user()->id);

        return response()->json([
            'message' => 'Pesanan berhasil dibatalkan.',
            'data'    => $updatedOrder,
        ]);
    }
}
