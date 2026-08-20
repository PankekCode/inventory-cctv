# 📚 Dokumentasi Resmi REST API — Hablun CCTV E-Commerce & Inventory Management

Dokumentasi komprehensif REST API untuk **Sistem E-Commerce Hablun CCTV (Storefront)** dan **Sistem Manajemen Stok & Inventaris Internal (Admin Inventory Management)**.

---

## 📌 Informasi Umum & Arsitektur

### 1. Base URL & Header
- **Base URL:** `http://localhost:8000/api` (atau sesuai environment domain produksi)
- **Header Wajib:**
  ```http
  Content-Type: application/json
  Accept: application/json
  ```

### 2. Autentikasi Token (Laravel Sanctum)
Sebagian besar endpoint internal/admin dan area pelanggan membutuhkan token Sanctum.
- Header Request: `Authorization: Bearer <sanctum_token>`

### 3. Format Error Standard
| HTTP Code | Keterangan |
| :--- | :--- |
| `401 Unauthorized` | Token tidak valid, expired, atau Authorization header tidak ada |
| `403 Forbidden` | Akun tidak memiliki hak akses |
| `404 Not Found` | Resource / ID / Slug tidak ditemukan |
| `422 Unprocessable Entity` | Validasi input gagal |
| `500 Internal Server Error` | Kegagalan internal server |

