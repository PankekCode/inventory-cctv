<?php

namespace App\Imports;

use App\Models\Supplier;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;


class SupplierImport implements ToCollection, WithHeadingRow
{

    public function headingRow(): int
    {
        return 5;
    }


    public function collection(Collection $rows)
    {

        foreach ($rows as $row) {


            if (!isset($row['merk'])) {
                continue;
            }


            $merk = trim($row['merk']);


            if (!$merk) {
                continue;
            }


            Supplier::firstOrCreate([
                'name'=>$merk
            ]);

        }

    }
}