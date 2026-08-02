<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStockInRequest;
use App\Http\Requests\StoreStockOutRequest;
use App\Http\Resources\ItemResource;
use App\Services\InventoryService;

class StockController extends Controller
{
    public function __construct(
        protected InventoryService $inventoryService
    ) {}

    public function stockIn(StoreStockInRequest $request)
    {
        $item = $this->inventoryService->stockIn(
            $request->validated()
        );

        return new ItemResource($item);
    }

    public function stockOut(StoreStockOutRequest $request)
    {
        $item = $this->inventoryService->stockOut(
            $request->validated()
        );

        return new ItemResource($item);
    }
}