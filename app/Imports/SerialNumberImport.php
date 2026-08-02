<?php

namespace App\Imports;

use App\Models\Item;
use App\Models\ItemSerialNumber;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;


class SerialNumberImport implements ToCollection, WithHeadingRow
{

    public function headingRow(): int
    {
        return 1;
    }


    public function collection(Collection $rows)
    {

        foreach ($rows as $row) {


            if (!isset($row['serial_number'])) {
                continue;
            }


            if (!$row['serial_number']) {
                continue;
            }


            $item = Item::where('device_model', trim($row['model']))
            ->whereHas('supplier', function ($q) use ($row) {
                $q->where('name', trim($row['merk']));
            })
            ->first();


            if (!$item) {
                continue;
            }


            ItemSerialNumber::updateOrCreate(
                [
                    'serial_number' => $row['serial_number']
                ],
                [
                    'item_id' => $item->id,
                    'status' => 'AVAILABLE'
                ]
            );


        }

    }
}