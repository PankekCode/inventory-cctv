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
Sistem mengembalikan format HTTP status code standar dengan body JSON error:
- **401 Unauthorized:** Token tidak valid, expired, atau header Authorization tidak ada.
- **403 Forbidden:** Akun tidak memiliki hak akses (misal: Customer mencoba akses Admin API).
- **404 Not Found:** Resource / ID / Slug yang dicari tidak ditemukan.
- **422 Unprocessable Entity:** Validasi input gagal. Format response:
  ```json
  {
    "message": "The given data was invalid.",
    "errors": {
      "phone": ["Format nomor telepon tidak valid."]
    }
  }
  ```
- **500 Internal Server Error:** Kegagalan internal pada server backend.

---

## 🛍️ BAGIAN 1: Storefront E-Commerce API (Public & Customer App)

### 1. Katalog & Halaman Publik

#### 1.1 Beranda / Landing Page (`GET /storefront/home`)
Mengambil data beranda publik meliputi banner promo aktif, kategori utama, produk unggulan (featured), dan brand mitra.

- **Endpoint:** `GET /api/storefront/home`
- **Autentikasi:** Public (Tidak perlu token)
- **Response (200 OK):**
```json
{
  "data": {
    "banners": [
      {
        "id": 1,
        "title": "Promo Paket CCTV 4 Kamera",
        "description": "Diskon instalasi gratis kawasan Jabodetabek",
        "image_path": "banners/promo-merdeka.jpg",
        "cta_label": "Lihat Promo",
        "cta_url": "/katalog?category=analog"
      }
    ],
    "categories": [
      {
        "id": 1,
        "name": "Kamera Wireless Wi-Fi",
        "slug": "wireless",
        "image_path": "categories/wireless.jpg"
      }
    ],
    "featured_products": {
      "data": [
        {
          "id": 1,
          "name": "EZVIZ H6c 3MP Smart Wi-Fi Camera",
          "slug": "ezviz-h6c-3mp",
          "product_type": "wireless",
          "starting_price": 458000,
          "average_rating": 4.8,
          "review_count": 12
        }
      ]
    },
    "brands": [
      {
        "id": 1,
        "name": "EZVIZ",
        "slug": "ezviz",
        "logo_path": "brands/ezviz.png"
      }
    ]
  }
}
```

---

#### 1.2 Katalog Produk & Filter (`GET /storefront/products`)
Daftar produk katalog dengan pencarian nama, filter kategori, brand, tipe produk, harga, dan urutan pagination.

- **Endpoint:** `GET /api/storefront/products`
- **Autentikasi:** Public
- **Query Parameters:**
  | Parameter | Tipe Data | Deskripsi | Example |
  | :--- | :--- | :--- | :--- |
  | `search` | string | Kata kunci nama/deskripsi | `ezviz` |
  | `category` | string | Slug kategori | `wireless` |
  | `brand` | string | Slug brand | `ezviz` |
  | `type` | string | Tipe produk (`wireless` / `analog`) | `wireless` |
  | `featured` | boolean | Filter produk unggulan (`true`/`false`) | `true` |
  | `min_price` | numeric | Batas harga terendah | `300000` |
  | `max_price` | numeric | Batas harga tertinggi | `1000000` |
  | `sort` | string | `latest`, `price_asc`, `price_desc`, `name_asc` | `latest` |
  | `per_page` | integer | Jumlah item per halaman (default: 12) | `12` |

- **Response (200 OK):**
```json
{
  "current_page": 1,
  "data": [
    {
      "id": 1,
      "brand_id": 1,
      "name": "EZVIZ H6c 3MP Smart Wi-Fi Camera",
      "slug": "ezviz-h6c-3mp",
      "product_type": "wireless",
      "starting_price": 458000,
      "average_rating": 4.8,
      "review_count": 15,
      "brand": {
        "id": 1,
        "name": "EZVIZ",
        "slug": "ezviz"
      },
      "images": [
        {
          "id": 1,
          "path": "products/ezviz-h6c.jpg",
          "is_primary": true
        }
      ]
    }
  ],
  "total": 24,
  "per_page": 12
}
```

---

