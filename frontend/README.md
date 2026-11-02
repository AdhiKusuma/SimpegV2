# SIMPEG frontend

`frontend/` berisi view layer SIMPEG berbasis React dan Inertia.js. Folder ini tidak mengakses database langsung dan tidak menyimpan logika bisnis inti.

## Isi folder

- `src/Pages`: halaman Inertia berdasarkan workflow SIMPEG.
- `src/Components`: shell, chart, combobox, theme toggle, dan komponen UI lokal.
- `src/css/app.css`: token tema, Tailwind CSS, responsive rules, dan state visual.
- `src/app.js`: bootstrap React dan resolver halaman Inertia.
- `vite.config.js`: build aset ke `../backend/public/build` dan dev server ke backend Laravel.

## Perintah utama

```bash
npm install
npm run dev
npm run build
```

Perubahan pada frontend tidak boleh mengubah route, prop Inertia, validasi, handler, role, atau nama field database tanpa persetujuan.
