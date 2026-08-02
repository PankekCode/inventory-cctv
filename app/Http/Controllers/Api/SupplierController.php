<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSupplierRequest;
use App\Http\Requests\UpdateSupplierRequest;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use App\Services\SupplierService;

class SupplierController extends Controller
{
    public function __construct(
    protected SupplierService $supplierService
) {}

    public function index()
    {
        return SupplierResource::collection(
            $this->supplierService->index()
        );
    }

    public function store(StoreSupplierRequest $request)
    {
        $supplier = $this->supplierService->store(
            $request->validated()
        );

        return new SupplierResource($supplier);
    }

    public function show(Supplier $supplier)
    {
        return new SupplierResource(
            $this->supplierService->show($supplier)
        );
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier)
    {
            $supplier = $this->supplierService->update(
            $supplier,
            $request->validated()
        );

        return new SupplierResource($supplier);
    }

    public function destroy(Supplier $supplier)
    {
            $this->supplierService->destroy($supplier);

        return response()->json([
            'message' => 'Supplier berhasil dihapus.'
        ]);
    }
}