#### 1.3 Detail Produk (`GET /storefront/products/{slug}`)
Mengambil detail produk berdasarkan `slug`, lengkap dengan daftar varian (unit saja / paket pemasangan), galeri gambar, fitur unggulan, dan ulasan.

- **Endpoint:** `GET /api/storefront/products/{slug}`
- **Autentikasi:** Public
- **Response (200 OK):**
```json
{
  "data": {
    "id": 1,
    "name": "EZVIZ H6c 3MP Smart Wi-Fi Camera",
    "slug": "ezviz-h6c-3mp",
    "product_type": "wireless",
    "description": "Kamera CCTV Wi-Fi Smart Home 3MP dengan rotasi 360 derajat.",
    "specifications": {
      "resolution": "3MP (2K)",
      "night_vision": "10 Meters",
      "storage": "MicroSD up to 256GB"
    },
    "average_rating": 4.8,
    "review_count": 15,
    "brand": { "id": 1, "name": "EZVIZ", "slug": "ezviz" },
    "images": [
      { "id": 1, "path": "products/ezviz-h6c-1.jpg", "is_primary": true }
    ],
    "features": [
      { "id": 1, "title": "Smart Motion Tracking", "description": "Deteksi gerak otomatis" }
    ],
    "variants": [
      {
        "id": 10,
        "sku": "EZV-H6C-UNIT",
        "name": "Unit Saja",
        "variant_type": "unit",
        "price": "458000.00",
        "installation_included": false
      },
      {
        "id": 11,
        "sku": "EZV-H6C-PKT",
        "name": "Paket Pemasangan",
        "variant_type": "installation_package",
        "price": "650000.00",
        "installation_included": true
      }
    ]
  }
}
```

---

#### 1.4 List Kategori Katalog (`GET /storefront/categories`)
- **Endpoint:** `GET /api/storefront/categories`
- **Autentikasi:** Public
- **Response (200 OK):**
```json
{
  "data": [
    { "id": 1, "name": "Wireless Wi-Fi", "slug": "wireless", "image_path": "categories/wireless.jpg" },
    { "id": 2, "name": "Paket Analog HD", "slug": "analog", "image_path": "categories/analog.jpg" }
  ]
}
```

---

#### 1.5 List Brand Partner (`GET /storefront/brands`)
- **Endpoint:** `GET /api/storefront/brands`
- **Autentikasi:** Public
- **Response (200 OK):**
```json
{
  "data": [
    { "id": 1, "name": "EZVIZ", "slug": "ezviz", "logo_path": "brands/ezviz.png" },
    { "id": 2, "name": "Hikvision", "slug": "hikvision", "logo_path": "brands/hikvision.png" },
    { "id": 3, "name": "Dahua", "slug": "dahua", "logo_path": "brands/dahua.png" },
    { "id": 4, "name": "IMOU", "slug": "imou", "logo_path": "brands/imou.png" },
    { "id": 5, "name": "HiLook", "slug": "hilook", "logo_path": "brands/hilook.png" }
  ]
}
```

---

#### 1.6 Profil Perusahaan (`GET /storefront/company-profile`)
- **Endpoint:** `GET /api/storefront/company-profile`
- **Autentikasi:** Public
- **Response (200 OK):**
```json
{
  "data": {
    "company_name": "Hablun CCTV",
    "about": "Penyedia jasa pemasangan dan penyedia perangkat CCTV berkualitas.",
    "vision": "Menjadi mitra sistem keamanan terbaik di Indonesia.",
    "contacts": {
      "phone": "081234567890",
      "whatsapp": "6281234567890",
      "address": "Jl. Utama No. 123, Jakarta"
    }
  }
}
```

---

#### 1.7 Layanan / Services (`GET /storefront/services`)
- **Endpoint:** `GET /api/storefront/services`
- **Autentikasi:** Public
- **Response (200 OK):**
```json
{
  "data": [
    {
      "id": 1,
      "name": "Jasa Pemasangan & Setup CCTV",
      "slug": "jasa-pemasangan",
      "description": "Instalasi kabel rapi, setting jaringan, dan edukasi aplikasi HP.",
      "whatsapp_url": "https://wa.me/6281234567890?text=Halo%20Admin"
    }
  ]
}
```

---

### 2. Autentikasi Nomor WhatsApp & OTP

