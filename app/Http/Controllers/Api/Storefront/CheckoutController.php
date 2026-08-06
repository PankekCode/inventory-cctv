<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\CheckoutRequest;
use App\Services\CheckoutService;
use Illuminate\Http\JsonResponse;

class CheckoutController extends Controller
{
    public function __construct(
        protected CheckoutService $checkoutService
    ) {}

    public function process(CheckoutRequest $request): JsonResponse
    {
        $user = auth('sanctum')->user();
        $order = $this->checkoutService->checkout(
            $request->validated(),
            $user
        );

        return response()->json([
            'message' => 'Pesanan berhasil dibuat.',
            'data' => $order,
        ], 201);
    }
}
