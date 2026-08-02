<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StockMovementResource;
use App\Models\StockMovement;
use App\Services\StockMovementService;

class StockMovementController extends Controller
{
    public function __construct(
        protected StockMovementService $stockMovementService
    ) {}


    public function index()
    {
        return StockMovementResource::collection(
            $this->stockMovementService->index()
        );
    }


    public function show(StockMovement $stockMovement)
    {
        return new StockMovementResource(
            $this->stockMovementService->show($stockMovement)
        );
    }
}