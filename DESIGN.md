# SIMPEG UI Design Direction

Status: design direction untuk migrasi Laravel 13, Inertia.js, React, Tailwind CSS, shadcn/ui, dan MySQL.

Dokumen ini menjadi sumber arah visual untuk UI SIMPEG. Ia melengkapi `AGENTS.md` dan dipakai bersama filter Anti-Slop resmi. Dokumen ini bukan izin untuk menambah fitur, route, data, atau workflow baru.

Referensi resmi:

- [shadcn/ui documentation](https://ui.shadcn.com/docs)
- [Anti-Slop repository](https://github.com/miqdadbadjuber/anti-slop)

Sumber arah visual: dua referensi UI yang diberikan pemilik produk, yaitu struktur modular dashboard pada Gambar 1 dan palet warm premium pada Gambar 2. Referensi dipakai untuk mengambil prinsip komposisi dan warna, bukan untuk menyalin identitas produk lain.

## Design Read

### Produk

SIMPEG adalah aplikasi operasional untuk pengelolaan data kepegawaian. Pengguna datang untuk memeriksa, memperbarui, mencari, dan melaporkan data pegawai. UI harus terasa dapat dipercaya, cepat dipindai, dan dekat dengan pekerjaan administrasi sehari-hari.

### Pengguna utama

- Admin mengelola data pegawai, master data, laporan, user, dan pengaturan aplikasi.
- Kepala melihat data pegawai dan laporan sesuai unit atau cabangnya.
- Pegawai mengelola dan melihat profil serta riwayat data personal yang memang tersedia untuk perannya.

### Pekerjaan yang harus didukung

1. Menemukan data pegawai berdasarkan nama, NIK, ID pegawai, jabatan, atau kantor.
2. Memahami ringkasan kepegawaian berdasarkan data nyata dari database legacy.
3. Menindaklanjuti data yang memiliki konsekuensi operasional, seperti pegawai nonaktif, pelanggaran, masa pensiun, atau jabatan vacant.
4. Membuka laporan atau detail tanpa kehilangan konteks menu dan role pengguna.

### Focal point dashboard admin

Fokus pertama adalah jumlah pegawai aktif dan status data yang memerlukan perhatian. Setelah itu pengguna membaca komposisi, tren, dan statistik jabatan. Daftar pensiun serta ketersediaan jabatan berada setelah analisis karena keduanya mendukung keputusan tindak lanjut.

### Identity motif

SIMPEG memakai kontras antara frame navigasi biru-hitam yang tenang dan sinyal emas untuk keadaan aktif atau tindakan utama. Motif ini mengingatkan pengguna bahwa sistem adalah alat kerja yang serius, tetapi tetap memiliki penanda visual yang hangat dan mudah dikenali.

## Liveliness dials

- `ENERGY: 3/3` - kontras dark ink, warm canvas, dan gold accent memberi karakter tanpa neon atau dekorasi berlebih.
- `RHYTHM: 2/3` - layout bervariasi secukupnya antara ringkasan, grid metrik, analisis chart, dan tabel operasional.
- `MOTION: 1/3` - motion hanya dipakai untuk membuka drawer, accordion, dropdown, fokus, dan perubahan state yang membantu orientasi.

Tidak ada endless pulse, floating animation, bounce, atau staggered fade sebagai dekorasi.

## Component architecture: shadcn/ui first

### Prinsip

shadcn/ui diperlakukan sebagai distribusi source code komponen yang terbuka dan dapat dikomposisikan, bukan paket komponen hitam yang harus diikuti tanpa perubahan. Komponen lokal harus tetap dapat dibaca, diuji, dan disesuaikan dengan workflow SIMPEG.

Target struktur:

```text
backend/
├── app/                     # route handler, service, middleware, model, dan policy
├── resources/views/         # shell Blade untuk Inertia
└── routes/                  # route web yang dipakai workflow

frontend/
├── components.json
└── src/
    ├── Components/ui/       # komponen shadcn/ui lokal yang benar-benar dipakai
    ├── Components/          # komponen domain dan shell legacy
    └── Pages/               # halaman Inertia berdasarkan workflow
```

Status implementasi tahap ini: `frontend/components.json` dan primitive lokal `frontend/src/Components/ui` sudah tersedia. Shell, dashboard admin, dashboard cabang, chart operasional, tabel operasional, dan login sudah memakai primitive tersebut. Halaman workflow lain tetap dipertahankan dan akan dipindahkan bertahap saat modulnya dikerjakan.

### Pemetaan komponen

| Kebutuhan SIMPEG | Komponen shadcn/ui yang diprioritaskan | Aturan penggunaan |
| --- | --- | --- |
| Shell desktop dan mobile | `Sidebar`, `Sheet`, `ScrollArea` | Sidebar desktop tetap, mobile menjadi drawer yang dapat ditutup dengan tombol dan `Escape`. |
| Navigasi menu bertingkat | `Sidebar`, `Collapsible`, `NavigationMenu` | Hanya tampilkan menu dengan route nyata dan sesuai role. |
| Tombol aksi | `Button` | Label menyebut tindakan nyata, misalnya `Daftar pegawai` atau `Laporan nominatif`. |
| Kartu ringkasan | `Card` | Dipakai untuk hierarki data yang memang perlu dipindai, bukan untuk membungkus setiap elemen. |
| Pencarian pegawai | `Input`, `Label`, `Command` bila pencarian perlu pilihan | Pencarian harus terhubung ke route dan query backend yang sudah tersedia. |
| Profil pengguna | `DropdownMenu`, `Avatar` berbasis inisial | Tidak membuat foto avatar baru. Gunakan foto legacy bila tersedia atau inisial sebagai placeholder jujur. |
| Status proses | `Badge` | Hanya untuk status nyata seperti hadir, menunggu approval, ditolak, vacant, atau status pensiun. |
| Tabel operasional | `Table`, `Pagination` | Kolom ditentukan oleh keputusan kerja pengguna, bukan kolom generik. |
| Filter halaman | `Tabs`, `Select`, `Combobox` | Pertahankan nilai query, route, dan semantik filter legacy. |
| Dialog konfirmasi | `Dialog`, `AlertDialog` | Hanya untuk workflow yang memang membutuhkan konfirmasi, terutama tindakan destruktif. |
| Feedback Inertia | `Alert`, `Sonner` atau pola toast yang disetujui | Flash message harus berasal dari response nyata, bukan pesan sukses buatan. |
| Data sedang dimuat | `Skeleton` | Hanya tampil selama request berlangsung dan mengikuti bentuk konten yang benar-benar dimuat. |
| Data kosong atau gagal | `Empty`, `Alert` | Jelaskan penyebab dan tindakan berikutnya, misalnya filter diperlebar atau data ditambahkan melalui workflow yang tersedia. |

### Aturan implementasi shadcn/ui

- Tambahkan hanya komponen yang dibutuhkan oleh workflow yang sedang dikerjakan.
- Pertahankan API komponen sederhana dan composable. Variasi visual diletakkan pada variant class, bukan override acak di setiap halaman.
- Gunakan helper `cn` atau padanan yang disepakati ketika komponen membutuhkan penggabungan class.
- Jangan memasukkan komponen demo, blok contoh, atau komponen yang belum memiliki konsumen nyata.
- Jangan mengganti route, nama prop Inertia, validasi, query, atau role hanya karena bentuk komponen berubah.
- Komponen domain seperti `EmployeeTable`, `RetirementTable`, dan `PositionAvailabilityTable` boleh mengomposisikan komponen shadcn/ui, tetapi tetap memahami data legacy yang dirender.

## Visual language

### Palette

Palet aktif dibatasi pada tiga warna inti, satu aksen, dan warna semantik untuk menyampaikan status data.

| Token | Nilai | Fungsi | Alasan |
| --- | --- | --- | --- |
| `ink` | `#131822` | teks utama, sidebar, tombol dark | Memberi kontras kuat dan rasa sistem kerja yang stabil. |
| `ink-soft` | `#1E2634` | gradasi sidebar dan tombol dark | Memisahkan navigasi dari kanvas tanpa membuat seluruh halaman gelap. |
| `canvas` | `#EDE4D5` sampai `#D8CCA8` | latar halaman warm radial | Menjaga suasana hangat dari referensi UI dan membedakan area kerja dari chrome navigasi. |
| `surface` | `#F7F5F0` dan putih | kontainer utama, card, tabel | Membuat data mudah dibaca di atas kanvas warm. |
| `accent-gold` | `#F5CE59` dan `#FAD873` | state aktif dan aksi utama | Menjadi satu sinyal visual yang konsisten untuk fokus dan tindakan penting. |

Status semantik hanya dipakai ketika nilai status memang ada di data:

- Emerald: disetujui atau hadir.
- Amber: menunggu approval atau perhatian yang belum selesai.
- Rose: ditolak, alpha, atau risiko yang perlu ditindaklanjuti.
- Sky: izin atau cuti.

Warna semantik tidak boleh dipakai sebagai dekorasi atau statistik fiktif.

Tema korporat tersedia sebagai pilihan tampilan tambahan tanpa mengubah tema terang atau gelap. Tema ini memakai `#263285` sebagai warna utama untuk navigasi, tombol, dan tautan; serta `#eb983c` sebagai aksen untuk hover, fokus, dan penanda aktif. Permukaan tetap terang agar tabel dan form mudah dibaca.

### Gradient, blur, dan shadow

- Radial warm gradient hanya untuk kanvas halaman. Ia memberi orientasi spasial dan identitas SIMPEG, bukan hiasan di setiap section.
- Dark gradient hanya untuk sidebar dan tombol dark karena keduanya adalah area navigasi atau aksi dengan prioritas tinggi.
- Gold gradient hanya untuk state aktif dan aksi utama. Jangan menyebarkannya ke semua icon, label, border, dan badge.
- `backdrop-blur` dibatasi pada shell utama dan utility header yang perlu memisahkan chrome dari konten. Card data harus tetap terbaca tanpa bergantung pada blur.
- Shadow besar hanya untuk shell utama atau drawer yang berada di atas konten. Card biasa memakai elevation tipis agar hierarki tetap tenang.
- Glow hanya boleh muncul sebagai focus accent yang singkat. Tidak ada glow simultan pada card, button, badge, icon, dan background.

### Shape and spacing

- Shell utama: radius besar `32px` untuk menandai satu area kerja.
- Section dan card analisis: radius `16px` sampai `24px` untuk grouping data.
- Input dan button: radius `12px` sampai `16px` agar nyaman disentuh tanpa membuat semua elemen menjadi pill.
- Badge status: pill hanya karena status adalah label ringkas yang perlu dibaca sebagai state.
- Tap target minimum: `44px` untuk button, link penting, input, pagination, dan kontrol menu.
- Spacing mengikuti skala Tailwind yang konsisten, tetapi section utama boleh memiliki jarak berbeda untuk memisahkan ringkasan, analisis, dan tindak lanjut.

### Typography

Gunakan system sans yang sudah berjalan di aplikasi: `ui-sans-serif`, system-ui, dan fallback platform. Alasannya adalah keterbacaan lintas macOS, Windows, dan mobile tanpa menambah dependency font yang tidak dibutuhkan.

- Heading memakai ukuran bertahap dengan bobot normal sampai medium. Hindari font tebal sebagai hiasan dan jangan memakai monospace sebagai dekorasi.
- Body copy memakai kalimat pendek dan konkret dalam Bahasa Indonesia.
- Gunakan kapitalisasi kalimat yang natural. Jangan memakai uppercase atau letter spacing lebar untuk memaksa hierarki visual.
- NIK, ID pegawai, kode jabatan, dan data teknis boleh memakai treatment monospace hanya jika membantu membedakan identifier dari nama manusia.
- Tidak memakai em dash dalam UI copy.

### Iconography

`lucide-react` dipakai karena icon dapat diimpor sebagai komponen, diberi label aksesibilitas, dan cocok dengan struktur komponen React. Ia bukan dekorasi default.

Icon wajib relevan dengan data atau tindakan:

- `UsersRound`: data pegawai atau kelompok pengguna.
- `GraduationCap`: pendidikan dan pelatihan.
- `BriefcaseBusiness`: jabatan dan formasi.
- `CalendarClock`: daftar pensiun atau tanggal yang perlu diperhatikan.
- `ShieldAlert`: pelanggaran atau risiko kepegawaian.
- `FileBarChart2`: laporan.
- `Search`: pencarian.
- `Menu` dan `X`: navigasi mobile.

Gunakan stroke sekitar `1.75` sampai `1.8` sebagai baseline. Jangan menggunakan sparkle, star, robot, orb, atau icon lain yang tidak menjelaskan pekerjaan pengguna. Icon-only control wajib memiliki label yang dapat diakses dan tooltip bila konteks visual tidak cukup.

## Layout and information hierarchy

### Shell

- Desktop memakai sidebar gelap selebar sekitar `18rem` untuk menu yang selalu tersedia.
- Utility header menampilkan konteks halaman, pencarian pegawai atau NIK, notifikasi nyata, dan profil user.
- Mobile memakai drawer atau `Sheet` dengan overlay, tombol buka yang mudah dijangkau, tombol tutup, dan dukungan `Escape`.
- Main content memiliki kanvas warm dan satu shell kerja utama. Jangan membuat setiap halaman terasa seperti landing page.

### Dashboard admin

Urutan section mengikuti kebutuhan kerja admin, bukan template dashboard:

1. Greeting dan dua aksi yang benar-benar tersedia: daftar pegawai dan laporan nominatif.
2. Metrik aktual dari backend: total pegawai, nonaktif, pelanggaran, dan diklat.
3. Statistik masa kerja untuk memahami distribusi tenure.
4. Komposisi gender dan jenjang pendidikan.
5. Status kepegawaian dan tren pelanggaran.
6. Statistik kepegawaian berdasarkan jabatan.
7. Daftar pensiun tahun berjalan.
8. Ketersediaan jabatan eksekutif dan struktural.

Chart hanya dipakai jika menjawab pertanyaan kerja yang jelas. Judul chart harus menyebut objek dan periode, bukan `Overview` atau `Performance` tanpa konteks.

### Menu dan route

Menu berasal dari route dan role yang sudah ada. Jangan menambahkan tab Penggajian, Kehadiran, atau Kinerja sebelum route, props, validasi, dan workflow-nya benar-benar tersedia.

Navigasi admin saat ini mencakup:

- Dashboard.
- Data Pegawai, termasuk tambah, pengangkatan, import, dan penonaktifan.
- Master Data, termasuk keluarga, jabatan, pendidikan, pelatihan, biaya pendidikan, dan sertifikasi.
- Laporan kepegawaian, keadaan pegawai, formasi jabatan, pelatihan, dan daftar diklat.
- Data User dan Pengaturan Aplikasi.

Kepala dan pegawai hanya menerima menu yang sesuai dengan role dan route yang tersedia. Link tanpa tujuan nyata adalah defect, bukan placeholder visual.

## Responsive rules

- Tidak boleh ada page-level horizontal overflow.
- Grid satu kolom pada mobile, dua kolom pada tablet bila konten masih terbaca, dan multi-kolom pada desktop bila hierarki datanya mendukung.
- Tabel lebar memakai scroll container yang jelas dan tidak memaksa seluruh halaman melebar.
- Kolom tabel memprioritaskan field yang menentukan tindakan pengguna.
- Sidebar mobile tidak menutup focus indicator dan dapat ditutup dengan tombol, overlay, link navigasi, atau `Escape`.
- Header dapat mengecil tanpa memotong nama user, label, atau tombol penting.
- Test minimal pada lebar sekitar `360px`, `440px`, `768px`, `1024px`, dan desktop lebar.
- Semua state penting dapat digunakan dengan keyboard dan screen reader.

## Data, copy, and states

### Data integrity

- Angka dashboard, chart, tabel, dan countdown hanya boleh berasal dari props Inertia atau query backend yang sudah ada.
- Tidak boleh membuat delta, persentase, feed aktivitas, avatar, nama, jabatan, atau klaim kinerja yang tidak memiliki sumber.
- Schema MySQL, nama tabel legacy, relasi, dan data tersimpan tidak berubah karena keputusan visual.

### Copy

- Gunakan Bahasa Indonesia yang ringkas dan operasional.
- CTA menyebut hasil tindakan, misalnya `Daftar pegawai`, `Laporan nominatif`, `Simpan perubahan`, atau `Import Excel`.
- Hindari buzzword pemasaran dan label yang tidak menjelaskan state.
- Jangan menggunakan em dash dalam copy.

### Required UI states

Setiap data view harus memiliki:

- Loading state yang menyebut data yang sedang dimuat.
- Empty state yang menjelaskan mengapa data kosong dan, bila ada, tindakan berikutnya.
- Error state yang menjelaskan bagian yang gagal tanpa membocorkan detail teknis sensitif.
- Filtered-empty state yang berbeda dari belum ada data sama sekali.
- Disabled, focus, validation, dan success state untuk form atau action yang relevan.

## Anti-Slop operating rules

Anti-Slop adalah filter kualitas, bukan pengganti arah desain. Setiap perubahan UI harus lulus tujuan visual dan fungsi berikut:

- Tidak ada angka, statistik, testimonial, nama, atau link palsu.
- Tidak ada menu yang mengarah ke route yang belum ada.
- Tidak ada button, dropdown, icon-only control, atau form yang tidak memiliki perilaku nyata.
- Tidak ada halaman dengan hanya happy path. Empty, loading, dan error state harus dipikirkan sejak komponen dibuat.
- Gradient, blur, shadow, radius, icon, dan motion harus memiliki alasan satu baris.
- Tidak mengandalkan bento grid, fake terminal, pricing template, hero landing page, atau filler section.
- Tidak memilih `lucide-react` hanya karena tampak modern. Setiap icon harus menjelaskan objek atau tindakan.
- Tidak menggunakan foto atau asset orang baru tanpa instruksi eksplisit. Inisial atau asset legacy adalah placeholder yang jujur.
- Tidak mengubah workflow legacy demi mengejar bentuk visual referensi.

## Decision log

| Keputusan | Alasan satu baris |
| --- | --- |
| Dark sidebar | Menjaga menu tetap terbaca dan memisahkan navigasi dari area data. |
| Warm radial canvas | Memberi identitas SIMPEG yang hangat tanpa memakai gradient AI default biru-ungu. |
| Gold active state | Membuat satu sinyal visual untuk lokasi aktif dan tindakan prioritas. |
| System sans | Menjaga keterbacaan dan menghindari dependency font yang tidak memberi manfaat operasional. |
| shadcn/ui local source | Memungkinkan komponen disesuaikan, diaudit, dan dipelihara tanpa API library tertutup. |
| Legacy palette adapter | Menyamakan halaman workflow yang belum dipindahkan tanpa mengubah markup, route, atau perilaku legacy. |
| Lucide icons yang semantik | Menjelaskan jenis data atau tindakan, bukan mengisi ruang kosong. |
| Chart berbasis pertanyaan | Mencegah chart menjadi dekorasi dan membantu admin melakukan tindak lanjut. |
| Tabel dengan filter dan pagination | Mempertahankan cara kerja legacy sambil mengurangi beban pemindaian data. |
| Mobile drawer | Mempertahankan semua menu tanpa membuat header dan konten sempit. |
| Motion minimal | Menjaga fokus pada data dan hanya memberi feedback saat state berubah. |

## Delivery Gate

Jalankan checklist ini sebelum setiap delivery UI. Status harus ditulis sebagai `PASS` atau `FAIL` berdasarkan bukti, bukan asumsi.

### Hard Gate

- [ ] R-02: Tidak ada em dash pada UI copy.
- [ ] R-03: Tidak ada page-level overflow atau layout pecah pada breakpoint yang diuji.
- [ ] R-17 dan R-38: Semua angka, klaim, row, dan link berasal dari data atau route nyata.
- [ ] R-24 dan R-26: Semua navigasi dan kontrol memiliki tujuan atau perilaku yang dapat diverifikasi.
- [ ] R-25 dan R-32: Kontras, focus indicator, keyboard navigation, dan label aksesibilitas lulus.
- [ ] R-27: Loading, empty, filtered-empty, error, validation, dan success state tersedia sesuai kebutuhan.
- [ ] R-35: Build dijalankan dan click-through direkam untuk kontrol interaktif serta mobile breakpoint.

### Purpose Gate

- [ ] R-01: Gradient dan warna memiliki alasan hierarki atau identitas.
- [ ] R-04: Icon relevan dengan data atau tindakan.
- [ ] R-06: Typography dipilih untuk karakter SIMPEG dan keterbacaan.
- [ ] R-10, R-12, R-13: Blur, shadow, dan glow berada dalam dosis yang diperlukan.
- [ ] R-14 dan R-31: Variasi card, spacing, dan section memiliki alasan satu baris.

### Liveliness

- [ ] Dials `ENERGY`, `RHYTHM`, dan `MOTION` tetap konsisten dengan implementasi.
- [ ] Setiap screen memiliki focal point yang jelas.
- [ ] Gold accent dipakai sebagai satu aksen terarah, bukan di semua komponen.
- [ ] Motif dark ink, warm canvas, dan gold active state tetap terasa spesifik untuk SIMPEG.

### Craftsmanship and quality locks

- [ ] C-1 sampai C-5 lulus: intentional, fungsional, berbasis konten nyata, tahan terhadap state, dan evidence over claims.
- [ ] Tidak ada clone visual dari produk populer.
- [ ] Tidak ada template dashboard yang dipakai hanya karena terlihat umum.
- [ ] Tidak ada perubahan schema, data production, atau workflow tanpa persetujuan.

