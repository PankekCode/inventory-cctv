<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogService
{
    public function home(): array
    {
        return [
            'banners' => \App\Models\Banner::query()
                ->where('is_active', true)
                ->where(fn (Builder $query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
                ->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
                ->orderBy('sort_order')
                ->get(),
            'categories' => \App\Models\CatalogCategory::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(),
            'featured_products' => $this->products(['featured' => true, 'per_page' => 12]),
            'brands' => \App\Models\Brand::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(),
        ];
    }

    public function products(array $filters = []): LengthAwarePaginator
    {
        $query = Product::query()
            ->published()
            ->with([
                'brand',
                'categories',
                'images',
                'variants' => fn (HasMany $query) => $query
                    ->where('is_active', true)
                    ->with(['components.item']),
            ])
            ->withAvg(['reviews as average_rating' => fn (Builder $query) => $query->where('is_published', true)], 'rating')
            ->withCount(['reviews as review_count' => fn (Builder $query) => $query->where('is_published', true)])
            ->withMin(['variants as starting_price' => fn (Builder $query) => $query->where('is_active', true)], 'price');

        if (!empty($filters['category'])) {
            $query->whereHas('categories', fn (Builder $query) => $query->where('slug', $filters['category']));
        }

        if (!empty($filters['brand'])) {
            $query->whereHas('brand', fn (Builder $query) => $query->where('slug', $filters['brand']));
        }

        if (!empty($filters['type'])) {
            $query->where('product_type', $filters['type']);
        }

        if (isset($filters['featured'])) {
            $query->where('is_featured', filter_var($filters['featured'], FILTER_VALIDATE_BOOLEAN));
        }

        if (!empty($filters['min_price'])) {
            $query->whereHas('variants', fn (Builder $query) => $query->where('price', '>=', $filters['min_price']));
        }

        if (!empty($filters['max_price'])) {
            $query->whereHas('variants', fn (Builder $query) => $query->where('price', '<=', $filters['max_price']));
        }

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(fn (Builder $query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('short_description', 'like', "%{$search}%"));
        }

        match ($filters['sort'] ?? 'latest') {
            'price_asc' => $query->orderBy('starting_price'),
            'price_desc' => $query->orderByDesc('starting_price'),
            'name_asc' => $query->orderBy('name'),
            default => $query->latest('published_at')->latest('id'),
        };

        return $query->paginate(min(max((int) ($filters['per_page'] ?? 12), 1), 48));
    }

    public function findPublished(string $slug): Product
    {
        return Product::query()
            ->published()
            ->where('slug', $slug)
            ->with([
                'brand',
                'categories',
                'images',
                'features',
                'variants' => fn (HasMany $query) => $query
                    ->where('is_active', true)
                    ->with(['components.item']),
                'reviews' => fn (HasMany $query) => $query
                    ->where('is_published', true)
                    ->latest(),
            ])
            ->withAvg(['reviews as average_rating' => fn (Builder $query) => $query->where('is_published', true)], 'rating')
            ->withCount(['reviews as review_count' => fn (Builder $query) => $query->where('is_published', true)])
            ->firstOrFail();
    }
}
