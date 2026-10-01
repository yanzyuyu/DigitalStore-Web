# Digital Store

Pasar produk digital multi-vendor. Penjual membuka toko dan mengunggah file, pembeli membayar lalu mengunduh, admin menyetujui toko, menerbitkan produk, dan memverifikasi transfer.

Pembayaran saat ini **manual** (bukti transfer + verifikasi admin). Driver Duitku dan Midtrans sudah ada di belakang antarmuka yang sama, belum diaktifkan.

## Isi repo

| Direktori | Isi |
| --- | --- |
| `backend/` | API Laravel 13, Sanctum, MySQL 8 |
| `frontend/` | SPA React 19, Vite 6, Tailwind CSS 4 |

Lisensi: [MIT](LICENSE).

## Menjalankan

Butuh PHP 8.3+, Composer 2, MySQL 8, Node.js 20+.

```sh
# API
cd backend
composer install
cp .env.example .env
php artisan key:generate
# isi DB_* dan ADMIN_* di .env, buat database, lalu:
php artisan migrate --seed
php artisan serve
```

```sh
# SPA, terminal lain
cd frontend
npm install
cp .env.example .env
npm run dev
```

- API: `http://localhost:8000`
- SPA: `http://localhost:5173` (proxy `/api` ke API)

Akun admin dari seeder memakai `ADMIN_EMAIL` dan `ADMIN_PASSWORD` di `backend/.env`. Jangan commit `.env`.

Detail ekstensi PHP, skema, dan permukaan API ada di [backend/README.md](backend/README.md). Halaman SPA ada di [frontend/README.md](frontend/README.md).

## Yang dijaga

- Uang dihitung dalam sen (integer), disimpan `DECIMAL(15,2)`. Checkout dan settlement berjalan di `DB::transaction`.
- Status pesanan hanya lewat state machine. Webhook pembayaran idempoten dan diverifikasi tanda tangannya.
- File produk di disk privat. Unduhan lewat endpoint yang mengecek pemilik, lalu diaudit.
- Peran dipisah middleware (`buyer` / `seller` / `admin`) plus policy per record. Admin tidak bisa didaftarkan sendiri.
- Harga tidak diterima dari klien. Total dihitung ulang di server.

## Uji

```sh
cd backend && php artisan test
cd frontend && npm run build && npx eslint src --max-warnings 0
```
