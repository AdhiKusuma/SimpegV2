import { Head, Link, useForm } from '@inertiajs/react';
import LegacyPageShell from '../../Components/LegacyPageShell';

export default function DiklatImport() {
    const form = useForm({ file_xlsx: null });

    function submit(event) {
        event.preventDefault();
        form.post('/diklat/import', { forceFormData: true });
    }

    return (
        <LegacyPageShell title="Import Data Diklat">
            <Head title="Import Data Diklat" />
            <div className="mx-auto max-w-3xl">
                <div className="mb-6"><Link href="/diklat" className="text-sm font-bold text-blue-600 hover:text-blue-700">← Kembali ke Diklat</Link><p className="mt-4 mb-2 text-sm font-semibold uppercase tracking-[0.18em] text-blue-600">Pengembangan Pegawai</p><h1 className="text-3xl font-extrabold tracking-tight text-slate-900">Import data diklat</h1><p className="mt-2 text-slate-500">Unggah file XLSX untuk menambah data atau memperbarui data berdasarkan pegawai, nama diklat, dan tahun.</p></div>
                <section className="simpeg-form-card">
                    <div className="rounded-xl border border-blue-100 bg-blue-50 p-4 text-sm text-blue-900"><h2 className="font-extrabold">Format dan validasi import</h2><p className="mt-2 leading-6">Gunakan template XLSX resmi dengan kolom <strong>ID Pegawai (ID/NIK), Nama Diklat, Penyelenggara, Tempat, Biaya (Rp), Angkatan, Tahun, dan Tgl Awal Diklat</strong>. Format lama dengan header NIK, NAMA_DIKLAT, dan TGL_INPUT tetap diterima.</p><p className="mt-2 leading-6">Jika nama kegiatan, penyelenggara, dan tanggal cocok persis dengan master, data baru otomatis tertaut ke master dan mengikuti validasi kuota serta anggaran. Jika belum ada master yang cocok, data tetap disimpan melalui alur legacy.</p><a href="/diklat/import/template" download className="mt-4 inline-flex rounded-lg bg-blue-600 px-3 py-2 text-sm font-bold text-white hover:bg-blue-700">Unduh template XLSX</a></div>
                    <form onSubmit={submit} className="mt-6"><label htmlFor="file_xlsx" className="mb-2 block text-sm font-bold text-slate-800">File XLSX</label><input id="file_xlsx" name="file_xlsx" type="file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required onChange={(event) => form.setData('file_xlsx', event.target.files?.[0] || null)} className="block w-full rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm file:mr-4 file:rounded-lg file:border-0 file:bg-blue-100 file:px-3 file:py-2 file:font-bold file:text-blue-700" />{form.errors.file_xlsx && <p className="mt-2 text-sm text-red-600" role="alert">{form.errors.file_xlsx}</p>}<div className="mt-6 flex justify-end gap-3"><Link href="/diklat" className="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-600">Batal</Link><button type="submit" disabled={form.processing} className="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-60">{form.processing ? 'Memproses...' : 'Proses Import'}</button></div></form>
                </section>
            </div>
        </LegacyPageShell>
    );
}
