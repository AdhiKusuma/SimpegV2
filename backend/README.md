# SIMPEG backend

`backend/` adalah aplikasi Laravel 13 yang menangani route, Inertia response, autentikasi, middleware role, business logic, model Eloquent legacy, validasi, upload, laporan, dan koneksi MySQL.

## Perintah utama

```bash
php artisan serve --host=127.0.0.1 --port=8000
php artisan test
```

Gunakan `.env` lokal. Backend tidak membuat schema baru untuk menggantikan tabel legacy dan tidak boleh diarahkan ke database production selama pengembangan.

## Batas tanggung jawab

- `app/Http/Controllers`: menerima request dan menyiapkan response.
- `app/Services`: integrasi dan proses yang digunakan lintas controller, termasuk SSO.
- `app/Models/Legacy`: mapping tabel dan relasi database legacy.
- `app/Http/Middleware`: autentikasi session dan pemeriksaan role.
- `routes/web.php`: route yang dipakai oleh halaman Inertia.
- `resources/views`: shell Blade yang memuat aplikasi Inertia.
- `public`: file publik dan hasil build frontend.

Source React dan CSS berada di folder `../frontend`.
