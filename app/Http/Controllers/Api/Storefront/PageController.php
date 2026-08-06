<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Models\CompanyProfile;
use App\Models\Service;
use Illuminate\Http\JsonResponse;

class PageController extends Controller
{
    public function companyProfile(): JsonResponse
    {
        $profile = CompanyProfile::first();

        return response()->json([
            'data' => $profile,
        ]);
    }

    public function services(): JsonResponse
    {
        $services = Service::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'data' => $services,
        ]);
    }
}
