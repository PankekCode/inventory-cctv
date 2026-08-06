<?php

namespace App\Http\Resources;

use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CatalogProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $variants = $this->whenLoaded('variants');
        $variantCollection = $this->relationLoaded('variants') ? $this->variants : collect();
        $primaryImage = $this->relationLoaded('images')
            ? ($this->images->firstWhere('is_primary', true) ?? $this->images->first())
            : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'product_type' => $this->product_type,
            'short_description' => $this->short_description,
            'description' => $this->when($this->relationLoaded('features') || $request->routeIs('catalog.products.show'), $this->description),
            'specifications' => $this->when($request->routeIs('catalog.products.show'), $this->specifications),
            'is_featured' => $this->is_featured,
            'brand' => $this->whenLoaded('brand', fn () => [
                'id' => $this->brand?->id,
                'name' => $this->brand?->name,
                'slug' => $this->brand?->slug,
                'logo_path' => $this->brand?->logo_path,
            ]),
            'categories' => $this->whenLoaded('categories', fn () => $this->categories->map(fn ($category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
            ])),
            'thumbnail' => $primaryImage?->path,
            'images' => $this->whenLoaded('images', fn () => $this->images->map(fn ($image) => [
                'id' => $image->id,
                'path' => $image->path,
                'alt_text' => $image->alt_text,
                'is_primary' => $image->is_primary,
                'sort_order' => $image->sort_order,
            ])),
            'features' => $this->whenLoaded('features', fn () => $this->features->map(fn ($feature) => [
                'title' => $feature->title,
                'description' => $feature->description,
                'icon' => $feature->icon,
            ])),
            'starting_price' => (string) ($this->starting_price ?? $variantCollection->min('price')),
            'average_rating' => $this->average_rating !== null ? round((float) $this->average_rating, 1) : null,
            'review_count' => (int) ($this->review_count ?? 0),
            'in_stock' => $this->isInStock($variantCollection),
            'variants' => $this->whenLoaded('variants', fn () => $variantCollection->map(
                fn (ProductVariant $variant) => $this->variant($variant)
            )),
            'reviews' => $this->whenLoaded('reviews', fn () => $this->reviews->map(fn ($review) => [
                'author_name' => $review->author_name ?: $review->user?->name,
                'rating' => $review->rating,
                'comment' => $review->comment,
                'created_at' => $review->created_at,
            ])),
        ];
    }

    private function variant(ProductVariant $variant): array
    {
        return [
            'id' => $variant->id,
            'sku' => $variant->sku,
            'name' => $variant->name,
            'variant_type' => $variant->variant_type,
            'camera_count' => $variant->camera_count,
            'price' => (string) $variant->price,
            'installation_included' => $variant->installation_included,
            'warranty_months' => $variant->warranty_months,
            'configuration' => $variant->configuration,
            'in_stock' => $this->variantIsInStock($variant),
        ];
    }

    private function isInStock($variants): bool
    {
        return $variants->contains(fn (ProductVariant $variant) => $this->variantIsInStock($variant));
    }

    private function variantIsInStock(ProductVariant $variant): bool
    {
        if (!$variant->is_stock_managed) {
            return true;
        }

        if (!$variant->relationLoaded('components') || $variant->components->isEmpty()) {
            return false;
        }

        return $variant->components->every(function ($component): bool {
            return $component->item !== null
                && $component->item->available_stock >= $component->quantity;
        });
    }
}
