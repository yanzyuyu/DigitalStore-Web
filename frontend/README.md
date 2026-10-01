# Digital Shop — Frontend

SPA pembeli, penjual, dan admin untuk API Laravel di `../backend`.

## Stack

React 19, Vite 6, Tailwind CSS 4, React Router 7, Zustand, Axios.

## Menjalankan

```sh
cd frontend
cp .env.example .env
npm install
npm run dev
```

Dev server di `http://localhost:5173`. Request `/api` di-proxy ke `http://localhost:8000`.

API harus sudah jalan (`php artisan serve` di `backend`) dan database ter-migrate. Akun admin dari seeder: `admin@digital-shop.test` / kata sandi di `ADMIN_PASSWORD`.

## Variabel

| Nama | Arti |
| --- | --- |
| `VITE_API_URL` | Kosong di lokal (pakai proxy). Isi origin penuh bila API beda host. |

Tidak ada rahasia di bundle. Token Sanctum disimpan di `localStorage` (`ds.token`) dan dikirim sebagai `Authorization: Bearer`. Respons 401 menghapus token dan mengarahkan ke `/masuk`.

## Area

| Path | Siapa | API |
| --- | --- | --- |
| `/`, `/produk`, `/toko` | publik | katalog |
| `/keranjang`, `/checkout` | pembeli | `POST /api/v1/checkout` |
| `/pesanan`, `/akun/unduhan` | login | pesanan dan unduhan privat |
| `/seller` | penjual | toko, produk, file, penjualan |
| `/admin` | admin | persetujuan toko, moderasi, verifikasi bayar |

Harga di keranjang hanya perkiraan. Total resmi dihitung server. Penjual tidak bisa menerbitkan produk sendiri. File produk tidak punya URL publik; unduhan lewat endpoint terotorisasi.

## Perintah

```sh
npm run dev
npm run build
npm run preview
npx eslint src --max-warnings 0
```
