# 📄 API Contract — Hablun CCTV REST API

> **Versi:** 1.0.0
> **Base URL:** `http://localhost:8000/api`
> **Format:** JSON (`Content-Type: application/json`, `Accept: application/json`)
> **Autentikasi:** Laravel Sanctum Bearer Token

---

## Konvensi

- `🔓 Public` — Tidak memerlukan autentikasi
- `🔑 Sanctum` — Memerlukan header `Authorization: Bearer <token>`
- `🛡️ Admin` — Memerlukan Sanctum + role `admin`
- Field bertanda `*` = **wajib diisi**
- Tipe data: `string`, `integer`, `number`, `boolean`, `array`, `object`, `uuid`, `date (YYYY-MM-DD)`, `datetime (ISO 8601)`

---

## Skema Data Umum (Shared Schemas)

### `OrderResource`
```
id                  : integer
public_id           : uuid
unique_order_code   : string          -- contoh: "HBL-2026-0001"
customer_name       : string
installation_address: string
installation_city   : string
installation_date   : date
installation_time_slot: string
status              : enum            -- lihat Status Pesanan
payment_status      : enum            -- pending | paid | failed | expired | cancelled
payment_method      : string
subtotal            : string (decimal)
installation_fee    : string (decimal)
tax_amount          : string (decimal)
grand_total         : string (decimal)
currency            : string          -- contoh: "IDR"
payment_expires_at  : datetime | null
paid_at             : datetime | null
created_at          : datetime
items[]             : OrderItemSchema
payments[]          : PaymentSchema
status_history[]    : StatusHistorySchema
```

### `OrderItemSchema`
```
product_name        : string
variant_name        : string
sku                 : string
quantity            : integer
unit_price          : string (decimal)
line_total          : string (decimal)
installation_included: boolean
configuration       : object | null
```

### `PaymentSchema`
```
gateway             : string
method              : string
status              : enum            -- pending | paid | failed | expired | cancelled
provider_reference  : string | null
amount              : string (decimal)
payment_url         : string | null
qris_payload        : string | null
expires_at          : datetime | null
```

### `StatusHistorySchema`
```
status              : string
title               : string
note                : string | null
occurred_at         : datetime
```

### `ValidationErrorResponse` (422)
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "<field>": ["<pesan error>"]
  }
}
```

---

## Alur Status Pesanan

```
awaiting_payment
  └─ (cancel)             → cancelled
  └─ (payment confirmed)  → order_received
        └─ (cancel)       → cancelled
        └─ (updateStatus) → installation_in_progress
                                └─ (updateStatus) → completed
