<?php

return [

    'required' => ':attribute wajib diisi.',

    'string' => ':attribute harus berupa teks.',

    'email' => ':attribute harus berupa email yang valid.',

    'unique' => ':attribute sudah digunakan.',

    'exists' => ':attribute tidak ditemukan.',

    'integer' => ':attribute harus berupa angka.',

    'numeric' => ':attribute harus berupa angka.',

    'min' => [
        'numeric' => ':attribute minimal :min.',
        'string' => ':attribute minimal :min karakter.',
    ],

    'max' => [
        'numeric' => ':attribute maksimal :max.',
        'string' => ':attribute maksimal :max karakter.',
    ],

    'gte' => [
        'numeric' => ':attribute harus lebih besar atau sama dengan :value.',
    ],

    'date' => ':attribute harus berupa tanggal yang valid.',


    'attributes' => [

        'name' => 'Nama',

        'email' => 'Email',

        'phone' => 'Nomor telepon',

        'address' => 'Alamat',

        'category_id' => 'Kategori',

        'supplier_id' => 'Supplier',

        'code' => 'Kode barang',

        'description' => 'Deskripsi',

        'purchase_price' => 'Harga beli',

        'selling_price' => 'Harga jual',

        'stock' => 'Stok',

        'minimum_stock' => 'Minimum stok',

        'unit' => 'Satuan',

        'quantity' => 'Jumlah',

        'price' => 'Harga',

        'movement_date' => 'Tanggal transaksi',

        'reference' => 'Referensi',

        'note' => 'Catatan',

        'password' => 'Password',

    ],

];