<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;


class SerialNumberSheetImport implements WithMultipleSheets
{

    public function sheets(): array
    {
        return [
            'DATA SN STOK' => new SerialNumberImport()
        ];
    }

}