```

| `status` Enum | Label |
| :--- | :--- |
| `awaiting_payment` | Menunggu Pembayaran |
| `order_received` | Pesanan Diterima |
| `installation_in_progress` | Proses Pemasangan |
| `completed` | Selesai |
| `cancelled` | Dibatalkan |
| `payment_expired` | Pembayaran Kedaluwarsa |

---

## ═══════════════════════════════════════
## BAGIAN A — Storefront API (Public & Customer)
## ═══════════════════════════════════════

---

### A1. Katalog Publik

---

#### `GET /storefront/home`
🔓 Public — Data beranda (banner, kategori, produk unggulan, brand)

**Response 200:**
```json
{
  "data": {
    "banners": [
      { "id": "integer", "title": "string", "description": "string", "image_path": "string", "cta_label": "string", "cta_url": "string" }
    ],
    "categories": [
      { "id": "integer", "name": "string", "slug": "string", "image_path": "string" }
    ],
    "featured_products": { "data": ["<CatalogProductSchema>"] },
    "brands": [
      { "id": "integer", "name": "string", "slug": "string", "logo_path": "string" }
    ]
  }
}
```

---

#### `GET /storefront/products`
🔓 Public — Daftar produk katalog dengan filter & paginasi

**Query Parameters:**
| Parameter | Tipe | Wajib | Default | Keterangan |
| :--- | :--- | :--- | :--- | :--- |
| `search` | string | Tidak | — | Cari nama/deskripsi produk |
| `category` | string | Tidak | — | Slug kategori |
| `brand` | string | Tidak | — | Slug brand |
| `type` | string | Tidak | — | `wireless` atau `analog` |
| `featured` | boolean | Tidak | — | Filter produk unggulan |
| `min_price` | number | Tidak | — | Harga minimum |
| `max_price` | number | Tidak | — | Harga maksimum |
| `sort` | string | Tidak | `latest` | `latest` \| `price_asc` \| `price_desc` \| `name_asc` |
| `per_page` | integer | Tidak | `12` | Jumlah item per halaman |

**Response 200:** Laravel Pagination dengan `data[]` berisi CatalogProductSchema

---

#### `GET /storefront/products/{slug}`
🔓 Public — Detail produk berdasarkan slug

**Path Params:** `slug` string* — Slug produk

**Response 200:**
```json
{
  "data": {
    "id": "integer",
    "name": "string",
    "slug": "string",
    "product_type": "string",
    "description": "string",
    "specifications": "object | null",
    "average_rating": "number | null",
    "review_count": "integer",
    "brand": { "id": "integer", "name": "string", "slug": "string" },
    "images": [{ "id": "integer", "path": "string", "is_primary": "boolean" }],
    "features": [{ "id": "integer", "title": "string", "description": "string" }],
    "variants": [
      {
        "id": "integer", "sku": "string", "name": "string",
        "variant_type": "enum: unit | installation_package",
        "price": "string (decimal)", "installation_included": "boolean"
      }
    ]
  }
}
```

**Response 404:** Resource tidak ditemukan

---

#### `GET /storefront/categories`
🔓 Public — Daftar kategori katalog

**Response 200:**
```json
{ "data": [{ "id": "integer", "name": "string", "slug": "string", "image_path": "string | null" }] }
```

---

#### `GET /storefront/brands`
🔓 Public — Daftar brand partner

**Response 200:**
```json
{ "data": [{ "id": "integer", "name": "string", "slug": "string", "logo_path": "string | null" }] }
```

---

#### `GET /storefront/company-profile`
🔓 Public — Profil perusahaan

**Response 200:**
```json
{
  "data": {
    "company_name": "string", "about": "string", "vision": "string | null",
    "contacts": { "phone": "string", "whatsapp": "string", "address": "string" }
  }
}
```

---

#### `GET /storefront/services`
🔓 Public — Daftar layanan yang tersedia

**Response 200:**
```json
{ "data": [{ "id": "integer", "name": "string", "slug": "string", "description": "string", "whatsapp_url": "string | null" }] }
```

---

### A2. Autentikasi WhatsApp OTP

---

#### `POST /storefront/otp/send`
🔓 Public — Kirim kode OTP ke nomor WhatsApp

**Request Body:**
| Field | Tipe | Wajib | Keterangan |
| :--- | :--- | :--- | :--- |
| `phone` | string | * | Nomor telepon (format lokal atau E.164) |
| `purpose` | string | * | `guest_checkout` \| `customer_login` \| `customer_register` |

**Response 200:**
```json
{
  "message": "Kode OTP berhasil dikirim via WhatsApp.",
  "data": { "verification_id": "uuid", "phone": "string (E.164)", "expires_at": "datetime" }
}
```

**Response 422:** `phone` tidak valid atau `purpose` tidak dikenali

---

#### `POST /storefront/otp/verify`
🔓 Public — Verifikasi kode OTP

**Request Body:**
| Field | Tipe | Wajib | Keterangan |
| :--- | :--- | :--- | :--- |
| `verification_id` | uuid | * | ID verifikasi dari `/otp/send` |
| `code` | string | * | Kode OTP 4–6 digit |
| `purpose` | string | * | Harus sama dengan saat kirim OTP |

**Response 200:**
```json
{
  "message": "Nomor WhatsApp berhasil diverifikasi.",
  "data": { "verification_id": "uuid", "phone": "string (E.164)", "verified_at": "datetime" }
}
```

**Response 422:** Kode salah, kedaluwarsa, atau verification_id tidak cocok

---

#### `POST /storefront/auth/login-otp`
🔓 Public — Login atau registrasi pelanggan via OTP

**Request Body:**
| Field | Tipe | Wajib | Keterangan |
| :--- | :--- | :--- | :--- |
| `verification_id` | uuid | * | UUID yang sudah terverifikasi (purpose: `customer_login`/`customer_register`) |
| `name` | string | Tidak | Nama pelanggan (wajib untuk registrasi baru) |
| `password` | string | Tidak | Password (opsional, untuk akun baru) |

**Response 200:**
```json
{
  "message": "Autentikasi berhasil.",
  "data": {
    "user": { "id": "integer", "name": "string", "phone_e164": "string", "phone_verified_at": "datetime", "role": "customer" },
    "token": "string (Bearer token Sanctum)"
  }
}
```

---

### A3. Keranjang Belanja

---

#### `GET /storefront/cart`
🔓 Public / 🔑 Sanctum — Lihat isi keranjang

**Query Parameters:**
| Parameter | Tipe | Wajib | Keterangan |
| :--- | :--- | :--- | :--- |
| `guest_token` | string | Jika guest | Token identifikasi guest cart |

**Response 200:**
```json
{
  "data": {
    "id": "integer", "public_id": "uuid", "guest_token": "string | null",
    "items": [
      {
        "id": "integer", "product_variant_id": "integer", "quantity": "integer",
        "variant": { "id": "integer", "name": "string", "price": "string (decimal)", "product": { "name": "string" } }
      }
    ]
  }
}
```

---

#### `POST /storefront/cart/items`
🔓 Public / 🔑 Sanctum — Tambah item ke keranjang

**Request Body:**
| Field | Tipe | Wajib | Keterangan |
| :--- | :--- | :--- | :--- |
| `product_variant_id` | integer | * | ID varian produk |
| `quantity` | integer | * | Jumlah item (min: 1) |
| `guest_token` | string | Jika guest | Token guest cart |

**Response 200:** Struktur keranjang terbaru (sama dengan GET Cart)

---

#### `PUT /storefront/cart/items/{id}`
🔓 Public / 🔑 Sanctum — Update jumlah item di keranjang

**Path Params:** `id` integer* — ID cart item

**Request Body:**
| Field | Tipe | Wajib | Keterangan |
| :--- | :--- | :--- | :--- |
| `quantity` | integer | * | Jumlah baru (min: 1) |
| `guest_token` | string | Jika guest | Token guest cart |

**Response 200:** Struktur keranjang terbaru

---

#### `DELETE /storefront/cart/items/{id}`
🔓 Public / 🔑 Sanctum — Hapus item dari keranjang

**Path Params:** `id` integer* — ID cart item

**Query Parameters:** `guest_token` string (wajib jika guest)

**Response 200:** Struktur keranjang terbaru

---

### A4. Checkout & Pelacakan

---

#### `POST /storefront/checkout`
🔓 Public (Guest) / 🔑 Sanctum (Customer) — Buat pesanan baru

**Request Body:**
| Field | Tipe | Wajib | Keterangan |
| :--- | :--- | :--- | :--- |
| `customer_name` | string | * | Nama pemesan |
| `phone` | string | Jika guest | Nomor WhatsApp |
| `verification_id` | uuid | Jika guest | UUID terverifikasi (purpose: `guest_checkout`) |
| `installation_address` | string | * | Alamat pemasangan lengkap |
| `installation_city` | string | * | Kota/kabupaten pemasangan |
| `installation_date` | date | * | Tanggal pemasangan (YYYY-MM-DD) |
| `installation_time_slot` | string | * | Slot waktu (contoh: "09:00 - 11:00") |
| `payment_method` | string | * | `qris` (guest wajib QRIS) \| `bank_transfer` \| `gopay` \| `ovo` \| `shopeepay` |
| `items[]` | array | * | Daftar item pesanan |
| `items[].product_variant_id` | integer | * | ID varian produk |
| `items[].quantity` | integer | * | Jumlah item (min: 1) |
| `customer_note` | string | Tidak | Catatan tambahan pelanggan |
| `guest_token` | string | Tidak | Token guest cart (jika ada) |

**Response 201:** OrderResource lengkap + payment URL/QRIS payload

**Response 422:**
- `verification_id` tidak valid atau belum terverifikasi
- `payment_method` bukan `qris` untuk guest
- Stok tidak mencukupi

---

#### `GET /storefront/orders/track/{code}`
🔓 Public — Lacak pesanan berdasarkan kode unik

**Path Params:** `code` string* — Kode unik pesanan (contoh: `HBL-2026-0001`)

**Response 200:**
```json
{
  "data": {
    "unique_order_code": "string", "customer_name": "string",
    "status": "enum (lihat Status Pesanan)", "payment_status": "string",
    "grand_total": "string (decimal)",
    "status_histories": ["<StatusHistorySchema>"]
  }
}
```

**Response 404:** Kode pesanan tidak ditemukan

---

#### `GET /storefront/orders/track/{code}/invoice`
🔓 Public — Download invoice pesanan

**Path Params:** `code` string* — Kode unik pesanan

**Response 200:** `Content-Type: text/html` — File HTML Invoice

---

### A5. Webhook & Payment

---

#### `POST /storefront/payments/webhook`
🔓 Public — Menerima callback dari payment gateway

**Request Body:**
| Field | Tipe | Wajib | Keterangan |
| :--- | :--- | :--- | :--- |
| `gateway` | string | * | Nama gateway (contoh: `sandbox`) |
| `provider_reference` | string | * | Referensi unik dari gateway |
| `status` | string | * | `paid` atau `failed` |
| `event_id` | string | * | ID event unik (idempoten) |
| `amount` | number | * | Nominal pembayaran (dalam IDR) |

**Response 200:**
```json
{
  "message": "string",
  "data": { "payment_id": "integer", "provider_reference": "string", "status": "string", "order_code": "string" }
}
```

**Response 422:** Nominal tidak cocok / pembayaran kedaluwarsa / field wajib kosong

---

#### `GET /storefront/payments/{reference}`
🔓 Public (Guest) / 🔑 Sanctum — Detail status pembayaran

**Path Params:** `reference` string* — provider_reference dari gateway

**Query Parameters (Guest):** `order_code` string — Kode pesanan (wajib jika guest)

**Response 200:** Detail payment + ringkasan order

---

### A6. Area Pelanggan (Authenticated)

---

#### `GET /storefront/auth/me`
🔑 Sanctum — Profil pelanggan yang sedang login

**Response 200:**
```json
{
  "data": {
    "id": "integer", "name": "string", "phone_e164": "string", "email": "string | null",
    "addresses": [
      { "id": "integer", "label": "string", "recipient_name": "string", "phone_e164": "string", "address_line": "string", "city": "string", "is_default": "boolean" }
    ]
  }
}
```

---

#### `PUT /storefront/auth/profile`
🔑 Sanctum — Update profil pelanggan

**Request Body:**
| Field | Tipe | Wajib | Keterangan |
| :--- | :--- | :--- | :--- |
| `name` | string | Tidak | Nama lengkap |
| `email` | string | Tidak | Alamat email (format email) |

**Response 200:** Data profil pelanggan terbaru

---

#### `PUT /storefront/auth/change-password`
🔑 Sanctum — Ubah kata sandi

**Request Body:**
| Field | Tipe | Wajib | Keterangan |
| :--- | :--- | :--- | :--- |
| `current_password` | string | * | Kata sandi saat ini |
| `new_password` | string | * | Kata sandi baru |

**Response 200:** `{ "message": "Kata sandi berhasil diperbarui." }`

**Response 422:** Password lama salah / tidak memenuhi syarat

---

#### `POST /storefront/auth/logout`
🔑 Sanctum — Logout dan revoke token aktif

**Response 200:** `{ "message": "Berhasil logout." }`

---

#### `GET /storefront/orders`
🔑 Sanctum — Riwayat pesanan pelanggan

**Query Parameters:**
| Parameter | Tipe | Keterangan |
| :--- | :--- | :--- |
| `status` | string | `active` \| `completed` \| `cancelled` |
| `per_page` | integer | Default: 15 |

**Response 200:** Laravel Pagination dengan array OrderResource

---

#### `GET /storefront/orders/{id}`
🔑 Sanctum — Detail pesanan pelanggan

**Path Params:** `id` integer* — ID pesanan

**Response 200:** `{ "data": <OrderResource> }`

**Response 403:** Pesanan bukan milik pelanggan ini

---

#### `GET /storefront/orders/{id}/invoice`
🔑 Sanctum — Download invoice pesanan

**Response 200:** `Content-Type: text/html`

---

#### `POST /storefront/orders/{id}/cancel`
🔑 Sanctum — Batalkan pesanan

**Path Params:** `id` integer* — ID pesanan

**Request Body:**
| Field | Tipe | Wajib | Keterangan |
| :--- | :--- | :--- | :--- |
| `note` | string | Tidak | Alasan pembatalan |

**Response 200:** OrderResource dengan status `cancelled`

**Response 422:** Pesanan tidak dapat dibatalkan (status sudah `installation_in_progress` atau `completed`)

---

## ═══════════════════════════════════════
## BAGIAN B — Admin Internal API
## ═══════════════════════════════════════

> **Prasyarat semua endpoint:** Header `Authorization: Bearer <admin_token>` + role `admin`

---

### B1. Autentikasi Admin

---

#### `POST /auth/login`
🔓 Public — Login admin

**Request Body:**
| Field | Tipe | Wajib | Keterangan |
| :--- | :--- | :--- | :--- |
| `email` | string | * | Email admin |
| `password` | string | * | Password admin |

**Response 200:**
```json
{ "access_token": "string", "token_type": "Bearer" }
```

**Response 401:** Kredensial tidak valid

---

#### `POST /auth/logout`
🛡️ Admin — Logout admin

**Response 200:** `{ "message": "Berhasil logout." }`

---

### B2. Kategori Barang Inventaris

---

#### `GET /categories`
**Response 200:** Array kategori `[{ "id", "name", "code", "created_at" }]`

#### `POST /categories`
| Field | Tipe | Wajib |
| :--- | :--- | :--- |
| `name` | string | * |
| `code` | string | * |

**Response 201:** `{ "data": <CategorySchema> }`

#### `GET /categories/{id}`
**Response 200:** `{ "data": <CategorySchema> }` | **404** jika tidak ada

#### `PUT /categories/{id}`
Sama dengan POST. **Response 200:** `{ "data": <CategorySchema> }`

#### `DELETE /categories/{id}`
**Response 200:** `{ "message": "Kategori berhasil dihapus." }`

---

### B3. Supplier

---

#### `GET /suppliers`
**Response 200:** Array supplier

#### `POST /suppliers`
| Field | Tipe | Wajib | Keterangan |
| :--- | :--- | :--- | :--- |
| `name` | string | * | Nama supplier |
| `contact_person` | string | Tidak | Nama kontak |
| `phone` | string | Tidak | Nomor telepon |
| `email` | string | Tidak | Email |
| `address` | string | Tidak | Alamat |

**Response 201:** `{ "data": <SupplierSchema> }`

#### `GET /suppliers/{id}` | `PUT /suppliers/{id}` | `DELETE /suppliers/{id}`
CRUD standar. Response 404 jika tidak ditemukan.

---

### B4. Barang Inventaris (Items)

---

#### `GET /items`
**Response 200:** Array barang + stok

#### `POST /items`
| Field | Tipe | Wajib | Keterangan |
| :--- | :--- | :--- | :--- |
| `category_id` | integer | * | ID kategori |
| `supplier_id` | integer | Tidak | ID supplier |
| `code` | string | * | Kode unik barang |
| `name` | string | * | Nama barang |
| `unit` | string | * | Satuan (Pcs, Unit, dll.) |
| `min_stock` | integer | Tidak | Batas stok minimum |
| `purchase_price` | number | Tidak | Harga beli |

**Response 201:** `{ "data": <ItemSchema> }`

#### `GET /items/{id}` | `PUT /items/{id}` | `DELETE /items/{id}`
CRUD standar.

---

### B5. Brand Katalog

---

#### `GET /brands`
**Response 200:** Array brand katalog

#### `POST /brands`
| Field | Tipe | Wajib |
| :--- | :--- | :--- |
| `name` | string | * |
| `slug` | string | * |
| `logo` | file (multipart) | Tidak |

**Response 201:** `{ "data": <BrandSchema> }`

#### `PUT /brands/{id}` | `DELETE /brands/{id}`
CRUD standar.

---

### B6. Produk Katalog

---

#### `GET /products`
**Response 200:** Paginated produk katalog

#### `POST /products`
| Field | Tipe | Wajib | Keterangan |
| :--- | :--- | :--- | :--- |
| `brand_id` | integer | * | ID brand |
| `name` | string | * | Nama produk |
| `slug` | string | Tidak | Slug (auto-generate jika kosong) |
| `product_type` | string | * | `wireless` \| `analog` \| dll. |
| `description` | string | Tidak | Deskripsi produk |
| `specifications` | object | Tidak | Spesifikasi teknis (JSON) |
| `is_featured` | boolean | Tidak | Tampil di beranda |

**Response 201:** `{ "data": <ProductSchema> }`

#### `PUT /products/{id}` | `DELETE /products/{id}`
CRUD standar.

---

### B7. Varian Produk

---

#### `GET /products/{product}/variants`
**Path Params:** `product` integer* — ID produk

**Response 200:** Array varian dari produk tersebut

#### `POST /products/{product}/variants`
| Field | Tipe | Wajib | Keterangan |
| :--- | :--- | :--- | :--- |
| `sku` | string | * | SKU unik varian |
| `name` | string | * | Nama varian |
| `variant_type` | string | * | `unit` \| `installation_package` |
| `price` | number | * | Harga jual |
| `installation_included` | boolean | Tidak | Default: false |

**Response 201:** `{ "data": <VariantSchema> }`

#### `PUT /variants/{variant}` | `DELETE /variants/{variant}`
CRUD standar. Path param: `variant` integer* — ID varian.

---

### B8. Gambar Produk

---

#### `POST /products/{product}/images`
**Content-Type:** `multipart/form-data`

| Field | Tipe | Wajib | Keterangan |
| :--- | :--- | :--- | :--- |
| `image` | file | * | File gambar (jpg, png, webp, maks 2MB) |
| `is_primary` | integer | Tidak | `1` = gambar utama, `0` = tidak |

**Response 201:** `{ "data": { "id": "integer", "path": "string", "is_primary": "boolean" } }`

---

#### `PUT /images/{image}`
| Field | Tipe | Wajib |
| :--- | :--- | :--- |
| `is_primary` | boolean | * |

**Response 200:** `{ "data": <ImageSchema> }`

---

#### `DELETE /images/{image}`
**Response 200:** `{ "message": "Gambar berhasil dihapus." }`

---

### B9. Fitur Highlight Produk

---

#### `POST /products/{product}/features`
| Field | Tipe | Wajib |
| :--- | :--- | :--- |
| `title` | string | * |
| `description` | string | * |

**Response 201:** `{ "data": { "id": "integer", "title": "string", "description": "string" } }`

#### `PUT /features/{feature}` | `DELETE /features/{feature}`
CRUD standar. `feature` = ID fitur.

---

### B10. Bundle BOM Varian (Komponen)

Menghubungkan varian produk e-commerce ke barang inventaris fisik.

---

#### `GET /variants/{variant}/components`
**Response 200:**
```json
{ "data": [{ "id": "integer", "item_id": "integer", "item_name": "string", "quantity": "integer" }] }
```

#### `POST /variants/{variant}/components`
| Field | Tipe | Wajib | Keterangan |
| :--- | :--- | :--- | :--- |
| `item_id` | integer | * | ID barang inventaris |
| `quantity` | integer | * | Jumlah barang per varian |

**Response 201:** `{ "data": <ComponentSchema> }`

#### `PUT /components/{component}`
| Field | Tipe | Wajib |
| :--- | :--- | :--- |
| `quantity` | integer | * |

#### `DELETE /components/{component}`
**Response 200:** `{ "message": "Komponen berhasil dihapus." }`

---

### B11. Operasi Stok

---

#### `POST /stock-in`
Catat penerimaan barang dari supplier.

| Field | Tipe | Wajib | Keterangan |
| :--- | :--- | :--- | :--- |
| `item_id` | integer | * | ID barang |
| `quantity` | integer | * | Jumlah barang masuk (min: 1) |
| `movement_date` | date | * | Tanggal transaksi (YYYY-MM-DD) |
| `reference` | string | Tidak | Nomor PO / referensi |
| `note` | string | Tidak | Catatan tambahan |
| `price` | number | Tidak | Harga beli per unit |

**Response 201:** `{ "message": "Stok berhasil ditambahkan.", "data": <StockMovementSchema> }`

---

#### `POST /stock-out`
Catat pengeluaran barang dari gudang.

| Field | Tipe | Wajib | Keterangan |
| :--- | :--- | :--- | :--- |
| `item_id` | integer | * | ID barang |
| `quantity` | integer | * | Jumlah barang keluar (min: 1) |
| `movement_date` | date | * | Tanggal transaksi |
| `reference` | string | Tidak | Nomor SO / referensi |
| `note` | string | Tidak | Catatan tambahan |

**Response 201:** `{ "message": "Stok berhasil dikurangi.", "data": <StockMovementSchema> }`

**Response 422:** Stok tidak mencukupi

---

#### `GET /stock-movements`
Audit trail pergerakan stok.

**Response 200:**
```json
{
  "data": [
    {
      "id": "integer", "item_id": "integer", "item_name": "string",
      "type": "enum: in | out", "quantity": "integer", "price": "number | null",
      "movement_date": "date", "reference": "string | null", "note": "string | null",
      "stock_after": "integer", "created_at": "datetime"
    }
  ]
}
```

---

### B12. Dashboard Analytics

---

#### `GET /dashboard`
🛡️ Admin — Ringkasan penjualan & inventaris

**Query Parameters:**
| Parameter | Tipe | Default | Keterangan |
| :--- | :--- | :--- | :--- |
| `period` | string | `this_month` | `today` \| `this_week` \| `this_month` \| `custom` |
| `date_from` | date | — | Wajib jika `period=custom` |
| `date_to` | date | — | Wajib jika `period=custom` |

**Response 200:**
```json
{
  "period": { "type": "string", "from": "datetime", "to": "datetime" },
  "sales": {
    "total_sales": "number",
    "total_orders": "integer",
    "completed_orders": "integer",
    "pending_orders": "integer",
    "cancelled_orders": "integer",
    "sales_by_date": [{ "date": "date", "count": "integer", "total": "number" }]
  },
  "inventory": {
    "total_items": "integer",
    "total_categories": "integer",
    "total_suppliers": "integer",
    "total_stock": "integer",
    "total_stock_reserved": "integer",
    "total_available_stock": "integer",
    "stock_in_count": "integer",
    "stock_in_value": "number",
    "stock_out_count": "integer",
    "stock_out_value": "number"
  }
}
```

---

### B13. Manajemen Pesanan Admin

---

#### `GET /admin/orders`
🛡️ Admin — List semua pesanan

**Query Parameters:**
| Parameter | Tipe | Keterangan |
| :--- | :--- | :--- |
| `status` | string | `awaiting_payment` \| `order_received` \| `installation_in_progress` \| `completed` \| `cancelled` \| `payment_expired` |
| `payment_status` | string | `pending` \| `paid` \| `failed` \| `expired` \| `cancelled` |
| `search` | string | Kode pesanan / nama / email / telepon |
| `per_page` | integer | 1–100, default: 15 |

**Response 200:** Laravel Pagination berisi OrderResource

---

#### `GET /admin/orders/{order}`
🛡️ Admin — Detail satu pesanan

**Path Params:** `order` integer* — ID pesanan

**Response 200:** `{ "data": <OrderResource lengkap> }`

---

#### `PATCH /admin/orders/{order}/status`
🛡️ Admin — Perbarui status pesanan

**Path Params:** `order` integer* — ID pesanan

**Request Body:**
| Field | Tipe | Wajib | Keterangan |
| :--- | :--- | :--- | :--- |
| `status` | string | * | Status baru (lihat transisi yang diizinkan) |
| `note` | string | Tidak | Catatan perubahan status (maks 500 karakter) |

**Transisi Status yang Diizinkan:**
| Status Saat Ini | Status Tujuan yang Valid |
| :--- | :--- |
| `awaiting_payment` | `cancelled` |
| `order_received` | `installation_in_progress`, `cancelled` |
| `installation_in_progress` | `completed` |

**Response 200:**
```json
{ "message": "Status pesanan berhasil diperbarui.", "data": "<OrderResource>" }
```

**Response 422:** Transisi status tidak diizinkan

---

#### `GET /admin/orders/{order}/invoice`
🛡️ Admin — Download invoice pesanan

**Response 200:** `Content-Type: text/html` — File HTML Invoice

---

### B14. Import Data Batch

---

#### `POST /import/supplier`
🛡️ Admin — Import data supplier dari Excel

**Content-Type:** `multipart/form-data`

| Field | Tipe | Wajib | Keterangan |
| :--- | :--- | :--- | :--- |
| `file` | file | * | File Excel (.xlsx, .xls) |

**Response 200:** `{ "message": "Import berhasil.", "data": { "imported": "integer", "failed": "integer" } }`

---

#### `POST /import/item`
🛡️ Admin — Import master barang inventaris dari Excel

**Content-Type:** `multipart/form-data` | Field: `file` (Excel)*

---

#### `POST /import/price`
🛡️ Admin — Import pricelist barang dari Excel

**Content-Type:** `multipart/form-data` | Field: `file` (Excel)*

---

## Ringkasan Endpoint

### Storefront (Public & Customer)
| Method | Path | Auth | Fungsi |
| :--- | :--- | :--- | :--- |
| POST | `/auth/login` | Public | Login admin |
| POST | `/auth/logout` | Sanctum | Logout admin |
| GET | `/storefront/home` | Public | Beranda |
| GET | `/storefront/products` | Public | Katalog produk |
| GET | `/storefront/products/{slug}` | Public | Detail produk |
| GET | `/storefront/categories` | Public | List kategori |
| GET | `/storefront/brands` | Public | List brand |
| GET | `/storefront/company-profile` | Public | Profil perusahaan |
| GET | `/storefront/services` | Public | Daftar layanan |
| POST | `/storefront/otp/send` | Public | Kirim OTP WhatsApp |
| POST | `/storefront/otp/verify` | Public | Verifikasi OTP |
| POST | `/storefront/auth/login-otp` | Public | Login/daftar via OTP |
| GET | `/storefront/cart` | Public/Sanctum | Lihat keranjang |
| POST | `/storefront/cart/items` | Public/Sanctum | Tambah ke keranjang |
| PUT | `/storefront/cart/items/{id}` | Public/Sanctum | Update qty item |
| DELETE | `/storefront/cart/items/{id}` | Public/Sanctum | Hapus item |
| POST | `/storefront/checkout` | Public/Sanctum | Checkout pesanan |
| GET | `/storefront/orders/track/{code}` | Public | Lacak pesanan |
| GET | `/storefront/orders/track/{code}/invoice` | Public | Invoice publik |
| POST | `/storefront/payments/webhook` | Public | Webhook payment |
| GET | `/storefront/payments/{reference}` | Public/Sanctum | Status payment |
| GET | `/storefront/auth/me` | Sanctum | Profil saya |
| PUT | `/storefront/auth/profile` | Sanctum | Update profil |
| PUT | `/storefront/auth/change-password` | Sanctum | Ganti password |
| POST | `/storefront/auth/logout` | Sanctum | Logout customer |
| GET | `/storefront/orders` | Sanctum | Riwayat pesanan |
| GET | `/storefront/orders/{id}` | Sanctum | Detail pesanan |
| GET | `/storefront/orders/{id}/invoice` | Sanctum | Invoice pesanan |
| POST | `/storefront/orders/{id}/cancel` | Sanctum | Batalkan pesanan |

### Admin Internal
| Method | Path | Auth | Fungsi |
| :--- | :--- | :--- | :--- |
| GET/POST | `/categories` | Admin | CRUD kategori |
| GET/PUT/DELETE | `/categories/{id}` | Admin | Detail/edit/hapus kategori |
| GET/POST | `/suppliers` | Admin | CRUD supplier |
| GET/PUT/DELETE | `/suppliers/{id}` | Admin | Detail/edit/hapus supplier |
| GET/POST | `/items` | Admin | CRUD barang inventaris |
| GET/PUT/DELETE | `/items/{id}` | Admin | Detail/edit/hapus barang |
| GET/POST | `/brands` | Admin | CRUD brand katalog |
| PUT/DELETE | `/brands/{id}` | Admin | Edit/hapus brand |
| GET/POST | `/products` | Admin | CRUD produk |
| PUT/DELETE | `/products/{id}` | Admin | Edit/hapus produk |
| GET/POST | `/products/{product}/variants` | Admin | List/tambah varian |
| PUT/DELETE | `/variants/{variant}` | Admin | Edit/hapus varian |
| POST | `/products/{product}/images` | Admin | Upload gambar produk |
| PUT/DELETE | `/images/{image}` | Admin | Edit/hapus gambar |
| POST | `/products/{product}/features` | Admin | Tambah fitur produk |
| PUT/DELETE | `/features/{feature}` | Admin | Edit/hapus fitur |
| GET/POST | `/variants/{variant}/components` | Admin | BOM list/tambah |
| PUT/DELETE | `/components/{component}` | Admin | Edit/hapus komponen BOM |
| POST | `/stock-in` | Admin | Stok masuk |
| POST | `/stock-out` | Admin | Stok keluar |
| GET | `/stock-movements` | Admin | Riwayat pergerakan stok |
| GET | `/dashboard` | Admin | Dashboard analytics |
| GET | `/admin/orders` | Admin | List semua pesanan |
| GET | `/admin/orders/{order}` | Admin | Detail pesanan |
| PATCH | `/admin/orders/{order}/status` | Admin | Update status pesanan |
| GET | `/admin/orders/{order}/invoice` | Admin | Invoice pesanan |
| POST | `/import/supplier` | Admin | Import supplier Excel |
| POST | `/import/item` | Admin | Import barang Excel |
| POST | `/import/price` | Admin | Import pricelist Excel |

---

*API Contract ini mencerminkan implementasi backend Hablun CCTV terkini. Tidak ada endpoint teknisi dalam sistem ini.*
