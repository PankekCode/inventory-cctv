# Inventory API

Backend REST API untuk sistem manajemen stok inventory menggunakan
Laravel 12.

Project ini menyediakan fitur pengelolaan master data, transaksi stok,
histori pergerakan barang, dan dashboard ringkasan inventory.

## Tech Stack

-   Laravel 12
-   PHP 8.2+
-   MySQL
-   Laravel Sanctum
-   Docker
-   Postman

## Features

### Authentication

-   Login user menggunakan Laravel Sanctum
-   Logout user
-   Token based authentication

Endpoint:

    POST /api/auth/login
    POST /api/auth/logout

### Category Management

CRUD kategori barang.

Endpoint:

    GET    /api/categories
    POST   /api/categories
    GET    /api/categories/{id}
    PUT    /api/categories/{id}
    DELETE /api/categories/{id}

### Supplier Management

CRUD data supplier.

Endpoint:

    GET    /api/suppliers
    POST   /api/suppliers
    GET    /api/suppliers/{id}
    PUT    /api/suppliers/{id}
    DELETE /api/suppliers/{id}

### Item Management

CRUD barang inventory.

Fitur: - Relasi kategori dan supplier - Auto generate kode barang -
Pengelolaan harga beli dan harga jual - Pengaturan minimum stok

Contoh kode barang:

    ITM-000001
    ITM-000002

Endpoint:

    GET    /api/items
    POST   /api/items
    GET    /api/items/{id}
    PUT    /api/items/{id}
    DELETE /api/items/{id}

### Stock Transaction

#### Stock In

Menambahkan stok barang dan mencatat histori transaksi.

    POST /api/stock-in

Proses:

    Request Stock In
            |
            v
    Create Stock Movement (IN)
            |
            v
    Tambah stok item
            |
            v
    Update harga beli

#### Stock Out

Mengurangi stok barang dengan validasi ketersediaan stok.

    POST /api/stock-out

Jika stok tidak mencukupi sistem mengembalikan error:

``` json
{
    "message": "Stock tidak mencukupi untuk transaksi ini.",
    "errors": {
        "stock": [
            "Stock tidak mencukupi."
        ]
    }
}
```

## Stock Movement History

Menyimpan seluruh riwayat perubahan stok.

Data yang dicatat: - Item - User - Tipe transaksi (IN/OUT) - Quantity -
Harga - Tanggal transaksi - Referensi - Catatan

Endpoint:

    GET /api/stock-movements
    GET /api/stock-movements/{id}

## Dashboard Summary

Menampilkan ringkasan inventory.

Endpoint:

    GET /api/dashboard

Informasi: - Total item - Total kategori - Total supplier - Total stok -
Stock masuk hari ini - Stock keluar hari ini

## Database Relationship

    Category
        |
        | 1:N
        |
    Item
        |
        | N:1
        |
    Supplier


    Item
        |
        | 1:N
        |
    Stock Movement


    User
        |
        | 1:N
        |
    Stock Movement

## Installation

Clone repository:

``` bash
git clone https://github.com/PankekCode/inventory-api.git
cd inventory-api
```

Install dependency:

``` bash
composer install
```

Setup environment:

``` bash
cp .env.example .env
php artisan key:generate
```

Database migration:

``` bash
php artisan migrate --seed
```

Run application:

``` bash
php artisan serve
```

API tersedia pada:

    http://127.0.0.1:8000

## Testing

API telah diuji menggunakan Postman dengan cakupan:

-   Authentication
-   Category CRUD
-   Supplier CRUD
-   Item CRUD
-   Stock In
-   Stock Out
-   Stock Movement
-   Dashboard
-   Validation Handling
-   Error Handling

## Project Structure

    app
    ├── Http
    │   ├── Controllers
    │   ├── Requests
    │   └── Resources
    │
    ├── Models
    ├── Services
    └── Enums

    database
    ├── migrations
    └── seeders

    routes
    └── api.php

## Author

PankekCode

Inventory Management API Project
