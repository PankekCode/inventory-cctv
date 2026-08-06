<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\CatalogCategory;
use App\Services\CatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function __construct(
        protected CatalogService $catalogService
    ) {}

    public function home(): JsonResponse
    {
        return response()->json([
            'data' => $this->catalogService->home(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $this->catalogService->products($request->all())
        );
    }

    public function show(string $slug): JsonResponse
    {
        return response()->json([
            'data' => $this->catalogService->findPublished($slug),
        ]);
    }

    public function categories(): JsonResponse
    {
        $categories = CatalogCategory::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'data' => $categories,
        ]);
    }

    public function brands(): JsonResponse
    {
        $brands = Brand::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'data' => $brands,
        ]);
    }
}
