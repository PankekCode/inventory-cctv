<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService
    ) {}

    public function index(\Illuminate\Http\Request $request)
    {
        return response()->json([
            'message' => 'Laporan penjualan dan inventori berhasil diambil.',
            'data' => $this->dashboardService->summary($request->all())
        ]);
    }
}