<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ItemPriceSheetImport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            'PRICELIST' => new ItemPriceImport()
        ];
    }
}