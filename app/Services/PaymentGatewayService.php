<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Str;
use RuntimeException;

use App\Contracts\PaymentGatewayInterface;

class PaymentGatewayService implements PaymentGatewayInterface
{
    /**
     * Returns a provider-neutral payment intent. The sandbox driver is a
     * deliberate local adapter; a real provider can implement this contract
     * after merchant credentials and callback requirements are supplied.
     *
     * @return array{gateway:string,provider_reference:string,payment_url:?string,qris_payload:?string,expires_at:\Carbon\CarbonInterface}
     */
    public function create(Order $order): array
    {
        $driver = (string) config('commerce.payment.driver');
        $expiresAt = now()->addMinutes((int) config('commerce.payment.pending_minutes'));

        if ($driver === 'sandbox') {
            $reference = 'SBX-'.str_replace('-', '', $order->order_code).'-'.strtoupper(Str::random(8));

            return [
                'gateway' => (string) config('commerce.payment.gateway_name', 'sandbox'),
                'provider_reference' => $reference,
                'payment_url' => $order->payment_method === 'qris'
                    ? null
                    : 'sandbox://payment/'.$reference,
                'qris_payload' => $order->payment_method === 'qris'
                    ? "HABLUN-SANDBOX-QRIS|{$reference}|{$order->grand_total}"
                    : null,
                'expires_at' => $expiresAt,
            ];
        }

        throw new RuntimeException(
            'Gateway pembayaran produksi belum dikonfigurasi. Gunakan PAYMENT_DRIVER=sandbox untuk pengembangan.'
        );
    }
}
