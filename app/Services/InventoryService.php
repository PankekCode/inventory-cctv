<?php

namespace App\Services;

use App\Models\Item;
use App\Models\StockMovement;
use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStockException;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    public function stockIn(array $data): Item
    {
        return DB::transaction(function () use ($data) {

            $item = Item::findOrFail(
                $data['item_id']
            );

            StockMovement::create([
                'item_id' => $item->id,
                'user_id' => auth()->id(),
                'type' => StockMovementType::IN,
                'quantity' => $data['quantity'],
                'price' => $data['price'],
                'movement_date' => $data['movement_date'],
                'reference' => $data['reference'] ?? null,
                'note' => $data['note'] ?? null,
            ]);

            $item->increment(
                'stock',
                $data['quantity']
            );

            $item->update([
                'purchase_price' => $data['price']
            ]);

            return $item
                ->fresh()
                ->load([
                    'category',
                    'supplier',
                ]);
        });
    }

    public function stockOut(array $data): Item
    {
        return DB::transaction(function () use ($data) {

            $item = Item::query()
                ->whereKey($data['item_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($item->available_stock < $data['quantity']) {
                throw new InsufficientStockException();
            }

            StockMovement::create([
                'item_id' => $item->id,
                'user_id' => auth()->id(),
                'type' => StockMovementType::OUT,
                'quantity' => $data['quantity'],
                'price' => $item->purchase_price,
                'movement_date' => $data['movement_date'],
                'reference' => $data['reference'] ?? null,
                'note' => $data['note'] ?? null,
            ]);

            $item->decrement(
                'stock',
                $data['quantity']
            );

            return $item
                ->fresh()
                ->load([
                    'category',
                    'supplier',
                ]);
        });
    }
}