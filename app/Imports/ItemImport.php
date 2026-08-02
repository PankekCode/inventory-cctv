<?php

namespace App\Imports;

use App\Models\Item;
use App\Models\Supplier;
use App\Models\Category;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ItemImport implements ToCollection, WithHeadingRow
{

    public function headingRow(): int
    {
        return 5;
    }


    public function collection(Collection $rows)
    {

        $brands = [
            'EZVIZ',
            'HIKVISION',
            'DAHUA',
            'IMOU',
            'HILOOK'
        ];


        foreach ($rows as $row) {


            if (!$row['kode_barang']) {
                continue;
            }


            $merk = strtoupper(trim($row['merk']));


            /*
            |--------------------------------------------------------------------------
            | Supplier / Category Mapping
            |--------------------------------------------------------------------------
            */

            if (in_array($merk, $brands)) {


                $supplier = Supplier::firstOrCreate([
                    'name' => $merk
                ]);


                $category = Category::firstOrCreate([
                    'name' => 'CCTV'
                ]);


                $supplierId = $supplier->id;


            } else {


                $category = Category::firstOrCreate([
                    'name' => $merk
                ]);


                $supplierId = null;

            }



            Item::updateOrCreate(

                [
                    'code' => $row['kode_barang']
                ],


                [
                    'supplier_id' => $supplierId,
                    'category_id' => $category->id,
                    'name' => $row['nama_produk'],
                    'model' => $row['kode_barang'],
                    'unit' => $row['satuan'],
                    'stock' => 0,
                    'purchase_price' => 0,
                    'selling_price' => 0,
                ]

            );

        }

    }
}