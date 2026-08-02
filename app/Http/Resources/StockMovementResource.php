<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [

            'id' => $this->id,

            'item' => [
                'id' => $this->item->id,
                'code' => $this->item->code,
                'name' => $this->item->name,
            ],

            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ],

            'type' => $this->type,

            'quantity' => $this->quantity,

            'price' => $this->price,

            'movement_date' => $this->movement_date,

            'reference' => $this->reference,

            'note' => $this->note,

        ];
    }
}