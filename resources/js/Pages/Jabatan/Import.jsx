import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Database, FileSpreadsheet, Upload, UploadCloud } from 'lucide-react';
import { useEffect, useState } from 'react';
import LegacyPageShell from '../../Components/LegacyPageShell';

export default function JabatanImport() {
    const [file, setFile] = useState(null);
    const [preview, setPreview] = useState(null);
    const [requestError, setRequestError] = useState('');
    const [loading, setLoading] = useState(false);
    const saveForm = useForm({ preview_token: '' });

    useEffect(() => saveForm.setData('preview_token', preview?.token || ''), [preview?.token]);

    async function previewFile(event) {
        event.preventDefault();
        if (!file) {
            setRequestError('Pilih file XLSX terlebih dahulu.');
            return;
        }

        setLoading(true);
        setRequestError('');
        setPreview(null);
        saveForm.clearErrors();
        const payload = new FormData();
        payload.append('file_xlsx', file);

        try {
            const response = await fetch('/jabatan/import/preview', {
                method: 'POST',
                body: payload,
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
            });
            const result = await response.json();
            if (!response.ok) {
                throw new Error(result.message || result.errors?.file_xlsx?.[0] || 'File XLSX tidak dapat diproses.');
            }
            setPreview(result);
        } catch (error) {
            setRequestError(error.message || 'File XLSX tidak dapat diproses.');
        } finally {
            setLoading(false);
        }
    }

    function saveImport(event) {
        event.preventDefault();
        if (!preview?.token || preview.invalid_count > 0 || preview.file_errors?.length) return;
        saveForm.post('/jabatan/import', { preserveScroll: true });
    }

    return (
        <LegacyPageShell title="Import jabatan">
            <Head title="Import jabatan" />
            <div className="simpeg-import-page mx-auto max-w-[1400px]">
                <header className="simpeg-import-header">
                    <div className="simpeg-import-heading">
                        <Link href="/jabatan" className="mb-5 inline-flex items-center gap-1.5 text-sm font-bold text-blue-600 transition hover:text-blue-700"><ArrowLeft aria-hidden="true" className="h-4 w-4" />Kembali ke jabatan</Link>
                        <p className="simpeg-import-kicker">Referensi kepegawaian</p>
                        <h1 className="simpeg-text mt-2 text-3xl font-extrabold tracking-tight sm:text-4xl">Import jabatan</h1>
                        <p className="simpeg-muted mt-3 max-w-3xl text-sm leading-6 sm:text-base">Unggah template, tinjau hasil pencocokan, lalu simpan jabatan pegawai.</p>
                    </div>
                    <div className="simpeg-import-stepper" aria-label="Tahapan import jabatan">
                        <span className="simpeg-import-step simpeg-import-step--active"><span className="simpeg-import-step-number">1</span>Template</span>
                        <span className="simpeg-import-step-divider" aria-hidden="true" />
                        <span className={`simpeg-import-step ${preview ? '' : 'simpeg-import-step--active'}`}><span className="simpeg-import-step-number">2</span>Unggah</span>
                        <span className="simpeg-import-step-divider" aria-hidden="true" />
                        <span className={`simpeg-import-step ${preview ? 'simpeg-import-step--active' : ''}`}><span className="simpeg-import-step-number">3</span>Preview</span>
                    </div>
                </header>

                <section className="simpeg-import-card">
                    <div className="mb-5 flex flex-wrap items-start justify-between gap-4 border-b border-slate-100 p-5 sm:p-6">
                        <div className="flex items-start gap-3">
                            <span className="simpeg-import-card-icon"><FileSpreadsheet aria-hidden="true" className="h-5 w-5" /></span>
                            <div>
                                <h2 className="simpeg-text font-semibold">Template import jabatan</h2>
                                <p className="simpeg-muted mt-1 max-w-3xl text-sm">Isi Kode Jabatan atau Nama Jabatan; jika keduanya diisi, nilainya harus cocok dengan master yang sama. Tanggal SK digunakan sebagai TMT. Untuk status Aktif, jabatan aktif sebelumnya pegawai akan ditutup.</p>
                            </div>
                        </div>
                        <a href="/jabatan/import/template" className="simpeg-action-primary inline-flex min-h-11 shrink-0 items-center gap-2 rounded-xl px-4 py-2 text-sm font-bold"><FileSpreadsheet aria-hidden="true" className="h-4 w-4" />Unduh template</a>
                    </div>

                    <form onSubmit={previewFile} className="grid gap-4 px-5 pb-5 lg:grid-cols-[1fr_auto] lg:items-end sm:px-6 sm:pb-6">
                        <div>
                            <label htmlFor="jabatan-import-file" className="simpeg-muted mb-2 block text-xs font-bold uppercase tracking-wide">File XLSX</label>
                            <label htmlFor="jabatan-import-file" className={`simpeg-import-dropzone ${file ? 'simpeg-import-dropzone--selected' : ''}`}>
                                <span className="simpeg-import-dropzone-icon"><UploadCloud className="h-6 w-6" aria-hidden="true" /></span>
                                <span className="min-w-0"><span className="simpeg-import-file-name simpeg-text block text-sm font-bold">{file?.name || 'Pilih file jabatan XLSX dari perangkat'}</span><span className="simpeg-muted mt-1 block text-xs">{file ? `${(file.size / 1024 / 1024).toFixed(2)} MB · Siap diperiksa` : 'Format .xlsx · Maksimal 20 MB'}</span></span>
                                <input id="jabatan-import-file" type="file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required onChange={(event) => { setFile(event.target.files?.[0] || null); setPreview(null); setRequestError(''); }} className="sr-only" />
                            </label>
                            <p className="simpeg-muted mt-2 text-xs">Maksimal 20 MB. ID pegawai harus aktif; jabatan dan unit kerja harus ditemukan pada data master.</p>
                            {requestError && <p role="alert" className="mt-2 text-sm font-medium text-red-600">{requestError}</p>}
                        </div>
                        <button type="submit" disabled={loading} className="simpeg-action-primary inline-flex min-h-11 items-center justify-center gap-2 rounded-xl px-5 py-2 text-sm font-bold disabled:opacity-60"><Upload aria-hidden="true" className="h-4 w-4" />{loading ? 'Memeriksa file…' : 'Periksa dan tampilkan preview'}</button>
                    </form>
                </section>

                {preview && <section className="simpeg-import-card simpeg-import-preview-card" aria-live="polite">
                    <div className="simpeg-import-preview-header">
                        <div className="min-w-0"><div className="flex items-center gap-2"><span className="simpeg-import-card-icon h-9 w-9 rounded-lg"><Database className="h-4 w-4" aria-hidden="true" /></span><div><p className="simpeg-import-step-label">Langkah 3</p><h2 className="simpeg-text mt-1 text-lg font-bold">Preview validasi</h2></div></div><div className="simpeg-import-summary"><span className="simpeg-import-summary-item">{preview.total} baris</span><span className="simpeg-import-summary-item border-emerald-200 bg-emerald-50 text-emerald-700">{preview.valid_count} valid</span><span className={`simpeg-import-summary-item ${preview.invalid_count > 0 || preview.file_errors?.length ? 'border-red-200 bg-red-50 text-red-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700'}`}>{preview.invalid_count || preview.file_errors?.length ? 'Perlu diperiksa' : 'Semua baris sesuai'}</span></div></div>
                    </div>
                    {preview.file_errors?.length > 0 && <div className="border-b border-red-200 bg-red-50 p-4 text-sm text-red-800"><ul className="list-inside list-disc">{preview.file_errors.map((error, index) => <li key={index}>{error}</li>)}</ul></div>}
                    {preview.invalid_count > 0 && <div className="border-b border-red-200 bg-red-50 p-4 sm:p-5">
                        <p className="mb-2 font-semibold text-red-800">Perbaiki baris berikut sebelum mengimpor:</p>
                        <ul className="max-h-56 space-y-1 overflow-y-auto text-sm text-red-700">{preview.invalid_rows.map((row) => <li key={row.row_number}>Baris {row.row_number}: {row.errors.join('; ')}</li>)}</ul>
                    </div>}
                    <div className="overflow-x-auto">
                        <table className="min-w-[980px] w-full text-left text-sm">
                            <thead className="border-b text-xs uppercase tracking-wide text-slate-500"><tr><th className="px-4 py-3">Baris</th><th className="px-4 py-3">Pegawai</th><th className="px-4 py-3">Jabatan</th><th className="px-4 py-3">Unit kerja</th><th className="px-4 py-3">No. SK / Tgl SK</th><th className="px-4 py-3">Status</th><th className="px-4 py-3">Validasi</th></tr></thead>
                            <tbody className="divide-y">
                                {preview.preview.map((row) => <tr key={row.row_number} className="align-top">
                                    <td className="px-4 py-3 tabular-nums">{row.row_number}</td>
                                    <td className="px-4 py-3"><span className="block font-semibold">{row.nama_pegawai || 'Pegawai tidak ditemukan'}</span><span className="simpeg-muted font-mono text-xs">{row.id_peg || '-'}</span></td>
                                    <td className="px-4 py-3"><span className="block font-semibold">{row.nama_jabatan || '-'}</span><span className="simpeg-muted text-xs">Kode: {row.kode_jabatan || '-'}</span></td>
                                    <td className="px-4 py-3"><span className="block">{row.nama_unit || '-'}</span><span className="simpeg-muted font-mono text-xs">{row.unit_kerja || '-'}</span></td>
                                    <td className="px-4 py-3"><span className="block">{row.no_sk || '-'}</span><span className="simpeg-muted text-xs">{row.tgl_sk || '-'}</span></td>
                                    <td className="px-4 py-3">{row.status || '-'}</td>
                                    <td className="max-w-72 px-4 py-3">{row.errors.length ? <span className="text-red-700">{row.errors.join('; ')}</span> : <span className="text-emerald-700">Sesuai</span>}</td>
                                </tr>)}
                            </tbody>
                        </table>
                    </div>
                    {saveForm.errors.preview_token && <p role="alert" className="border-t px-4 py-3 text-sm text-red-700">{saveForm.errors.preview_token}</p>}
                    <div className="flex flex-col gap-3 border-t p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5">
                        <p className="simpeg-muted text-sm">Import hanya tersedia jika seluruh baris lolos validasi.</p>
                        <form onSubmit={saveImport}>
                            <button type="submit" disabled={saveForm.processing || !preview.token || preview.invalid_count > 0 || preview.file_errors?.length > 0} className="simpeg-primary inline-flex min-h-11 w-full items-center justify-center rounded-xl px-5 text-sm font-bold disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto">{saveForm.processing ? 'Menyimpan…' : 'Simpan data jabatan'}</button>
                        </form>
                    </div>
                </section>}
            </div>
        </LegacyPageShell>
    );
}
