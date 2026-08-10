<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService
    ) {}

    public function webhook(Request $request): JsonResponse
    {
        $request->validate([
            'gateway' => ['nullable', 'string'],
            'provider_reference' => ['required', 'string'],
            'status' => ['required', 'string', 'in:paid,failed'],
            'event_id' => ['required_if:status,paid', 'string'],
            'amount' => ['required_if:status,paid', 'numeric'],
        ]);

        $gateway = $request->input('gateway', config('commerce.payment.gateway_name', 'sandbox'));
        $status = $request->input('status');

        if ($status === 'paid') {
            $payment = $this->paymentService->markPaid($gateway, $request->all());

            return response()->json([
                'message' => 'Pembayaran berhasil dikonfirmasi.',
                'data' => [
                    'payment_id' => $payment->id,
                    'provider_reference' => $payment->provider_reference,
                    'status' => $payment->status,
                    'order_code' => $payment->order->unique_order_code,
                ],
            ]);
        }

        $payment = $this->paymentService->markFailed($gateway, $request->all());

        return response()->json([
            'message' => 'Pembayaran gagal dikonfirmasi.',
            'data' => [
                'payment_id' => $payment->id,
                'provider_reference' => $payment->provider_reference,
                'status' => $payment->status,
            ],
        ]);
    }

    public function show(Request $request, string $reference): JsonResponse
    {
        $payment = Payment::query()
            ->where('provider_reference', $reference)
            ->with(['order.items', 'order.statusHistories'])
            ->firstOrFail();

        $order = $payment->order;
        $user = $request->user('sanctum');

        // Authorization check
        if ($user) {
            if (!$user->isAdmin() && $order->user_id !== $user->id) {
                return response()->json(['message' => 'Anda tidak memiliki akses ke data pembayaran ini.'], 403);
            }
        } else {
            // Guest verification requirement: Must provide matching unique order code via query
            $orderCode = $request->query('order_code');
            if (!$orderCode || strtoupper(trim($orderCode)) !== $order->unique_order_code) {
                return response()->json(['message' => 'Akses pembayaran guest membutuhkan kode pesanan yang valid.'], 403);
            }
        }

        return response()->json([
            'data' => [
                'id' => $payment->id,
                'gateway' => $payment->gateway,
                'provider_reference' => $payment->provider_reference,
                'amount' => (string) $payment->amount,
                'status' => $payment->status,
                'payment_url' => $payment->payment_url,
                'qris_payload' => $payment->qris_payload,
                'expires_at' => $payment->expires_at,
                'paid_at' => $payment->paid_at,
                'order' => [
                    'unique_order_code' => $order->unique_order_code,
                    'customer_name' => $order->customer_name,
                    'grand_total' => (string) $order->grand_total,
                    'status' => $order->status,
                    'payment_status' => $order->payment_status,
                ],
            ],
        ]);
    }
}
