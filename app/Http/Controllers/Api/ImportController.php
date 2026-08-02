<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Imports\SupplierImport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\SupplierSheetImport;
use App\Imports\ItemSheetImport;
use App\Imports\ItemPriceSheetImport;
use App\Imports\SerialNumberSheetImport;


class ImportController extends Controller
{

    public function supplier(Request $request)
    {

        $request->validate([
            'file'=>'required|mimes:xlsx,xls'
        ]);


        Excel::import(
            new SupplierSheetImport,
            $request->file('file')
        );


        return response()->json([
            'message'=>'Supplier berhasil diimport'
        ]);

    }

    public function item(Request $request)
    {

        $request->validate([
            'file'=>'required|mimes:xlsx,xls'
        ]);


        Excel::import(
            new ItemSheetImport(),
            $request->file('file')
        );


        return response()->json([
            'message'=>'Item berhasil diimport'
        ]);

    }

    public function itemPrice(Request $request)
    {

        $request->validate([
            'file'=>'required|mimes:xlsx,xls'
        ]);


        Excel::import(
            new ItemPriceSheetImport(),
            $request->file('file')
        );


        return response()->json([
            'message'=>'Harga berhasil diimport'
        ]);

    }

    public function serialNumber(Request $request)
    {

        $request->validate([
            'file'=>'required|mimes:xlsx,xls'
        ]);


        Excel::import(
            new SerialNumberSheetImport(),
            $request->file('file')
        );


        return response()->json([
            'message'=>'Serial number berhasil diimport'
        ]);

    }

}