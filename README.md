# SIMPEG

SIMPEG adalah aplikasi pengelolaan data kepegawaian yang mempertahankan tabel, relasi, dan alur kerja dari aplikasi legacy. Versi migrasi memakai Laravel 13, Inertia.js, React, Tailwind CSS, dan MySQL.

## Struktur aplikasi

```text
.
├── app/                 # Controller, middleware, service, model, dan policy
├── bootstrap/           # Bootstrap Laravel
├── config/              # Konfigurasi aplikasi
├── database/            # Migration, factory, dan seeder
├── public/              # Document root, aset publik, dan hasil build
├── resources/
│   ├── css/              # Stylesheet aplikasi
│   ├── js/               # React, Inertia, komponen, dan halaman
│   ├── templates/        # Template XLSX payroll
│   └── views/            # Shell Blade
├── routes/               # Route web dan console
├── storage/              # Log, cache, session, dan file aplikasi
├── tests/                # Test fitur dan unit
├── artisan
├── composer.json
├── package.json
└── vite.config.js
```

SIMPEG adalah aplikasi mandiri berbasis Laravel 13, Inertia.js, React, Tailwind CSS, dan MySQL. Aplikasi mengikuti struktur standar Laravel dari root project; source React berada di `resources/js`. Database SIMPEG tetap dipetakan oleh model Laravel tanpa mengganti tabel atau alur bisnis.

## Fitur sistem

### Autentikasi dan hak akses

- Login memakai ID user atau ID pegawai.
- Login lokal memakai data `tb_user` dan dapat meningkatkan hash password legacy secara bertahap.
- Integrasi SSO tersedia secara opsional melalui `app/Services/Sso/SsoClient.php`.
- Role yang didukung adalah `admin`, `superadmin`, `kepala`, dan `user`.
- Middleware memeriksa status aktif, role, ID user, ID pegawai, dan konteks kantor sebelum route dibuka.
- Matriks akses RBAC dapat diatur berdasarkan role, jabatan, atau ID pegawai.
- Aturan yang lebih spesifik mengalahkan aturan role, sehingga pengecualian akses tidak perlu mengubah kode menu.
- Logout menghapus session dan mengembalikan pengguna ke halaman login.

### Dashboard

- Dashboard admin dan superadmin.
- Dashboard kepala berdasarkan kantor atau unit kerja.
- Ringkasan pegawai aktif dan nonaktif.
- Komposisi gender.
- Jenjang pendidikan.
- Status kepegawaian.
- Tren pelanggaran.
- Distribusi masa kerja.
- Statistik kepegawaian berdasarkan jabatan.
- Daftar pensiun tahun berjalan.
- Ketersediaan jabatan eksekutif dan struktural.
- Kuota jabatan dapat dikonfigurasi per kode cabang dan jabatan dari menu admin.
- Dashboard menghitung kuota sesuai kantor/wilayah yang dipilih; kepala hanya melihat wilayah pada sesi akunnya.

Data dashboard berasal dari query backend dan database MySQL lokal.

### Data pegawai

- Daftar pegawai dengan pencarian, filter kantor, tab status, pagination, dan detail.
- Tambah dan ubah data pegawai sesuai hak akses.
- Import data pegawai melalui template `Template_Import_Pegawai_V2.xlsx` dengan tahap preview.
- Akses foto pegawai dari penyimpanan lokal.
- Pengangkatan pegawai.
- Penonaktifan atau mutasi pegawai.
- Pengelolaan ID pegawai terbaru dengan tetap mempertahankan nilai lama untuk kebutuhan relasi legacy.
- Import Diklat dan kegiatan pendidikan menggunakan template XLSX dengan preview sebelum penyimpanan.
- Ekspor laporan nominatif, formasi, diklat, biaya pendidikan, dan keadaan pegawai tersedia dalam format XLSX.

### Profil dan data personal

- Profil Saya untuk seluruh role yang memiliki akses ke profil.
- Data biodata, kontak, jabatan, kantor, dan informasi kepegawaian.
- Pendidikan terakhir.
- Diklat.
- Sertifikasi.
- Pelanggaran.
- Penghargaan, penugasan, dan pengangkatan sesuai data yang tersedia.

### Keluarga

- Data pasangan.
- Data anak.
- Data orang tua.
- Tambah, ubah, lihat, dan hapus mengikuti role serta kepemilikan data.

### Master data dan riwayat

- Jabatan dan master jabatan.
- Pendidikan dan referensi sekolah.
- Diklat dan biaya pendidikan.
- Sertifikasi.
- Golongan dan eselon.
- Mutasi.
- Cuti.
- Bahasa.
- DP3.
- Latihan jabatan.
- Penghargaan, penugasan, dan seminar.
- Kantor atau unit kerja.
- Pelanggaran dan hukuman.

### Laporan

- Laporan nominatif pegawai.
- Laporan keadaan pegawai.
- Laporan formasi jabatan.
- Rekap biaya diklat.
- Rekap biaya pendidikan.
- Export dan tampilan cetak untuk laporan yang tersedia.
- Payroll per tahun, bulan, dan kantor dengan rekap kantor serta rincian pegawai.
- Import payroll dari workbook XLSX melalui preview dan validasi sebelum penyimpanan.

### Administrasi aplikasi

- Manajemen user.
- Matriks akses user dengan ringkasan jumlah user, role, jabatan, dan aturan khusus.
- Pengaturan aplikasi dan logo.
- Pengaturan kuota jabatan dengan tiga input: kode_cabang, jabatan_id, dan kuota_pegawai.
- Notifikasi.
- Otorisasi atau approval perubahan data.
- Riwayat pengajuan milik pengguna yang sedang login.
- Pembatasan aksi edit, import, dan hapus untuk role read-only.

