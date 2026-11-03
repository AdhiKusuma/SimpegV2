import { Head, Link, useForm } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, FileSpreadsheet, LockKeyhole, Upload } from 'lucide-react';
import { useEffect } from 'react';
import LegacyPageShell from '../../Components/LegacyPageShell';
import SelectCombobox from '../../Components/SelectCombobox';

const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

export default function Import({ preview, filters, willRequireApproval = false, flash = {} }) {
    const upload = useForm({ tahun: filters.tahun, bulan: filters.bulan, file_xlsx: null });
    const confirm = useForm({ preview_token: preview?.token || '' });
    useEffect(() => confirm.setData('preview_token', preview?.token || ''), [preview?.token]);

    function submitPreview(event) {
        event.preventDefault();
        upload.post('/payroll/import/preview', { forceFormData: true, preserveScroll: true });
    }

    function submitImport(event) {
        event.preventDefault();
        confirm.post('/payroll/import', { preserveScroll: true });
    }

    const hasErrors = Boolean(preview?.error_count);

    return (
        <LegacyPageShell title="Import payroll">
            <Head title="Import payroll" />
            <div className="mb-6">
                <Link href="/payroll" className="simpeg-muted mb-4 inline-flex items-center gap-2 text-sm font-semibold hover:underline"><ArrowLeft aria-hidden="true" className="h-4 w-4" /> Kembali ke payroll</Link>
                <p className="simpeg-muted mb-2 text-sm font-semibold uppercase tracking-[0.18em]">Penggajian</p>
                <h1 className="simpeg-text text-3xl font-extrabold tracking-tight">Import data payroll</h1>
                <p className="simpeg-muted mt-2">Pilih periode, unggah workbook, lalu periksa validasi sebelum menyimpan data.</p>
            </div>

            {flash.error && <div role="alert" className="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800">{flash.error}</div>}

            <section className="simpeg-card mb-6 rounded-2xl border p-4 shadow-sm sm:p-6">
                <div className="mb-5 flex flex-wrap items-start justify-between gap-3">
                    <div className="flex items-start gap-3">
                        <span className="simpeg-card-soft inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border"><FileSpreadsheet aria-hidden="true" className="h-5 w-5" /></span>
                        <div><h2 className="simpeg-text font-semibold">Workbook payroll</h2><p className="simpeg-muted mt-1 text-sm">Nama sheet bebas. Sistem mengenali data dari header kolom payroll; pastikan semua kolom template tersedia sebelum mengunggah. Nominal Golongan Pegawai ditambahkan ke Gaji Pokok, dan nilai kosong dihitung Rp0.</p>
                            <p className="simpeg-muted mt-2 text-xs">{willRequireApproval ? 'Setelah valid, import diajukan ke supervisor kantor terkait. Data baru aktif setelah disetujui.' : 'Setelah valid, data payroll dan Master Gaji langsung diperbarui.'}</p>
                        </div>
                    </div>
                    <a href="/payroll/template" className="simpeg-utility inline-flex min-h-10 items-center gap-2 rounded-xl border px-4 text-sm font-semibold">
                        <FileSpreadsheet aria-hidden="true" className="h-4 w-4" /> Unduh template XLSX
                    </a>
                </div>
                <form onSubmit={submitPreview} className="grid gap-4 md:grid-cols-2">
                    <div>
                        <label htmlFor="payroll-import-year" className="simpeg-muted mb-2 block text-xs font-bold uppercase tracking-wide">Tahun</label>
                        <input id="payroll-import-year" type="number" min="2000" max="2100" required value={upload.data.tahun} onChange={(event) => upload.setData('tahun', event.target.value)} className="simpeg-input h-11 w-full rounded-xl border px-3 text-sm outline-none focus:ring-4 focus:ring-[var(--simpeg-focus-ring)]" />
                        {upload.errors.tahun && <p role="alert" className="mt-1 text-xs text-red-600">{upload.errors.tahun}</p>}
                    </div>
                    <div>
                        <label htmlFor="payroll-import-month" className="simpeg-muted mb-2 block text-xs font-bold uppercase tracking-wide">Bulan</label>
                        <SelectCombobox
                            id="payroll-import-month"
                            value={upload.data.bulan}
                            onChange={(value) => upload.setData('bulan', value)}
                            options={months.map((month, index) => ({ value: String(index + 1), label: month }))}
                            placeholder="Pilih bulan"
                            required
                        />
                        {upload.errors.bulan && <p role="alert" className="mt-1 text-xs text-red-600">{upload.errors.bulan}</p>}
                    </div>
                    <div className="md:col-span-2">
                        <label htmlFor="payroll-file" className="simpeg-muted mb-2 block text-xs font-bold uppercase tracking-wide">File XLSX</label>
                        <input id="payroll-file" type="file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required onChange={(event) => upload.setData('file_xlsx', event.target.files?.[0] || null)} className="simpeg-input block min-h-12 w-full rounded-xl border p-2 text-sm file:mr-3 file:min-h-9 file:rounded-lg file:border-0 file:px-3 file:font-semibold" />
                        <p className="simpeg-muted mt-2 text-xs">Maksimal 20 MB. Data rekening dan komponen gaji hanya diproses di backend.</p>
                        {upload.errors.file_xlsx && <p role="alert" className="mt-1 text-xs text-red-600">{upload.errors.file_xlsx}</p>}
                    </div>
                    <div className="md:col-span-2">
                        <button type="submit" disabled={upload.processing} className="simpeg-primary inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl px-5 text-sm font-bold disabled:opacity-60 sm:w-auto">
                            <Upload aria-hidden="true" className="h-4 w-4" />{upload.processing ? 'Memeriksa file…' : 'Periksa dan tampilkan preview'}
                        </button>
                    </div>
                </form>
            </section>

            {preview && <section className="simpeg-card overflow-hidden rounded-2xl border shadow-sm" aria-live="polite">
                <div className="border-b p-4 sm:p-5">
                    <p className="simpeg-muted text-xs font-bold uppercase tracking-wide">Preview validasi</p>
                    <h2 className="simpeg-text mt-1 text-lg font-semibold">{preview.source_filename}</h2>
                    <p className="simpeg-muted mt-1 text-sm">{months[Number(preview.bulan) - 1]} {preview.tahun}</p>
                </div>
                {preview.replaces_current && <div className="flex gap-3 border-b border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 sm:px-5">
                    <AlertTriangle aria-hidden="true" className="mt-0.5 h-4 w-4 shrink-0" />Periode ini sudah memiliki data aktif. Setelah disimpan, batch sebelumnya menjadi riwayat dan tidak dihapus.
                </div>}
                <div className="grid gap-3 p-4 sm:grid-cols-2 sm:p-5">
                    <div className="simpeg-card-soft rounded-xl border p-4"><p className="simpeg-muted text-xs">Baris pegawai</p><p className="simpeg-text mt-1 text-2xl font-bold tabular-nums">{Number(preview.row_count).toLocaleString('id-ID')}</p></div>
                    <div className="simpeg-card-soft rounded-xl border p-4"><p className="simpeg-muted text-xs">Kantor terdeteksi</p><p className="simpeg-text mt-1 text-2xl font-bold tabular-nums">{Number(preview.office_count).toLocaleString('id-ID')}</p></div>
                </div>
                {hasErrors ? <div className="border-t p-4 sm:p-5">
                    <p className="mb-3 font-semibold text-red-700">Ditemukan {preview.error_count} masalah. Perbaiki workbook sebelum mengimpor.</p>
                    <ul className="max-h-72 space-y-2 overflow-y-auto rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800">
                        {preview.errors.map((error, index) => <li key={`${error.row ?? 'header'}-${index}`}><span className="font-semibold">{error.row ? `Baris ${error.row}: ` : ''}</span>{error.messages.join('; ')}</li>)}
                    </ul>
                </div> : <div className="flex flex-col gap-3 border-t p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5">
                    <p className="simpeg-muted flex items-center gap-2 text-sm"><LockKeyhole aria-hidden="true" className="h-4 w-4 shrink-0" />Preview hanya menunjukkan jumlah baris; nilai gaji dan rekening tidak dikirim ke browser.</p>
                    <form onSubmit={submitImport}>
                        <input type="hidden" value={confirm.data.preview_token} readOnly />
                        {confirm.errors.preview_token && <p role="alert" className="mb-2 text-xs text-red-600">{confirm.errors.preview_token}</p>}
                        <button type="submit" disabled={confirm.processing || !preview.token} className="simpeg-primary inline-flex min-h-11 w-full items-center justify-center rounded-xl px-5 text-sm font-bold disabled:opacity-60 sm:w-auto">{confirm.processing ? (willRequireApproval ? 'Mengajukan…' : 'Menyimpan…') : (willRequireApproval ? 'Ajukan import untuk approval' : 'Simpan data payroll')}</button>
                    </form>
                </div>}
            </section>}
        </LegacyPageShell>
    );
}
