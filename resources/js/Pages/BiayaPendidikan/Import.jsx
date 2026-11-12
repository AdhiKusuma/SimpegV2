import { Head, Link, useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import LegacyPageShell from '../../Components/LegacyPageShell';

const currency = new Intl.NumberFormat('id-ID');

export default function BiayaPendidikanImport({ preview, previewToken }) {
    const uploadForm = useForm({ file_excel: null });
    const saveForm = useForm({ preview_token: previewToken || '' });
    const hasPreviewErrors = Array.isArray(preview) && preview.some((row) => Array.isArray(row.errors) && row.errors.length > 0);

    useEffect(() => {
        saveForm.setData('preview_token', previewToken || '');
    }, [previewToken]);

    function submitPreview(event) {
        event.preventDefault();
        uploadForm.post('/biaya-pendidikan/import/preview', { forceFormData: true, preserveScroll: true });
    }

    function submitImport(event) {
        event.preventDefault();
        saveForm.post('/biaya-pendidikan/import', { preserveScroll: true });
    }

    return (
        <LegacyPageShell title="Import Kegiatan Diklat">
            <Head title="Import Kegiatan Diklat" />
            <div className="mx-auto max-w-5xl">
                <div className="mb-6"><Link href="/biaya-pendidikan" className="text-sm font-bold text-blue-600 hover:text-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-600/20">Kembali ke daftar kegiatan</Link><p className="mt-4 mb-2 text-sm font-semibold uppercase tracking-[0.16em] text-blue-600">Pengembangan Pegawai</p><h1 className="text-3xl font-extrabold tracking-tight text-slate-900">Import kegiatan diklat</h1><p className="mt-2 text-slate-500">Unggah template sistem, periksa preview, lalu proses data baru dan data yang perlu diperbarui.</p></div>

                <section className="simpeg-form-card">
                    <div className="grid gap-4 border-b border-slate-100 pb-6 md:grid-cols-[1fr_auto] md:items-end"><div><label htmlFor="file_excel" className="mb-2 block text-sm font-bold text-slate-800">File XLSX</label><p className="mb-3 text-sm text-slate-500">Gunakan template resmi. Header lama dan header deskriptif dari template contoh tetap didukung.</p><input id="file_excel" name="file_excel" type="file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required onChange={(event) => uploadForm.setData('file_excel', event.target.files?.[0] || null)} className="block w-full rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm file:mr-4 file:rounded-lg file:border-0 file:bg-blue-100 file:px-3 file:py-2 file:font-bold file:text-blue-700" />{uploadForm.errors.file_excel && <p className="mt-2 text-sm text-red-600" role="alert">{uploadForm.errors.file_excel}</p>}</div><div className="flex flex-wrap gap-2"><a href="/biaya-pendidikan/template" className="inline-flex min-h-11 items-center rounded-lg border border-emerald-200 px-4 py-2.5 text-sm font-bold text-emerald-700 hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-600/20">Unduh template XLSX</a><button type="button" onClick={submitPreview} disabled={uploadForm.processing} className="inline-flex min-h-11 items-center rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-60 focus:outline-none focus:ring-2 focus:ring-blue-600/20">{uploadForm.processing ? 'Membaca file...' : 'Tampilkan preview'}</button></div></div>

                    {preview && <div className="mt-6"><div className="mb-4 flex flex-wrap items-center justify-between gap-3"><div><h2 className="text-lg font-extrabold text-slate-900">Preview data</h2><p className="mt-1 text-sm text-slate-500">Menampilkan maksimal 15 baris pertama. Seluruh baris akan diproses saat dikonfirmasi.</p>{hasPreviewErrors && <p className="mt-2 text-sm font-semibold text-red-600">Periksa baris yang ditandai sebelum melanjutkan.</p>}</div><form onSubmit={submitImport}><button type="submit" disabled={saveForm.processing || !previewToken || hasPreviewErrors} className="inline-flex min-h-11 items-center rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-700 disabled:opacity-60 focus:outline-none focus:ring-2 focus:ring-emerald-600/20">{saveForm.processing ? 'Memproses...' : 'Proses import dan update'}</button></form></div><input type="hidden" name="preview_token" value={saveForm.data.preview_token} readOnly />{saveForm.errors.preview_token && <p className="mb-3 text-sm text-red-600" role="alert">{saveForm.errors.preview_token}</p>}<div className="overflow-x-auto rounded-xl border border-slate-200"><table className="min-w-[1080px] w-full text-left text-sm"><thead className="border-b border-slate-100 text-xs uppercase tracking-wide text-slate-500"><tr><th className="px-5 py-4 font-semibold">Status</th><th className="px-5 py-4 font-semibold">Kategori</th><th className="px-5 py-4 font-semibold">Judul kegiatan</th><th className="px-5 py-4 font-semibold">Pihak</th><th className="px-5 py-4 font-semibold">Tanggal</th><th className="px-5 py-4 text-right font-semibold">SDM</th><th className="px-5 py-4 text-right font-semibold">Biaya</th><th className="px-5 py-4 font-semibold">Keterangan</th></tr></thead><tbody className="divide-y divide-slate-100">{preview.length === 0 ? <tr><td colSpan="8" className="px-5 py-12 text-center text-sm text-slate-500">Tidak ada baris data untuk ditampilkan.</td></tr> : preview.map((row, index) => <tr key={`${row.judul}-${row.tanggal}-${index}`} className="align-top transition hover:bg-slate-50"><td className={`px-5 py-4 font-semibold ${row.errors?.length ? 'text-red-600' : 'text-slate-700'}`}>{row.status}</td><td className="px-5 py-4 text-slate-600">{row.kode_kategori || '-'}</td><td className="px-5 py-4 font-semibold text-slate-800">{row.judul || '-'}</td><td className="px-5 py-4 text-slate-600">{row.pihak || '-'}</td><td className="px-5 py-4 text-slate-600">{row.tanggal || '-'}</td><td className="px-5 py-4 text-right text-slate-600">{row.jumlah_sdm || '0'}</td><td className="px-5 py-4 text-right text-slate-600">Rp {currency.format(row.total_biaya || 0)}</td><td className="max-w-xs px-5 py-4 text-sm text-red-600">{row.errors?.length ? row.errors.join(' ') : 'Siap diproses'}</td></tr>)}</tbody></table></div></div>}
                </section>
            </div>
        </LegacyPageShell>
    );
}
