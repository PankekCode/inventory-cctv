<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

class SupplierSheetImport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            'STOK BARANG' => new SupplierImport(),
        ];
    }
}