#### 2.1 Kirim OTP via WhatsApp (`POST /storefront/otp/send`)
Mengirimkan 4-6 digit kode OTP ke nomor WhatsApp tujuan.

- **Endpoint:** `POST /api/storefront/otp/send`
- **Autentikasi:** Public
- **Request Body:**
```json
{
  "phone": "081234567890",
  "purpose": "guest_checkout"
}
```
*Nilai `purpose` yang valid:* `guest_checkout`, `customer_login`, `customer_register`.

- **Response (200 OK):**
```json
{
  "message": "Kode OTP berhasil dikirim via WhatsApp.",
  "data": {
    "verification_id": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d",
    "phone": "+6281234567890",
    "expires_at": "2026-08-10T16:00:00.000000Z"
  }
}
```

---

#### 2.2 Verifikasi Kode OTP (`POST /storefront/otp/verify`)
Memvalidasi kode OTP yang dimasukkan pelanggan.

- **Endpoint:** `POST /api/storefront/otp/verify`
- **Autentikasi:** Public
- **Request Body:**
```json
{
  "verification_id": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d",
  "code": "123456",
  "purpose": "guest_checkout"
}
```

- **Response (200 OK):**
```json
{
  "message": "Nomor WhatsApp berhasil diverifikasi.",
  "data": {
    "verification_id": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d",
    "phone": "+6281234567890",
    "verified_at": "2026-08-10T15:45:00.000000Z"
  }
}
```

---

#### 2.3 Login / Registrasi via OTP (`POST /storefront/auth/login-otp`)
Mendaftarkan pelanggan baru atau login menggunakan `verification_id` yang terverifikasi. Seluruh riwayat pesanan Guest terdahulu dengan nomor HP sama akan otomatis ditautkan ke akun ini.

- **Endpoint:** `POST /api/storefront/auth/login-otp`
- **Autentikasi:** Public
- **Request Body:**
```json
{
  "verification_id": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d",
  "name": "Budi Santoso",
  "password": "Password123"
}
```

- **Response (200 OK):**
```json
{
  "message": "Autentikasi berhasil.",
  "data": {
    "user": {
      "id": 5,
      "name": "Budi Santoso",
      "phone_e164": "+6281234567890",
      "phone_verified_at": "2026-08-10T15:45:00.000000Z",
      "role": "customer"
    },
    "token": "1|sanctum_token_string_here"
  }
}
```

---

### 3. Keranjang Belanja (Cart)

#### 3.1 Lihat Keranjang (`GET /storefront/cart`)
- **Endpoint:** `GET /api/storefront/cart`
- **Autentikasi:** Optional Sanctum / Guest token
- **Query Parameters:** `guest_token` (string, wajib jika guest)
- **Response (200 OK):**
```json
{
  "data": {
    "id": 1,
    "public_id": "c8a1b2c3-d4e5-4f6a-8b9c-0d1e2f3a4b5c",
    "guest_token": "guest_abc123",
    "items": [
      {
        "id": 10,
        "product_variant_id": 11,
        "quantity": 1,
        "variant": {
          "id": 11,
          "name": "Paket Pemasangan",
          "price": "650000.00",
          "product": { "name": "EZVIZ H6c 3MP" }
        }
      }
    ]
  }
}
```

---

#### 3.2 Tambah Item ke Keranjang (`POST /storefront/cart/items`)
- **Endpoint:** `POST /api/storefront/cart/items`
- **Request Body:**
```json
{
  "product_variant_id": 11,
  "quantity": 1,
  "guest_token": "guest_abc123"
}
```
- **Response (200 OK):** Menampilkan struktur data keranjang belanja terbaru.

---

#### 3.3 Update Jumlah Item (`PUT /storefront/cart/items/{id}`)
- **Endpoint:** `PUT /api/storefront/cart/items/{id}`
- **Request Body:**
```json
{
  "quantity": 2,
  "guest_token": "guest_abc123"
}
```

---

#### 3.4 Hapus Item Keranjang (`DELETE /storefront/cart/items/{id}`)
- **Endpoint:** `DELETE /api/storefront/cart/items/{id}`
- **Query Parameter:** `guest_token=guest_abc123`

---

### 4. Checkout & Pelacakan Pesanan (Tracking)

#### 4.1 Checkout Pesanan (`POST /storefront/checkout`)

