<?php

namespace App\Contracts;

use App\Models\Order;

interface PaymentGatewayInterface
{
    /**
     * Create a payment intent/transaction for the given order.
     *
     * @return array{gateway:string,provider_reference:string,payment_url:?string,qris_payload:?string,expires_at:\Carbon\CarbonInterface}
     */
    public function create(Order $order): array;
}
