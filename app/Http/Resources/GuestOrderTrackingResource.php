<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Safe, controlled serialisation of order data for the public guest tracking
 * endpoint. Only fields that are safe to expose without authentication are
 * included. Sensitive fields (guest_phone_e164, customer_email, internal IDs,
 * raw payment references) are intentionally omitted.
 *
 * The qris_payload is conditionally included only while payment_status is
 * "pending" — once paid it is no longer needed and hiding it reduces the
 * surface area for data exposure.
 */
class GuestOrderTrackingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Resolve the pending payment record for QRIS display (if applicable).
        $pendingPayment = null;
        if ($this->payment_status === 'pending' && $this->payments !== null) {
            $pendingPayment = $this->payments->firstWhere('status', 'pending');
        }

        return [
            // Order identity
            'order_code'           => $this->order_code,
            'public_id'            => $this->public_id,
            'created_at'           => $this->created_at,

            // Status
            'status'               => $this->status,
            'payment_status'       => $this->payment_status,
            'payment_method'       => $this->payment_method,
            'payment_expires_at'   => $this->payment_expires_at,

            // Financial
            'subtotal'             => $this->subtotal,
            'installation_fee'     => $this->installation_fee,
            'tax_amount'           => $this->tax_amount,
            'grand_total'          => $this->grand_total,
            'currency'             => $this->currency ?? 'IDR',

            // Customer info — name and address only, no email or phone
            'customer_name'        => $this->customer_name,
            'installation_address' => $this->installation_address,
            'installation_city'    => $this->installation_city,
            'installation_date'    => $this->installation_date,
            'installation_time_slot' => $this->installation_time_slot,
            'customer_note'        => $this->customer_note,

            // Order lines
            'items'                => $this->whenLoaded('items', fn () =>
                $this->items->map(fn ($item) => [
                    'product_name'   => $item->product_name,
                    'variant_name'   => $item->variant_name,
                    'sku'            => $item->sku,
                    'quantity'       => $item->quantity,
                    'unit_price'     => $item->unit_price,
                    'line_total'     => $item->line_total,
                    'installation_included' => $item->installation_included,
                ])
            ),

            // Status history — actor_id intentionally excluded
            'status_history'       => $this->whenLoaded('statusHistories', fn () =>
                $this->statusHistories->map(fn ($h) => [
                    'status'      => $h->status,
                    'title'       => $h->title,
                    'note'        => $h->note,
                    'occurred_at' => $h->occurred_at ?? $h->created_at,
                ])
            ),

            // Technician — only when assigned, only name (no internal ID or private contact)
            'technician'           => $this->whenLoaded('technician', function () {
                if ($this->technician === null) {
                    return null;
                }
                return [
                    'name' => $this->technician->name,
                ];
            }),

            // QRIS payload — only shown while payment is still pending
            // Hidden once paid to reduce unnecessary data exposure.
            'qris_payload'         => $pendingPayment?->qris_payload,
        ];
    }
}
