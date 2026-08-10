# CCTV Inventory API

REST API untuk sistem manajemen inventory CCTV dan Storefront E-Commerce Hablun CCTV menggunakan Laravel.

> 📖 **Dokumentasi Lengkap API:** Silakan baca [API_DOCUMENTATION.md](file:///d:/Projek/Inventory/inventory-cctv/API_DOCUMENTATION.md) untuk melihat daftar endpoint, parameter, contoh request, dan response JSON.

Project ini digunakan untuk mengelola data barang, kategori, supplier/brand, harga jual, pergerakan stok barang, serta transaksi e-commerce storefront.

---

## Tech Stack

- Laravel 12
- PHP 8.2+
- MySQL
- Laravel Sanctum Authentication
- Laravel Excel (Maatwebsite)
- REST API

---

# Fitur

## Authentication

- Login menggunakan email dan password
- Token authentication menggunakan Laravel Sanctum
- Logout user

---

## Master Data

### Category Management

Mengelola kategori barang.

Contoh kategori:

- CCTV
- Accessories

Fitur:

- Create category
- Read category
- Update category
- Delete category

---

### Supplier / Brand Management

Supplier digunakan sebagai brand produk.

Brand CCTV yang digunakan:

- EZVIZ
- HIKVISION
- DAHUA
- IMOU
- HILOOK

Accessories dapat memiliki supplier kosong karena tidak memiliki brand khusus.

---

### Item Management

Mengelola data barang inventory.

Data item berasal dari file Excel **STOK BARANG**.

Data yang diimport:

| Excel | Database |
|---|---|
| KODE BARANG | code |
| MERK | supplier |
| NAMA PRODUK | name |
| DESKRIPSI | description |
| SATUAN | unit |
| STOK AWAL | stock |

Contoh:

```
EZ-003
H6C 3MP
EZVIZ
Pcs
```

---

# Import Excel

Project menggunakan Laravel Excel untuk proses import data.

## 1. Import Stok Barang

Sumber:

```
STOK BARANG.xlsx
```

Digunakan untuk membuat:

```
items
```

---

## 2. Import Pricelist

Sumber:

```
PRICELIST.xlsx
```

Harga yang digunakan:

```
PERSONAL
```

Data masuk ke:

```
item_prices
```

Contoh:

```
EZ-003
H6C 3MP

Harga Personal:
458000
```

---

# Database Structure

Relasi utama:

```
suppliers
      |
      |
      v
items
      |
      +----------------+
                       |
                       v
                item_prices


items
      |
      |
      v
stock_movements
```

---

# Database Table

## suppliers

Menyimpan data brand/supplier.

Contoh:

```
EZVIZ
HIKVISION
DAHUA
IMOU
HILOOK
```

---

## categories

Menyimpan kategori barang.

Contoh:

```
CCTV
ACCESSORIES
```

---

## items

Menyimpan master barang.

Field utama:

```
id
supplier_id
category_id
code
name
description
purchase_price
minimum_stock
unit
```

---

## item_prices

Menyimpan harga barang.

Saat ini menggunakan:

```
PERSONAL PRICE
```

Field:

```
id
item_id
price
```

---

## stock_movements

Menyimpan histori perubahan stok.

Digunakan untuk:

- Barang masuk
- Barang keluar
- Perhitungan stok

---

# API Endpoint

## Authentication

### Login

```
POST /api/auth/login
```

Request:

```json
{
    "email": "user@example.com",
    "password": "password"
}
```

---

### Logout

```
POST /api/auth/logout
```

---

# Categories

```
GET     /api/categories
POST    /api/categories
GET     /api/categories/{id}
PUT     /api/categories/{id}
DELETE  /api/categories/{id}
```

---

# Suppliers

```
GET     /api/suppliers
POST    /api/suppliers
GET     /api/suppliers/{id}
PUT     /api/suppliers/{id}
DELETE  /api/suppliers/{id}
```

---

# Items

```
GET     /api/items
POST    /api/items
GET     /api/items/{id}
PUT     /api/items/{id}
DELETE  /api/items/{id}
```

---

# Stock

## Stock In

```
POST /api/stock-in
```

## Stock Out

```
POST /api/stock-out
```

---

# Development Progress

## Completed

✅ Laravel API setup  
✅ Authentication with Sanctum  
✅ Category CRUD  
✅ Supplier CRUD  
✅ Item CRUD  
✅ Excel import system  
✅ Import STOK BARANG  
✅ Import PRICELIST Personal  
✅ Item-price relationship  
✅ Inventory database structure  

---

## Planned Development

- [ ] Import stok awal ke stock_movements
- [ ] Dashboard inventory
- [ ] Stock report
- [ ] Low stock notification
- [ ] Transaction history
- [ ] Frontend React integration
- [ ] Role permission management

---

# Installation

Clone repository:

```bash
git clone https://github.com/PankekCode/inventory-cctv.git
```

Masuk folder:

```bash
cd inventory-cctv
```

Install dependency:

```bash
composer install
```

Copy environment:

```bash
cp .env.example .env
```

Generate key:

```bash
php artisan key:generate
```

Setup database:

```bash
php artisan migrate --seed
```

Run server:

```bash
php artisan serve
```

---

# Environment

Contoh konfigurasi:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=inventory
DB_USERNAME=root
DB_PASSWORD=
```

---

# Notes

Saat ini sistem fokus pada:

- Master barang
- Harga personal
- Inventory management

Data serial number CCTV belum digunakan sebagai sumber utama karena membutuhkan relasi langsung dengan kode barang agar tidak terjadi kesalahan mapping produk.

---

## Author

PankekCode