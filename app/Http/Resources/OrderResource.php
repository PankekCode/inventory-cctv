<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'order_code'        => $this->order_code,
            'customer_name' => $this->customer_name,
            'installation_address' => $this->installation_address,
            'installation_city' => $this->installation_city,
            'installation_date' => $this->installation_date,
            'installation_time_slot' => $this->installation_time_slot,
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'payment_method' => $this->payment_method,
            'subtotal' => (string) $this->subtotal,
            'installation_fee' => (string) $this->installation_fee,
            'tax_amount' => (string) $this->tax_amount,
            'grand_total' => (string) $this->grand_total,
            'currency' => $this->currency,
            'payment_expires_at' => $this->payment_expires_at,
            'paid_at' => $this->paid_at,
            'created_at' => $this->created_at,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'product_name' => $item->product_name,
                'variant_name' => $item->variant_name,
                'sku' => $item->sku,
                'quantity' => $item->quantity,
                'unit_price' => (string) $item->unit_price,
                'line_total' => (string) $item->line_total,
                'installation_included' => $item->installation_included,
                'configuration' => $item->configuration,
            ])),
            'payments' => $this->whenLoaded('payments', fn () => $this->payments->map(fn ($payment) => [
                'gateway' => $payment->gateway,
                'method' => $payment->method,
                'status' => $payment->status,
                'provider_reference' => $payment->provider_reference,
                'amount' => (string) $payment->amount,
                'payment_url' => $payment->payment_url,
                'qris_payload' => $payment->qris_payload,
                'expires_at' => $payment->expires_at,
            ])),
            'status_history' => $this->whenLoaded('statusHistories', fn () => $this->statusHistories->map(fn ($history) => [
                'status' => $history->status,
                'title' => $history->title,
                'note' => $history->note,
                'occurred_at' => $history->occurred_at,
            ])),
            'technician' => $this->whenLoaded('technician', fn () => $this->technician ? [
                'id' => $this->technician->id,
                'name' => $this->technician->name,
                'phone_e164' => $this->technician->phone_e164,
            ] : null),
        ];
    }
}