### Payroll

- Membaca komponen gaji pegawai dari sheet `SDM` dan rekap kantor/kredit dari `REKAP`.
- Menyimpan setiap periode sebagai batch terpisah; impor ulang membuat batch baru dan mempertahankan batch sebelumnya sebagai riwayat.
- Membatasi rincian payroll menurut izin matriks akses dan cakupan kantor akun.
- Membutuhkan migration payroll baru sebelum menu dapat digunakan. Migration disiapkan, tetapi belum dijalankan.

### Antarmuka

- React dan Inertia.js untuk halaman berbasis data.
- Tailwind CSS dengan komponen UI lokal bergaya shadcn/ui.
- Ikon `lucide-react`.
- Mode light dan dark.
- Layout responsif untuk desktop, tablet, dan mobile.
- Combobox pencarian untuk pilihan data yang panjang.
- Tabel mobile memakai container scroll agar halaman tidak melebar.
- State loading, kosong, error, validasi, dan feedback mengikuti response backend.

## Hak akses ringkas

| Role | Akses utama |
| --- | --- |
| `admin` | Mengelola data operasional, master data, laporan, user, dan pengaturan sesuai route yang tersedia. |
| `superadmin` | Melihat dashboard dan data sesuai kebijakan read-only yang diterapkan pada UI dan backend. |
| `kepala` | Melihat dashboard cabang, data pegawai, dan laporan dalam konteks kantor atau unit kerja. |
| `user` | Melihat Profil Saya dan data personal yang tersedia untuk akun tersebut. |

Route tetap memakai nama dan URL yang sudah berjalan di aplikasi migrasi. Perubahan layout tidak mengubah proses bisnis, nama tabel, relasi, atau data tersimpan.

## Menjalankan di lokal

Gunakan database MySQL lokal. Jangan arahkan `.env` ke database production.

Terminal 1, backend:

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

Terminal 2, frontend:

```bash
npm ci
npm run dev
```

Buka `http://localhost:8000`.

Untuk build aset frontend:

```bash
npm run build
```

Untuk menjalankan test backend:

```bash
php artisan test
```

## Konfigurasi lokal

Pasang dependency dari root project, salin `.env.example` menjadi `.env`, lalu isi `APP_KEY` dan koneksi MySQL lokal.

```bash
composer install
npm ci
cp .env.example .env
php artisan key:generate
```

Nilai penting:

```dotenv
APP_ENV=local
APP_URL=http://localhost:8000
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=masq2971_simpeg_dummy
DB_USERNAME=root
DB_PASSWORD=
SSO_ENABLED=false
```

SSO hanya aktif jika `SSO_ENABLED=true` dan `SSO_BASE_URL` diarahkan ke service yang memang disediakan untuk environment lokal. Jangan menyimpan token, password, atau credential production di repository.

## Database

Backend memetakan model Eloquent ke tabel legacy, termasuk tabel seperti:

- `tb_pegawai`
- `tb_user`
- `tb_jabatan`
- `tb_kantor`
- `tb_suamiistri`
- `tb_anak`
- `tb_ortu`
- `tb_pendidikan`
- `tb_diklat`
- `tb_sertifikasi`
- `tb_mutasi`
- `tb_hukuman`
- `tb_config`
- `tb_notifikasi`
- `tb_edit_pending`

Jangan menjalankan `migrate:fresh`, `db:wipe`, truncate, drop table, atau migration destruktif pada database legacy. Schema dan data dipertahankan.

RBAC memakai tabel tambahan `rbac_permissions` dan `rbac_access_rules`. Tabel legacy tidak diubah. Setelah meninjau migration dan memastikan backup database tersedia, jalankan migration tambahan dari root project:

    php artisan migrate

Konfigurasi kuota memakai tabel tambahan kuota_jabatan. Tabel ini tidak menambah atau mengubah kolom pada tb_master_jabatan; setiap baris menyimpan kombinasi unik kode_cabang + jabatan_id serta kuota_pegawai. Dashboard memakai konfigurasi baru ini jika tersedia dan tetap memakai kuota master untuk jabatan yang belum dikonfigurasi.

Matriks dapat dibuka dari menu Data User > Matriks akses. Aturan dapat disimpan untuk role, jabatan, atau ID pegawai. Jika tabel RBAC belum dimigrasikan, aplikasi tetap memakai hak akses legacy sebagai fallback.

Import pegawai tersedia dari menu Data Pegawai > Import Excel. Gunakan template resmi di `public/templates/Template_Import_Pegawai_V2.xlsx`; aplikasi menerima file `.xlsx`, memvalidasi 18 nama kolom, menampilkan preview, lalu menyimpan setelah dikonfirmasi. Menu import lain dan seluruh tombol ekspor laporan juga menggunakan `.xlsx` agar format file konsisten.

## Pemeriksaan sebelum perubahan

1. Pastikan `.env` memakai MySQL lokal.
2. Pastikan service MySQL lokal aktif.
3. Jalankan `php artisan test` dari root project.
4. Jalankan `npm run build` dari root project.
5. Uji login dan route sesuai role.
6. Uji perubahan data pada salinan atau database lokal, bukan production.

Repository ini berisi aplikasi SIMPEG Refactor mandiri; deploy dengan document root diarahkan ke folder `public/`.

## Deploy

Upload seluruh project di luar document root web, kemudian arahkan document root domain langsung ke folder `public/`. Jangan jadikan root repository sebagai document root. Siapkan `.env` di server (jangan commit), pasang dependency PHP, dan bangun aset:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Gunakan kredensial database production hanya pada `.env` server. Tinjau migration dan backup database sebelum menjalankan `php artisan migrate --force`; jangan gunakan `migrate:fresh` atau `db:wipe`.
