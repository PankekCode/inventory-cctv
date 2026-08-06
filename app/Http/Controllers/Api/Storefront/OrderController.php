<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        protected OrderService $orderService
    ) {}

    public function track(string $code): JsonResponse
    {
        $order = Order::query()
            ->where('unique_order_code', strtoupper(trim($code)))
            ->with([
                'items.product',
                'items.variant',
                'payments',
                'statusHistories',
                'technician',
            ])
            ->firstOrFail();

        return response()->json([
            'data' => $order,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Order::query()
            ->where('user_id', $user->id)
            ->with(['items.product', 'payments', 'technician'])
            ->latest();

        if ($status = $request->query('status')) {
            if ($status === 'active') {
                $query->whereNotIn('status', ['completed', 'cancelled']);
            } else {
                $query->where('status', $status);
            }
        }

        return response()->json(
            $query->paginate(15)
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $order = Order::query()
            ->where('user_id', $request->user()->id)
            ->where('id', $id)
            ->with([
                'items.product',
                'items.variant',
                'payments',
                'statusHistories',
                'technician',
            ])
            ->firstOrFail();

        return response()->json([
            'data' => $order,
        ]);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $order = Order::query()
            ->where('user_id', $request->user()->id)
            ->where('id', $id)
            ->firstOrFail();

        $note = $request->input('note', 'Dibatalkan oleh pelanggan');
        $updatedOrder = $this->orderService->transition($order, 'cancelled', $note, $request->user()->id);

        return response()->json([
            'message' => 'Pesanan berhasil dibatalkan.',
            'data' => $updatedOrder,
        ]);
    }
}
