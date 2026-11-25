import { Head, Link, useForm } from '@inertiajs/react';
import { AlertCircle, AlertTriangle, ArrowLeft, CalendarClock, Database, Download, FileSpreadsheet, LockKeyhole, Save, UploadCloud } from 'lucide-react';
import { useEffect } from 'react';
import LegacyPageShell from '../../Components/LegacyPageShell';
import SelectCombobox from '../../Components/SelectCombobox';

const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

function Step({ number, label, active }) {
    return <span className={`simpeg-import-step ${active ? 'simpeg-import-step--active' : ''}`}><span className="simpeg-import-step-number">{number}</span>{label}</span>;
}

export default function Import({ preview, filters, willRequireApproval = false, flash = {} }) {
    const upload = useForm({ tahun: filters.tahun, bulan: filters.bulan, file_xlsx: null });
    const confirm = useForm({ preview_token: preview?.token || '' });

    useEffect(() => confirm.setData('preview_token', preview?.token || ''), [preview?.token]);

    function submitPreview(event) {
        event.preventDefault();
        upload.post('/payroll/import/preview', { forceFormData: true, preserveState: true, preserveScroll: true });
    }

    function submitImport(event) {
        event.preventDefault();
        const file = upload.data.file_xlsx;
        if (!file || (typeof File !== 'undefined' && !(file instanceof File))) {
            confirm.setError('file_xlsx', 'Pilih kembali file XLSX yang sama sebelum menyimpan data payroll.');
            return;
        }

        confirm.transform((data) => ({ ...data, file_xlsx: file }));
        confirm.post('/payroll/import', { forceFormData: true, preserveScroll: true });
    }

    const hasErrors = Boolean(preview?.error_count);
    const hasWarnings = Boolean(preview?.warning_count);
    const hasDuplicate = Boolean(preview?.duplicate_current || preview?.duplicate_pending);

    return (
        <LegacyPageShell title="Import data payroll">
            <Head title="Import data payroll" />
            <div className="simpeg-import-page mx-auto max-w-[1400px]">
                <header className="simpeg-import-header">
                    <div className="simpeg-import-heading">
                        <Link href="/payroll" className="mb-5 inline-flex items-center gap-1.5 text-sm font-bold text-blue-600 transition hover:text-blue-700"><ArrowLeft className="h-4 w-4" aria-hidden="true" />Kembali ke payroll</Link>
                        <p className="simpeg-import-kicker">Penggajian</p>
                        <h1 className="simpeg-text mt-2 text-3xl font-extrabold tracking-tight sm:text-4xl">Import data payroll</h1>
                        <p className="simpeg-muted mt-3 max-w-3xl text-sm leading-6 sm:text-base">Pilih periode, unggah template payroll, lalu periksa hasil pemeriksaan sebelum menyimpan data.</p>
                    </div>
                    <div className="simpeg-import-stepper" aria-label="Tahapan import payroll">
                        <Step number="1" label="Template" active />
                        <span className="simpeg-import-step-divider" aria-hidden="true" />
                        <Step number="2" label="Unggah" active={!preview} />
                        <span className="simpeg-import-step-divider" aria-hidden="true" />
                        <Step number="3" label="Preview" active={Boolean(preview)} />
                    </div>
                </header>

                {flash.error && <div role="alert" className="flex items-start gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800"><AlertCircle className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />{flash.error}</div>}

                <div className="grid min-w-0 gap-5 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
                    <section className="simpeg-import-card">
                        <div className="simpeg-import-card-heading">
                            <span className="simpeg-import-card-icon"><FileSpreadsheet className="h-5 w-5" aria-hidden="true" /></span>
                            <div><p className="simpeg-import-step-label">Langkah 1</p><h2 className="simpeg-text mt-1 text-lg font-bold">Siapkan template</h2><p className="simpeg-muted mt-1 text-sm leading-5">Gunakan template payroll resmi agar kolom dan nominal terbaca konsisten.</p></div>
                        </div>
                        <div className="simpeg-import-card-body">
                            <div className="simpeg-import-template-card">
                                <div className="min-w-0">
                                    <p className="simpeg-text text-sm font-bold">Format file XLSX</p>
                                    <ul className="simpeg-import-template-list">
                                        <li>Nama lembar kerja boleh disesuaikan.</li>
                                        <li>ID Pegawai menjadi kunci pencocokan data.</li>
                                        <li>Nominal dan rekening tetap divalidasi sebelum simpan.</li>
                                    </ul>
                                </div>
                                <a href="/payroll/template" download className="simpeg-action-primary inline-flex min-h-11 shrink-0 items-center justify-center gap-2 rounded-xl px-4 py-2 text-sm font-bold"><Download className="h-4 w-4" aria-hidden="true" />Unduh template</a>
                            </div>
                        </div>
                    </section>

                    <section className="simpeg-import-card">
                        <div className="simpeg-import-card-heading">
                            <span className="simpeg-import-card-icon"><UploadCloud className="h-5 w-5" aria-hidden="true" /></span>
                            <div><p className="simpeg-import-step-label">Langkah 2</p><h2 className="simpeg-text mt-1 text-lg font-bold">Unggah file payroll</h2><p className="simpeg-muted mt-1 text-sm leading-5">Tentukan periode dan pilih file XLSX yang akan diperiksa.</p></div>
                        </div>
                        <div className="simpeg-import-card-body">
                            <form onSubmit={submitPreview} className="space-y-4">
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <label htmlFor="payroll-import-year" className="simpeg-muted mb-2 block text-xs font-bold uppercase tracking-wide">Tahun</label>
                                        <input id="payroll-import-year" type="number" min="2000" max="2100" required value={upload.data.tahun} onChange={(event) => upload.setData('tahun', event.target.value)} className="simpeg-input h-11 w-full rounded-xl border px-3 text-sm outline-none focus:ring-4 focus:ring-[var(--simpeg-focus-ring)]" />
                                        {upload.errors.tahun && <p role="alert" className="mt-1 text-xs text-red-600">{upload.errors.tahun}</p>}
                                    </div>
                                    <div>
                                        <label htmlFor="payroll-import-month" className="simpeg-muted mb-2 block text-xs font-bold uppercase tracking-wide">Bulan</label>
                                        <SelectCombobox id="payroll-import-month" value={upload.data.bulan} onChange={(value) => upload.setData('bulan', value)} options={months.map((month, index) => ({ value: String(index + 1), label: month }))} placeholder="Pilih bulan" required />
                                        {upload.errors.bulan && <p role="alert" className="mt-1 text-xs text-red-600">{upload.errors.bulan}</p>}
                                    </div>
                                </div>
                                <div className="simpeg-card-soft flex items-start gap-3 rounded-xl border p-3">
                                    <CalendarClock className="mt-0.5 h-5 w-5 shrink-0" aria-hidden="true" />
                                    <div><p className="simpeg-text text-sm font-semibold">Pengiriman otomatis sesuai tanggal gajian</p><p className="simpeg-muted mt-1 text-xs leading-5">Tanggal gajian akan diperiksa terlebih dahulu. Slip dikirim otomatis pukul 08.00 WIB setelah payroll aktif.</p></div>
                                </div>
                                <label htmlFor="payroll-file" className={`simpeg-import-dropzone ${upload.data.file_xlsx ? 'simpeg-import-dropzone--selected' : ''}`}>
                                    <span className="simpeg-import-dropzone-icon"><UploadCloud className="h-6 w-6" aria-hidden="true" /></span>
                                    <span className="min-w-0"><span className="simpeg-import-file-name simpeg-text block text-sm font-bold">{upload.data.file_xlsx?.name || 'Pilih file payroll XLSX dari perangkat'}</span><span className="simpeg-muted mt-1 block text-xs">{upload.data.file_xlsx ? `${(upload.data.file_xlsx.size / 1024 / 1024).toFixed(2)} MB · Siap diperiksa` : 'Format .xlsx · Maksimal 20 MB'}</span></span>
                                    <input id="payroll-file" type="file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required onChange={(event) => upload.setData('file_xlsx', event.target.files?.[0] || null)} className="sr-only" />
                                </label>
                                {upload.errors.file_xlsx && <p role="alert" className="text-xs text-red-600">{upload.errors.file_xlsx}</p>}
                                <button type="submit" disabled={upload.processing} className="simpeg-action-primary inline-flex min-h-11 items-center justify-center gap-2 rounded-xl px-5 py-2 text-sm font-bold disabled:cursor-not-allowed disabled:opacity-60"><UploadCloud className="h-4 w-4" aria-hidden="true" />{upload.processing ? 'Memeriksa file…' : 'Periksa dan tampilkan preview'}</button>
                            </form>
                        </div>
                    </section>
                </div>

                {preview && <section className="simpeg-import-card simpeg-import-preview-card">
                    <div className="simpeg-import-preview-header">
                        <div className="min-w-0">
                            <div className="flex items-center gap-2"><span className="simpeg-import-card-icon h-9 w-9 rounded-lg"><Database className="h-4 w-4" aria-hidden="true" /></span><div><p className="simpeg-import-step-label">Langkah 3</p><h2 className="simpeg-text mt-1 text-lg font-bold">Preview validasi</h2></div></div>
                            <div className="simpeg-import-summary"><span className="simpeg-import-summary-item">{Number(preview.row_count || 0).toLocaleString('id-ID')} baris pegawai</span><span className="simpeg-import-summary-item">{Number(preview.office_count || 0).toLocaleString('id-ID')} kantor terdeteksi</span><span className={`simpeg-import-summary-item ${hasErrors ? 'border-red-200 bg-red-50 text-red-700' : hasWarnings ? 'border-amber-200 bg-amber-50 text-amber-800' : 'border-emerald-200 bg-emerald-50 text-emerald-700'}`}>{hasErrors ? `${preview.error_count} masalah` : hasWarnings ? `${preview.warning_count} peringatan` : 'Semua baris sesuai'}</span></div>
                        </div>
                        {!hasErrors && !hasDuplicate && <form onSubmit={submitImport}><input type="hidden" value={confirm.data.preview_token} readOnly />{confirm.errors.preview_token && <p role="alert" className="mb-2 text-xs text-red-600">{confirm.errors.preview_token}</p>}{confirm.errors.file_xlsx && <p role="alert" className="mb-2 text-xs text-red-600">{confirm.errors.file_xlsx}</p>}<button type="submit" disabled={confirm.processing || !preview.token || !upload.data.file_xlsx} className="simpeg-action-primary inline-flex min-h-11 shrink-0 items-center justify-center gap-2 rounded-xl px-5 py-2 text-sm font-bold disabled:cursor-not-allowed disabled:opacity-60"><Save className="h-4 w-4" aria-hidden="true" />{confirm.processing ? (willRequireApproval ? 'Mengajukan…' : 'Menyimpan…') : (willRequireApproval ? 'Ajukan untuk approval' : 'Simpan data payroll')}</button></form>}
                    </div>
                    <div className="space-y-4 p-4 sm:p-5">
                        {preview.replaces_current && <div className="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"><AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />Periode ini sudah memiliki data aktif. Setelah disimpan, batch sebelumnya menjadi riwayat dan tidak dihapus.</div>}
                        {hasErrors && <div className="rounded-xl border border-red-200 bg-red-50 p-4"><p className="mb-3 font-semibold text-red-700">Ditemukan {preview.error_count} masalah. Periksa dan perbaiki file sebelum mengimpor.</p><ul className="max-h-72 space-y-2 overflow-y-auto text-sm text-red-800">{(preview.errors || []).map((error, index) => <li key={`${error.row ?? 'header'}-${index}`}><span className="font-semibold">{error.row ? `Baris ${error.row}: ` : ''}</span>{error.messages.join('; ')}</li>)}</ul></div>}
                        {hasWarnings && <div className="rounded-xl border border-amber-200 bg-amber-50 p-4"><p className="mb-3 flex items-start gap-2 font-semibold text-amber-900"><AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />Ditemukan {preview.warning_count} peringatan referensi master. Peringatan tidak mengubah nilai dan tidak menghalangi penyimpanan.</p><ul className="max-h-72 space-y-2 overflow-y-auto text-sm text-amber-900">{(preview.warnings || []).map((warning, index) => <li key={`${warning.row ?? 'header'}-${index}`}><span className="font-semibold">{warning.row ? `Baris ${warning.row}: ` : ''}</span>{warning.messages.join('; ')}</li>)}</ul></div>}
                        {!hasErrors && hasDuplicate ? <div className="rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">{preview.duplicate_current ? 'File ini sama dengan data payroll aktif untuk periode tersebut. Data baru tidak dibuat.' : 'File ini sama dengan pengajuan payroll yang masih menunggu persetujuan. Pengajuan baru tidak dibuat.'}</div> : !hasErrors && !hasDuplicate && !hasWarnings ? <div className="simpeg-card-soft flex items-start gap-2 rounded-xl border p-4 text-sm"><LockKeyhole className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />Pratinjau hanya menampilkan jumlah baris. Nilai gaji dan rekening tetap terlindungi dan diproses setelah konfirmasi.</div> : null}
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3"><div className="simpeg-card-soft rounded-xl border p-4"><p className="simpeg-muted text-xs">Periode</p><p className="simpeg-text mt-1 font-semibold">{months[Number(preview.bulan) - 1]} {preview.tahun}</p></div><div className="simpeg-card-soft rounded-xl border p-4"><p className="simpeg-muted text-xs">Pengiriman slip</p><p className="simpeg-text mt-1 font-semibold">{preview.publication_mode === 'scheduled' ? `Terjadwal${preview.scheduled_at ? ` · ${preview.scheduled_at.replace('T', ' ')}` : ''}` : 'Setelah payroll aktif'}</p></div><div className="simpeg-card-soft rounded-xl border p-4"><p className="simpeg-muted text-xs">Status approval</p><p className="simpeg-text mt-1 font-semibold">{willRequireApproval ? 'Menunggu approval' : 'Dapat langsung disimpan'}</p></div></div>
                    </div>
                </section>}
            </div>
        </LegacyPageShell>
    );
}