Format response error 422:
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "phone": ["Format nomor telepon tidak valid."]
  }
}
```

### 4. Alur Status Pesanan

| Status | Keterangan |
| :--- | :--- |
| `awaiting_payment` | Menunggu pembayaran dari pelanggan |
| `order_received` | Pembayaran terkonfirmasi, pesanan diproses |
| `installation_in_progress` | Proses pemasangan sedang berlangsung |
| `completed` | Pemasangan selesai |
| `cancelled` | Pesanan dibatalkan |
| `payment_expired` | Batas waktu pembayaran habis |

**Transisi yang diizinkan:**
```
awaiting_payment       ──▶  cancelled
order_received         ──▶  installation_in_progress | cancelled
installation_in_progress ──▶  completed
```

---

## 🛍️ BAGIAN 1: Storefront E-Commerce API (Public & Customer App)

### 1. Katalog & Halaman Publik

#### 1.1 Beranda (`GET /storefront/home`)
- **Endpoint:** `GET /api/storefront/home`
- **Auth:** Public
- **Response (200):**
```json
{
  "data": {
    "banners": [{ "id": 1, "title": "Promo Paket CCTV", "image_path": "banners/promo.jpg", "cta_label": "Lihat", "cta_url": "/katalog" }],
    "categories": [{ "id": 1, "name": "Wireless Wi-Fi", "slug": "wireless", "image_path": "categories/wireless.jpg" }],
    "featured_products": { "data": [{ "id": 1, "name": "EZVIZ H6c 3MP", "slug": "ezviz-h6c-3mp", "starting_price": 458000 }] },
    "brands": [{ "id": 1, "name": "EZVIZ", "slug": "ezviz", "logo_path": "brands/ezviz.png" }]
  }
}
```

---

#### 1.2 Katalog Produk (`GET /storefront/products`)
- **Endpoint:** `GET /api/storefront/products`
- **Auth:** Public
- **Query Params:** `search`, `category`, `brand`, `type` (wireless/analog), `featured`, `min_price`, `max_price`, `sort` (latest/price_asc/price_desc/name_asc), `per_page`
- **Response (200):** Paginated list produk dengan brand dan images.

---

#### 1.3 Detail Produk (`GET /storefront/products/{slug}`)
- **Endpoint:** `GET /api/storefront/products/{slug}`
- **Auth:** Public
- **Response (200):**
```json
{
  "data": {
    "id": 1, "name": "EZVIZ H6c 3MP", "slug": "ezviz-h6c-3mp",
    "product_type": "wireless", "description": "Kamera 3MP Smart Home.",
    "brand": { "id": 1, "name": "EZVIZ", "slug": "ezviz" },
    "images": [{ "id": 1, "path": "products/ezviz-h6c.jpg", "is_primary": true }],
    "features": [{ "id": 1, "title": "Smart Motion Tracking", "description": "Deteksi gerak otomatis" }],
    "variants": [
      { "id": 10, "sku": "EZV-H6C-UNIT", "name": "Unit Saja", "variant_type": "unit", "price": "458000.00", "installation_included": false },
      { "id": 11, "sku": "EZV-H6C-PKT", "name": "Paket Pemasangan", "variant_type": "installation_package", "price": "650000.00", "installation_included": true }
    ]
  }
}
```

---

#### 1.4 Kategori (`GET /storefront/categories`)
- **Endpoint:** `GET /api/storefront/categories` | **Auth:** Public
- **Response:** `{ "data": [{ "id": 1, "name": "Wireless Wi-Fi", "slug": "wireless", "image_path": "..." }] }`

#### 1.5 Brand (`GET /storefront/brands`)
- **Endpoint:** `GET /api/storefront/brands` | **Auth:** Public
- **Response:** `{ "data": [{ "id": 1, "name": "EZVIZ", "slug": "ezviz", "logo_path": "..." }] }`

#### 1.6 Profil Perusahaan (`GET /storefront/company-profile`)
- **Endpoint:** `GET /api/storefront/company-profile` | **Auth:** Public
- **Response:** `{ "data": { "company_name": "Hablun CCTV", "about": "...", "contacts": { "phone": "...", "whatsapp": "...", "address": "..." } } }`

#### 1.7 Layanan (`GET /storefront/services`)
- **Endpoint:** `GET /api/storefront/services` | **Auth:** Public
- **Response:** `{ "data": [{ "id": 1, "name": "Jasa Pemasangan CCTV", "slug": "...", "description": "...", "whatsapp_url": "..." }] }`

---

### 2. Autentikasi WhatsApp OTP

#### 2.1 Kirim OTP (`POST /storefront/otp/send`)
- **Endpoint:** `POST /api/storefront/otp/send` | **Auth:** Public
- **Request:**
```json
{ "phone": "081234567890", "purpose": "guest_checkout" }
```
*`purpose`: `guest_checkout` | `customer_login` | `customer_register`*

- **Response (200):**
```json
{
  "message": "Kode OTP berhasil dikirim via WhatsApp.",
  "data": { "verification_id": "uuid-here", "phone": "+6281234567890", "expires_at": "2026-08-10T16:00:00.000000Z" }
}
```

---

#### 2.2 Verifikasi OTP (`POST /storefront/otp/verify`)
- **Endpoint:** `POST /api/storefront/otp/verify` | **Auth:** Public
- **Request:** `{ "verification_id": "uuid-here", "code": "123456", "purpose": "guest_checkout" }`
- **Response (200):** `{ "message": "...", "data": { "verification_id": "...", "phone": "...", "verified_at": "..." } }`

---

#### 2.3 Login / Registrasi OTP (`POST /storefront/auth/login-otp`)
- **Endpoint:** `POST /api/storefront/auth/login-otp` | **Auth:** Public
- **Request:** `{ "verification_id": "uuid-here", "name": "Budi Santoso", "password": "Password123" }`
- **Response (200):** `{ "message": "Autentikasi berhasil.", "data": { "user": { "id": 5, "name": "...", "role": "customer" }, "token": "1|abc..." } }`

---

### 3. Keranjang Belanja (Cart)

| Method | Endpoint | Auth | Keterangan |
| :--- | :--- | :--- | :--- |
| GET | `/api/storefront/cart` | Public/Guest | Lihat isi keranjang. Query: `guest_token` |
| POST | `/api/storefront/cart/items` | Public/Guest | Tambah item. Body: `product_variant_id`, `quantity`, `guest_token` |
| PUT | `/api/storefront/cart/items/{id}` | Public/Guest | Update qty. Body: `quantity`, `guest_token` |
| DELETE | `/api/storefront/cart/items/{id}` | Public/Guest | Hapus item. Query: `guest_token` |

**Response GET Cart (200):**
```json
{
  "data": {
    "id": 1, "public_id": "uuid-here", "guest_token": "guest_abc123",
    "items": [{ "id": 10, "product_variant_id": 11, "quantity": 1, "variant": { "name": "Paket Pemasangan", "price": "650000.00" } }]
  }
}
```

---

### 4. Checkout & Pelacakan Pesanan

#### 4.1 Checkout (`POST /storefront/checkout`)
- **Endpoint:** `POST /api/storefront/checkout`
- **Auth:** Optional (Guest wajib `verification_id`, payment hanya `qris`)
- **Request Body (Guest):**
```json
{
  "customer_name": "Budi Guest",
  "phone": "081234567890",
  "verification_id": "uuid-verified",
  "installation_address": "Jl. Merdeka No. 45",
  "installation_city": "Jakarta Selatan",
  "installation_date": "2026-08-15",
  "installation_time_slot": "09:00 - 11:00",
  "payment_method": "qris",
  "items": [{ "product_variant_id": 11, "quantity": 1 }]
}
```
*`payment_method` untuk user login: `qris` | `bank_transfer` | `gopay` | `ovo` | `shopeepay`*

- **Response (201):**
```json
{
  "message": "Pesanan berhasil dibuat.",
  "data": {
    "id": 101, "order_code": "HBL-260820-A7K3P9", "status": "awaiting_payment",
    "grand_total": "650000.00",
    "payments": [{ "gateway": "sandbox", "payment_url": "https://...", "qris_payload": "00020101...", "expires_at": "2026-08-10T17:00:00Z" }],
    "items": [{ "product_name": "EZVIZ H6c 3MP", "variant_name": "Paket Pemasangan", "quantity": 1, "unit_price": "650000.00", "line_total": "650000.00" }]
  }
}
```

---

#### 4.2 Lacak Pesanan Publik (`GET /storefront/orders/track/{code}`)
- **Endpoint:** `GET /api/storefront/orders/track/HBL-260820-A7K3P9`
- **Auth:** Public
- **Response (200):**
```json
{
  "data": {
    "order_code": "HBL-260820-A7K3P9", "customer_name": "Budi Guest",
    "status": "order_received", "payment_status": "paid", "grand_total": "650000.00",
    "status_histories": [
      { "status": "awaiting_payment", "title": "Menunggu Pembayaran", "note": "Pesanan dibuat.", "occurred_at": "..." },
      { "status": "order_received", "title": "Pesanan diterima", "note": "Pembayaran terkonfirmasi.", "occurred_at": "..." }
    ]
  }
}
```

---

#### 4.3 Invoice Publik (`GET /storefront/orders/track/{code}/invoice`)
- **Endpoint:** `GET /api/storefront/orders/track/{code}/invoice`
- **Response:** `Content-Type: text/html` — File HTML Invoice

---

### 5. Webhook & Payment

#### 5.1 Webhook Payment Gateway (`POST /storefront/payments/webhook`)
- **Endpoint:** `POST /api/storefront/payments/webhook`
- **Request:** `{ "gateway": "sandbox", "provider_reference": "PAY-REF-xxx", "status": "paid", "event_id": "EVT-xxx", "amount": 650000 }`
- **Response (200):** `{ "message": "Pembayaran berhasil dikonfirmasi.", "data": { "payment_id": 45, "status": "paid", "order_code": "HBL-260820-A7K3P9" } }`

#### 5.2 Status Pembayaran (`GET /storefront/payments/{reference}`)
- **Endpoint:** `GET /api/storefront/payments/{reference}`
- **Query (Guest):** `order_code=HBL-260820-A7K3P9` | **Auth (User):** Bearer token

---

### 6. Area Pelanggan Terdaftar

| Method | Endpoint | Auth | Keterangan |
| :--- | :--- | :--- | :--- |
| GET | `/api/storefront/auth/me` | Sanctum | Profil pelanggan + alamat |
| PUT | `/api/storefront/auth/profile` | Sanctum | Update `name`, `email` |
| PUT | `/api/storefront/auth/change-password` | Sanctum | `current_password`, `new_password` |
| POST | `/api/storefront/auth/logout` | Sanctum | Logout |
| GET | `/api/storefront/orders` | Sanctum | List pesanan. Query: `status` (active/completed/cancelled) |
| GET | `/api/storefront/orders/{id}` | Sanctum | Detail pesanan |
| GET | `/api/storefront/orders/{id}/invoice` | Sanctum | Invoice HTML |
| POST | `/api/storefront/orders/{id}/cancel` | Sanctum | Batalkan pesanan. Body: `note` |

---

## 🛠️ BAGIAN 2: Internal Inventory & Admin API

Seluruh endpoint Admin membutuhkan header `Authorization: Bearer <token>` dengan role **admin**.

### 1. Autentikasi Admin

| Method | Endpoint | Auth | Keterangan |
| :--- | :--- | :--- | :--- |
| POST | `/api/auth/login` | Public | Login admin. Body: `email`, `password`. Response: `access_token` |
| POST | `/api/auth/logout` | Sanctum | Logout admin |

---

### 2. Master Data

#### Kategori Barang (`/api/categories`) — CRUD lengkap
| Method | Endpoint | Body / Query |
| :--- | :--- | :--- |
| GET | `/api/categories` | — |
| POST | `/api/categories` | `{ "name": "DVR/NVR", "code": "DVR" }` |
| GET | `/api/categories/{id}` | — |
| PUT | `/api/categories/{id}` | `{ "name": "...", "code": "..." }` |
| DELETE | `/api/categories/{id}` | — |

#### Supplier (`/api/suppliers`) — CRUD lengkap
| Method | Endpoint | Body |
| :--- | :--- | :--- |
| GET | `/api/suppliers` | — |
| POST | `/api/suppliers` | `{ "name": "PT Dahua Indonesia", "contact_person": "Budi", "phone": "0812345" }` |
| GET | `/api/suppliers/{id}` | — |
| PUT | `/api/suppliers/{id}` | `{ "name": "...", ... }` |
| DELETE | `/api/suppliers/{id}` | — |

#### Barang Inventaris (`/api/items`) — CRUD lengkap
| Method | Endpoint | Body |
| :--- | :--- | :--- |
| GET | `/api/items` | — |
| POST | `/api/items` | `{ "category_id": 1, "supplier_id": 1, "code": "CAM-EZV-H6C", "name": "EZVIZ H6c", "unit": "Pcs", "min_stock": 5 }` |
| GET | `/api/items/{id}` | — |
| PUT | `/api/items/{id}` | `{ ... }` |
| DELETE | `/api/items/{id}` | — |

#### Brand Katalog (`/api/brands`) — CRUD lengkap
| Method | Endpoint | Body |
| :--- | :--- | :--- |
| GET | `/api/brands` | — |
| POST | `/api/brands` | `{ "name": "EZVIZ", "slug": "ezviz" }` |
| PUT | `/api/brands/{id}` | `{ "name": "...", "slug": "..." }` |
| DELETE | `/api/brands/{id}` | — |

#### Produk Katalog (`/api/products`) — CRUD lengkap
| Method | Endpoint | Body |
| :--- | :--- | :--- |
| GET | `/api/products` | — |
| POST | `/api/products` | `{ "brand_id": 1, "name": "EZVIZ H6c 3MP", "product_type": "wireless", "description": "..." }` |
| PUT | `/api/products/{id}` | `{ ... }` |
| DELETE | `/api/products/{id}` | — |

#### Varian Produk
| Method | Endpoint | Body |
| :--- | :--- | :--- |
| GET | `/api/products/{product}/variants` | — |
| POST | `/api/products/{product}/variants` | `{ "sku": "EZV-PKT", "name": "Paket Pemasangan", "variant_type": "installation_package", "price": 650000, "installation_included": true }` |
| PUT | `/api/variants/{variant}` | `{ ... }` |
| DELETE | `/api/variants/{variant}` | — |

---

### 3. Gambar & Fitur Produk

| Method | Endpoint | Content-Type | Body |
| :--- | :--- | :--- | :--- |
| POST | `/api/products/{product}/images` | multipart/form-data | `image` (file), `is_primary` (0/1) |
| PUT | `/api/images/{image}` | application/json | `{ "is_primary": true }` |
| DELETE | `/api/images/{image}` | — | — |
| POST | `/api/products/{product}/features` | application/json | `{ "title": "2K Resolution", "description": "..." }` |
| PUT | `/api/features/{feature}` | application/json | `{ "title": "...", "description": "..." }` |
| DELETE | `/api/features/{feature}` | — | — |

#### Bundle BOM Varian
| Method | Endpoint | Body |
| :--- | :--- | :--- |
| GET | `/api/variants/{variant}/components` | — |
| POST | `/api/variants/{variant}/components` | `{ "item_id": 1, "quantity": 1 }` |
| PUT | `/api/components/{component}` | `{ "quantity": 2 }` |
| DELETE | `/api/components/{component}` | — |

---

### 4. Operasi Stok

#### Stok Masuk (`POST /api/stock-in`)
```json
{ "item_id": 1, "quantity": 25, "movement_date": "2026-08-10", "reference": "PO-2026-0801", "note": "Restock dari EZVIZ" }
```

#### Stok Keluar (`POST /api/stock-out`)
```json
{ "item_id": 1, "quantity": 2, "movement_date": "2026-08-10", "reference": "SO-OFFLINE-01", "note": "Penjualan langsung" }
```

#### Riwayat Pergerakan (`GET /api/stock-movements`)
- Response: Paginated histori transaksi stok masuk & keluar.

#### Dashboard (`GET /api/dashboard`)
- **Query Params:** `period` (`today` | `this_week` | `this_month` | `custom`), `date_from`, `date_to`
- **Response:**
```json
{
  "period": { "type": "this_month", "from": "2026-08-01 00:00:00", "to": "2026-08-31 23:59:59" },
  "sales": { "total_sales": 12500000, "total_orders": 45, "completed_orders": 30, "pending_orders": 10, "cancelled_orders": 5 },
  "inventory": { "total_items": 150, "total_categories": 6, "total_suppliers": 5, "total_stock": 320, "total_available_stock": 305 }
}
```

---

### 5. Manajemen Pesanan Admin

#### List Pesanan (`GET /api/admin/orders`)
- **Query Params:** `status`, `payment_status`, `search`, `per_page`

#### Detail Pesanan (`GET /api/admin/orders/{order}`)
- **Response (200):** Full OrderResource (id, code, customer, items, payments, status_history)

#### Update Status (`PATCH /api/admin/orders/{order}/status`)
- **Request:** `{ "status": "installation_in_progress", "note": "Teknisi tiba di lokasi." }`
- **Transisi Status:**

| Dari | Ke (yang diizinkan) |
| :--- | :--- |
| `awaiting_payment` | `cancelled` |
| `order_received` | `installation_in_progress`, `cancelled` |
| `installation_in_progress` | `completed` |

- **Response (200):** `{ "message": "Status pesanan berhasil diperbarui.", "data": { ...OrderResource } }`

#### Download Invoice (`GET /api/admin/orders/{order}/invoice`)
- **Response:** `Content-Type: text/html` — File HTML Invoice

---

### 6. Import Data Batch Excel

| Method | Endpoint | Form Field | Keterangan |
| :--- | :--- | :--- | :--- |
| POST | `/api/import/supplier` | `file` | Import supplier dari Excel |
| POST | `/api/import/item` | `file` | Import master barang (`STOK BARANG.xlsx`) |
| POST | `/api/import/price` | `file` | Import pricelist (`PRICELIST.xlsx`) |

---

## ⚡ Pengujian API

File Postman Collection: `inventory-api.postman_collection.json`

Import ke Postman → File → Import → Pilih `inventory-api.postman_collection.json`.

---
*Versi dokumentasi ini tidak mencakup fitur penugasan teknisi (telah dihapus dari sistem).*
