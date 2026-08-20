<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        protected OrderService $orderService,
        protected \App\Services\InvoiceService $invoiceService,
    ) {}

    public function invoice(Order $order)
    {
        return $this->invoiceService->download($order);
    }

    public function index(Request $request): JsonResponse
    {
        $query = Order::query()->with(['user', 'items', 'payments', 'statusHistories']);

        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->has('payment_status')) {
            $query->where('payment_status', $request->query('payment_status'));
        }

        if ($request->has('search')) {
            $search = trim($request->query('search'));
            $query->where(fn ($q) => $q->where('order_code', 'like', "%{$search}%")
                ->orWhere('customer_name', 'like', "%{$search}%")
                ->orWhere('customer_email', 'like', "%{$search}%")
                ->orWhere('guest_phone_e164', 'like', "%{$search}%"));
        }

        $orders = $query->latest('id')->paginate(min(max((int) $request->query('per_page', 15), 1), 100));

        return response()->json($orders);
    }

    public function show(Order $order): JsonResponse
    {
        return response()->json([
            'data' => new OrderResource($order->load([
                'user',
                'items.product',
                'items.variant',
                'payments',
                'statusHistories',
            ])),
        ]);
    }

    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'string'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $updatedOrder = $this->orderService->transition(
            $order,
            (string) $request->input('status'),
            $request->input('note'),
            auth()->id()
        );

        return response()->json([
            'message' => 'Status pesanan berhasil diperbarui.',
            'data' => new OrderResource($updatedOrder),
        ]);
    }

    public function assignTechnician(Request $request, Order $order): JsonResponse
    {
        $request->validate([
            'technician_id' => ['required', 'integer', 'exists:technicians,id'],
        ]);

        $technician = \App\Models\Technician::whereKey($request->input('technician_id'))
            ->where('is_active', true)
            ->first();

        if (!$technician) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'technician' => ['Teknisi tidak aktif atau tidak ditemukan.'],
            ]);
        }

        $order->update(['technician_id' => $technician->id]);

        return response()->json([
            'message' => 'Teknisi berhasil ditugaskan.',
            'data' => new OrderResource($order->fresh()->load(['user', 'items', 'payments', 'statusHistories', 'technician'])),
        ]);
    }
}
