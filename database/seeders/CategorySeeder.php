<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        Category::insert([
            [
                'name' => 'Wire',
                'description' => 'CCTV Dengan Kabel'
            ],
            [
                'name' => 'Wireless',
                'description' => 'CCTV Tanpa Kabel'
            ]
        ]);
    }
}