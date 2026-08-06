<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'guest_token' => $this->when(!$this->user_id, $this->guest_token),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'product_variant_id' => $item->product_variant_id,
                'quantity' => $item->quantity,
                'product' => [
                    'name' => $item->variant?->product?->name,
                    'slug' => $item->variant?->product?->slug,
                ],
                'variant' => [
                    'name' => $item->variant?->name,
                    'price' => $item->variant?->price,
                ],
            ])),
        ];
    }
}
