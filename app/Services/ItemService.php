<?php

namespace App\Services;

use App\Models\Item;
use Illuminate\Database\Eloquent\Collection;

class ItemService
{
    public function index(): Collection
    {
        return Item::with([
            'category',
            'supplier',
            'price',
            'serialNumbers',
        ])
        ->orderBy('name')
        ->get();
    }

    public function store(array $data): Item
    {
        $item = Item::create([
            ...$data,
            'stock' => 0,
        ]);

        return $item
            ->load([
                'category',
                'supplier',
                'price',
                'serialNumbers',
            ]);
    }

    public function show(Item $item): Item
    {
        return $item->load([
            'category',
            'supplier',
            'price',
            'serialNumbers',
        ]);
    }

    public function update(Item $item, array $data): Item
    {
        $item->update($data);

        return $item->fresh()->load([
            'category',
            'supplier',
            'price',
            'serialNumbers',
        ]);
    }

    public function destroy(Item $item): void
    {
        $item->delete();
    }
}