##### 🚨 Aturan Pembayaran & Mode User:
1. **Mode Guest Checkout:**
   - Wajib melampirkan `verification_id` dari WhatsApp OTP (`purpose: guest_checkout`).
   - `payment_method` **wajib QRIS** (`"qris"`).
2. **Mode User Authenticated:**
   - Header Authorization Sanctum wajib dikirim.
   - Bebas memilih `payment_method`: `qris`, `bank_transfer`, `gopay`, `ovo`, `shopeepay`.

- **Endpoint:** `POST /api/storefront/checkout`
- **Headers:** `Authorization: Bearer <token>` (Opsional untuk Guest)
- **Request Body Contoh (Guest):**
```json
{
  "customer_name": "Budi Guest",
  "phone": "081234567890",
  "verification_id": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d",
  "installation_address": "Jl. Merdeka No. 45, Jakarta Selatan",
  "installation_city": "Jakarta Selatan",
  "installation_date": "2026-08-15",
  "installation_time_slot": "09:00 - 11:00",
  "payment_method": "qris",
  "items": [
    {
      "product_variant_id": 11,
      "quantity": 1
    }
  ]
}
```

- **Response (201 Created):**
```json
{
  "message": "Pesanan berhasil dibuat.",
  "data": {
    "id": 101,
    "unique_order_code": "HBL-2026-0001",
    "customer_name": "Budi Guest",
    "installation_address": "Jl. Merdeka No. 45, Jakarta Selatan",
    "installation_date": "2026-08-15",
    "installation_time_slot": "09:00 - 11:00",
    "status": "awaiting_payment",
    "payment_status": "pending",
    "payment_method": "qris",
    "subtotal": "650000.00",
    "tax_amount": "0.00",
    "grand_total": "650000.00",
    "payments": [
      {
        "gateway": "sandbox",
        "payment_url": "https://qris.habluncctv.com/pay/HBL-2026-0001",
        "qris_payload": "00020101021226670016ID.CO.QRIS...",
        "expires_at": "2026-08-10T17:00:00.000000Z"
      }
    ],
    "items": [
      {
        "product_name": "EZVIZ H6c 3MP Smart Wi-Fi Camera",
        "variant_name": "Paket Pemasangan",
        "quantity": 1,
        "unit_price": "650000.00",
        "line_total": "650000.00",
        "installation_included": true
      }
    ]
  }
}
```

---

#### 4.2 Lacak Pesanan Publik (`GET /storefront/orders/track/{code}`)
Melacak status pesanan secara publik menggunakan kode unik pesanan tanpa autentikasi.

- **Endpoint:** `GET /api/storefront/orders/track/{code}`
- **Contoh Request:** `GET /api/storefront/orders/track/HBL-2026-0001`
- **Response (200 OK):**
```json
{
  "data": {
    "unique_order_code": "HBL-2026-0001",
    "customer_name": "Budi Guest",
    "status": "awaiting_payment",
    "payment_status": "pending",
    "grand_total": "650000.00",
    "status_histories": [
      {
        "status": "awaiting_payment",
        "title": "Menunggu Pembayaran",
        "note": "Pesanan dibuat.",
        "created_at": "2026-08-10T15:45:00.000000Z"
      }
    ],
    "technician": null
  }
}
```

---

#### 4.3 Download Invoice PDF Publik (`GET /storefront/orders/track/{code}/invoice`)
Mengunduh file PDF faktur/invoice pesanan berdasarkan kode pesanan.

- **Endpoint:** `GET /api/storefront/orders/track/{code}/invoice`
- **Response Header:** `Content-Type: application/pdf`

---

### 5. Webhook Pembayaran & Detail Payment

#### 5.1 Webhook Payment Gateway (`POST /storefront/payments/webhook`)
Menerima notifikasi callback sistem pembayaran dari Payment Gateway.

- **Endpoint:** `POST /api/storefront/payments/webhook`
- **Request Body:**
```json
{
  "gateway": "sandbox",
  "provider_reference": "PAY-REF-998877",
  "status": "paid",
  "event_id": "EVT-1002938",
  "amount": 650000
}
```
*Catatan Status:* `paid`, `failed`.

