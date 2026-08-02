<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreItemRequest;
use App\Http\Requests\UpdateItemRequest;
use App\Http\Resources\ItemResource;
use App\Models\Item;
use App\Services\ItemService;

class ItemController extends Controller
{
    public function __construct(
        protected ItemService $itemService
    ) {}

    public function index()
    {
        return ItemResource::collection(
            $this->itemService->index()
        );
    }

    public function store(StoreItemRequest $request)
    {
        $item = $this->itemService->store(
            $request->validated()
        );

        return new ItemResource($item);
    }

        public function show(Item $item)
    {
        return new ItemResource(
            $this->itemService->show($item)
        );
    }

    public function update(UpdateItemRequest $request,Item $item)
    {
        $item = $this->itemService->update(
            $item,
            $request->validated()
        );

        return new ItemResource($item);
    }

    public function destroy(Item $item)
    {
        $this->itemService->destroy($item);

        return response()->json([
            'message' => 'Barang berhasil dihapus.'
        ]);
    }
}