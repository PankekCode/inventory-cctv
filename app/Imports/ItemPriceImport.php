<?php

namespace App\Imports;

use App\Models\Item;
use App\Models\ItemPrice;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;


class ItemPriceImport implements ToCollection, WithHeadingRow
{

    public function headingRow(): int
    {
        return 2;
    }


    public function collection(Collection $rows)
    {

        foreach ($rows as $row) {


            if (!isset($row['kode_barang'])) {
                continue;
            }


            if (!$row['kode_barang']) {
                continue;
            }


            $item = Item::where(
                'code',
                $row['kode_barang']
            )->first();


            if (!$item) {
                continue;
            }


            if (isset($row['personal']) && $row['personal'] !== null) {
                ItemPrice::updateOrCreate(
                    ['item_id' => $item->id],
                    ['price' => $row['personal']]
                );

                \App\Models\ProductVariant::where('sku', $row['kode_barang'])
                    ->update(['price' => $row['personal']]);
            }

        }

    }
}