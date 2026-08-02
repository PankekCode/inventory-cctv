<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ItemSheetImport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            'STOK BARANG' => new ItemImport()
        ];
    }
}