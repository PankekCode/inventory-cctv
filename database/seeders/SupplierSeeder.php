<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Supplier;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        Supplier::insert([
            [
                'name' => 'PT Ezviz',
                'email' => 'sales@ezviz.com',
                'phone' => '021123456',
                'address' => 'Jakarta'
            ],
            [
                'name' => 'PT Hikvision Indonesia',
                'email' => 'sales@hikvision.com',
                'phone' => '021987654',
                'address' => 'Bandung'
            ]
        ]);
    }
}