- **Response (200 OK):**
```json
{
  "message": "Pembayaran berhasil dikonfirmasi.",
  "data": {
    "payment_id": 45,
    "provider_reference": "PAY-REF-998877",
    "status": "paid",
    "order_code": "HBL-2026-0001"
  }
}
```

---

#### 5.2 Detail Status Pembayaran (`GET /storefront/payments/{reference}`)
Mengecek status pembayaran berdasarkan `provider_reference`.

- **Endpoint:** `GET /api/storefront/payments/{reference}`
- **Query Parameter (Khusus Guest):** `order_code=HBL-2026-0001`
- **Headers (User Login):** `Authorization: Bearer <token>`
- **Response (200 OK):** Detail pembayaran beserta ringkasan order.

---

### 6. Area Pelanggan Terdaftar (Customer Authenticated)

#### 6.1 Detail Profil Saya (`GET /storefront/auth/me`)
- **Endpoint:** `GET /api/storefront/auth/me`
- **Headers:** `Authorization: Bearer <token>`
- **Response (200 OK):**
```json
{
  "data": {
    "id": 5,
    "name": "Budi Santoso",
    "phone_e164": "+6281234567890",
    "email": "budi@example.com",
    "addresses": [
      {
        "id": 1,
        "label": "Rumah Utama",
        "recipient_name": "Budi Santoso",
        "phone_e164": "+6281234567890",
        "address_line": "Jl. Merdeka No. 45",
        "city": "Jakarta Selatan",
        "is_default": true
      }
    ]
  }
}
```

---

#### 6.2 Update Profil Pelanggan (`PUT /storefront/auth/profile`)
- **Endpoint:** `PUT /api/storefront/auth/profile`
- **Headers:** `Authorization: Bearer <token>`
- **Request Body:**
```json
{
  "name": "Budi Santoso",
  "email": "budi.new@example.com"
}
```

---

#### 6.3 Ubah Kata Sandi (`PUT /storefront/auth/change-password`)
- **Endpoint:** `PUT /api/storefront/auth/change-password`
- **Headers:** `Authorization: Bearer <token>`
- **Request Body:**
```json
{
  "current_password": "Password123",
  "new_password": "NewPassword456"
}
```

---

#### 6.4 Riwayat Pesanan Saya (`GET /storefront/orders`)
- **Endpoint:** `GET /api/storefront/orders`
- **Headers:** `Authorization: Bearer <token>`
- **Query Parameter:** `status` (`active`, `completed`, `cancelled`)
- **Response (200 OK):** Paginated array dari pesanan milik user login.

---

#### 6.5 Detail Pesanan Saya (`GET /storefront/orders/{id}`)
- **Endpoint:** `GET /api/storefront/orders/{id}`
- **Headers:** `Authorization: Bearer <token>`

---

#### 6.6 Pembatalan Pesanan (`POST /storefront/orders/{id}/cancel`)
- **Endpoint:** `POST /api/storefront/orders/{id}/cancel`
- **Headers:** `Authorization: Bearer <token>`
- **Request Body:**
```json
{
  "note": "Perubahan jadwal instalasi"
}
```

---

#### 6.7 Logout Pelanggan (`POST /storefront/auth/logout`)
- **Endpoint:** `POST /api/storefront/auth/logout`
- **Headers:** `Authorization: Bearer <token>`

---

## 🛠️ BAGIAN 2: Internal Inventory & Admin API (Admin Management)

Seluruh endpoint Admin membutuhkan token Sanctum dengan role `admin`.

### 1. Autentikasi Admin

#### 1.1 Login Admin (`POST /auth/login`)
- **Endpoint:** `POST /api/auth/login`
- **Request Body:**
```json
{
  "email": "admin@inventory.test",
  "password": "password"
}
```
- **Response (200 OK):**
```json
{
  "access_token": "2|sanctum_token_string_here",
  "token_type": "Bearer"
}
```

---

#### 1.2 Logout Admin (`POST /auth/logout`)
- **Endpoint:** `POST /api/auth/logout`
- **Headers:** `Authorization: Bearer <token>`

---

### 2. Master Data Inventory & Katalog Admin

