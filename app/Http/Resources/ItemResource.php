<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [

            'id' => $this->id,

            'code' => $this->code,

            'name' => $this->name,

            'model' => $this->device_model,

            'description' => $this->description,

            'category' => new CategoryResource(
                $this->whenLoaded('category')
            ),

            'supplier' => new SupplierResource(
                $this->whenLoaded('supplier')
            ),

            'purchase_price' => $this->purchase_price,

            'stock' => $this->stock,

            'minimum_stock' => $this->minimum_stock,

            'unit' => $this->unit,

            'price' => new ItemPriceResource(
                $this->whenLoaded('price')
            ),

            'serial_numbers' => ItemSerialNumberResource::collection(
                $this->whenLoaded('serialNumbers')
            ),
        ];
    }
}