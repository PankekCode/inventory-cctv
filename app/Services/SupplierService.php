<?php

namespace App\Services;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Collection;

class SupplierService
{
    public function index(): Collection
    {
        return Supplier::orderBy('name')->get();
    }

    public function store(array $data): Supplier
    {
        return Supplier::create($data);
    }

    public function show(Supplier $supplier): Supplier
    {
        return $supplier;
    }

    public function update(Supplier $supplier, array $data): Supplier
    {
        $supplier->update($data);

        return $supplier->fresh();
    }

    public function destroy(Supplier $supplier): void
    {
        $supplier->delete();
    }
}