#### 2.1 Manajemen Kategori Barang Internal (`/categories`)
- `GET /api/categories` — List seluruh kategori barang
- `POST /api/categories` — Tambah kategori baru (`{"name": "DVR/NVR", "code": "DVR"}`)
- `GET /api/categories/{id}` — Detail kategori
- `PUT /api/categories/{id}` — Update kategori
- `DELETE /api/categories/{id}` — Hapus kategori

#### 2.2 Manajemen Supplier / Brand Internal (`/suppliers`)
- `GET /api/suppliers` — List supplier internal
- `POST /api/suppliers` — Tambah supplier (`{"name": "PT Dahua Indonesia", "contact_person": "Budi", "phone": "0812345"}`)
- `GET /api/suppliers/{id}` — Detail supplier
- `PUT /api/suppliers/{id}` — Update supplier
- `DELETE /api/suppliers/{id}` — Hapus supplier

#### 2.3 Master Items / Barang Inventaris (`/items`)
- `GET /api/items` — List barang inventaris stok
- `POST /api/items` — Tambah barang inventaris
```json
{
  "category_id": 1,
  "supplier_id": 1,
  "code": "CAM-EZV-H6C",
  "name": "EZVIZ H6c 3MP Camera",
  "unit": "Pcs",
  "min_stock": 5
}
```
- `GET /api/items/{id}` — Detail barang
- `PUT /api/items/{id}` — Update barang
- `DELETE /api/items/{id}` — Hapus barang

#### 2.4 Manajemen Brand Katalog (`/brands`)
- `GET /api/brands` — List brand katalog storefront
- `POST /api/brands` — Tambah brand katalog (`{"name": "EZVIZ", "slug": "ezviz"}`)
- `PUT /api/brands/{id}` — Update brand
- `DELETE /api/brands/{id}` — Hapus brand

#### 2.5 Manajemen Produk Katalog (`/products`)
- `GET /api/products` — List produk katalog
- `POST /api/products` — Tambah produk katalog
```json
{
  "brand_id": 1,
  "name": "EZVIZ H6c 3MP",
  "product_type": "wireless",
  "description": "Smart Indoor Camera"
}
```
- `PUT /api/products/{id}` — Update produk
- `DELETE /api/products/{id}` — Hapus produk

#### 2.6 Manajemen Varian Produk (`/variants`)
- `GET /api/products/{product}/variants` — List varian dari produk tertentu
- `POST /api/products/{product}/variants` — Tambah varian produk
```json
{
  "sku": "EZV-H6C-PKT",
  "name": "Paket Pemasangan",
  "variant_type": "installation_package",
  "price": 650000,
  "installation_included": true
}
```
- `PUT /api/variants/{variant}` — Update varian
- `DELETE /api/variants/{variant}` — Hapus varian

---

### 3. Galeri Gambar & Fitur Produk Admin

#### 3.1 Upload Gambar Produk (`POST /products/{product}/images`)
- **Endpoint:** `POST /api/products/{product}/images`
- **Headers:** `Authorization: Bearer <token>`, `Content-Type: multipart/form-data`
- **Form Data:**
  - `image`: File gambar (`jpg`, `png`, `webp`)
  - `is_primary`: `1` atau `0`

#### 3.2 Update Status Gambar (`PUT /images/{image}`)
- **Endpoint:** `PUT /api/images/{image}`
- **Request Body:** `{"is_primary": true}`

#### 3.3 Hapus Gambar (`DELETE /images/{image}`)
- **Endpoint:** `DELETE /api/images/{image}`

#### 3.4 Kelola Fitur Highlight Produk (`/products/{product}/features`)
- `POST /api/products/{product}/features` — Tambah fitur (`{"title": "2K Resolution", "description": "3MP Clear View"}`)
- `PUT /api/features/{feature}` — Update fitur
- `DELETE /api/features/{feature}` — Hapus fitur

#### 3.5 Component Bundle Varian (BOM) (`/variants/{variant}/components`)
Menghubungkan varian produk katalog e-commerce ke barang inventaris fisik (`items`).
- `GET /api/variants/{variant}/components` — List barang inventaris penyusun varian
- `POST /api/variants/{variant}/components` — Tambah komponen (`{"item_id": 1, "quantity": 1}`)
- `PUT /api/components/{component}` — Update kuantitas komponen
- `DELETE /api/components/{component}` — Hapus komponen dari varian

---

