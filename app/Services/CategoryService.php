<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;

class CategoryService
{
    public function index(): Collection
    {
        return Category::orderBy('name')->get();
    }

    public function store(array $data): Category
    {
        return Category::create($data);
    }

    public function show(Category $category): Category
    {
        return $category;
    }

    public function update(Category $category, array $data): Category
    {
        $category->update($data);

        return $category->fresh();
    }

    public function destroy(Category $category): void
    {
        $category->delete();
    }
}