### 4. Transaksi & Pergerakan Stok (Inventory Operations)

#### 4.1 Transaksi Stok Masuk (`POST /stock-in`)
Mencatat penerimaan fisik barang dari supplier ke gudang inventaris.

- **Endpoint:** `POST /api/stock-in`
- **Headers:** `Authorization: Bearer <token>`
- **Request Body:**
```json
{
  "item_id": 1,
  "quantity": 25,
  "movement_date": "2026-08-10",
  "reference": "PO-2026-0801",
  "note": "Penerimaan restock dari Supplier EZVIZ"
}
```

---

#### 4.2 Transaksi Stok Keluar (`POST /stock-out`)
Mencatat pengeluaran fisik barang dari gudang (manual/offline).

- **Endpoint:** `POST /api/stock-out`
- **Headers:** `Authorization: Bearer <token>`
- **Request Body:**
```json
{
  "item_id": 1,
  "quantity": 2,
  "movement_date": "2026-08-10",
  "reference": "SO-OFFLINE-01",
  "note": "Penjualan langsung toko offline"
}
```

---

#### 4.3 Audit Trail Pergerakan Stok (`GET /stock-movements`)
- **Endpoint:** `GET /api/stock-movements`
- **Headers:** `Authorization: Bearer <token>`
- **Response (200 OK):** Histori lengkap transaksi barang masuk & keluar beserta sisa stok.

---

#### 4.4 Ringkasan Dashboard Inventory (`GET /dashboard`)
- **Endpoint:** `GET /api/dashboard`
- **Headers:** `Authorization: Bearer <token>`
- **Response (200 OK):**
```json
{
  "total_items": 150,
  "total_categories": 6,
  "total_suppliers": 5,
  "low_stock_items": 2
}
```

---

### 5. Manajemen Pesanan & Penugasan Teknisi Admin

#### 5.1 List Pesanan Admin (`GET /admin/orders` atau `GET /orders`)
- **Endpoint:** `GET /api/admin/orders`
- **Headers:** `Authorization: Bearer <token>`
- **Query Parameters:**
  - `status`: Filter status (`awaiting_payment`, `processing`, `technician_assigned`, `completed`, `cancelled`)
  - `payment_status`: Filter pembayaran (`pending`, `paid`, `failed`)
  - `search`: Kode order / nama pelanggan / email / no HP
  - `per_page`: Jumlah item per halaman (1–100)

---

#### 5.2 Update Status Pesanan (`PATCH /admin/orders/{order}/status`)
Mengubah alur status pesanan (misal: memproses pesanan atau menyelesaikan pemasangan).

- **Endpoint:** `PATCH /api/admin/orders/{order}/status`
- **Headers:** `Authorization: Bearer <token>`
- **Request Body:**
```json
{
  "status": "processing",
  "note": "Pesanan sedang disiapkan di gudang."
}
```

---

#### 5.3 Penugasan Teknisi (`POST /admin/orders/{order}/assign-technician`)
Menugaskan teknisi untuk jadwal instalasi pesanan.

- **Endpoint:** `POST /api/admin/orders/{order}/assign-technician`
- **Headers:** `Authorization: Bearer <token>`
- **Request Body:**
```json
{
  "technician_id": 2
}
```

---

#### 5.4 Download Faktur Admin (`GET /admin/orders/{order}/invoice`)
- **Endpoint:** `GET /api/admin/orders/{order}/invoice`
- **Headers:** `Authorization: Bearer <token>`
- **Response:** PDF Invoice File

---

### 6. Import Data Batch Excel

- **`POST /api/import/supplier`** — Import data supplier/brand dari spreadsheet Excel (`file` field).
- **`POST /api/import/item`** — Import master barang dari file `STOK BARANG.xlsx`.
- **`POST /api/import/price`** — Import pricelist barang dari file `PRICELIST.xlsx`.

---

## ⚡ Pengujian API & Postman Collection

File Postman Collection resmi telah tersedia di direktori proyek:
- File Collection: `inventory-api.postman_collection.json`
- Caranya: Buka Postman -> Import -> Pilih file `inventory-api.postman_collection.json`.

---
*Dokumentasi ini dibuat secara otomatis dan disesuaikan dengan arsitektur Laravel backend Hablun CCTV.*
