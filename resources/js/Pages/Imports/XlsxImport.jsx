import { Head, Link, useForm } from '@inertiajs/react';
import { AlertCircle, ArrowLeft, CheckCircle2, Database, Download, FileSpreadsheet, Save, ShieldCheck, UploadCloud } from 'lucide-react';
import { Component, useState } from 'react';
import LegacyPageShell from '../../Components/LegacyPageShell';

const statusLabels = {
    new: 'Data baru',
    update: 'Update data',
    rename: 'Ganti ID',
    invalid: 'Perlu diperiksa',
    'Data baru': 'Data baru',
    'Update data': 'Update data',
    'Perlu diperiksa': 'Perlu diperiksa',
};
const statusStyles = {
    new: 'bg-emerald-50 text-emerald-700',
    update: 'bg-amber-50 text-amber-700',
    rename: 'bg-red-50 text-red-700',
    invalid: 'bg-red-50 text-red-700',
    'Data baru': 'bg-emerald-50 text-emerald-700',
    'Update data': 'bg-amber-50 text-amber-700',
    'Perlu diperiksa': 'bg-red-50 text-red-700',
};

function Step({ number, label, active }) {
    return <span className={`simpeg-import-step ${active ? 'simpeg-import-step--active' : ''}`}><span className="simpeg-import-step-number">{number}</span>{label}</span>;
}

class ImportRenderErrorBoundary extends Component {
    state = { error: null };

    static getDerivedStateFromError(error) {
        return { error };
    }

    componentDidCatch(error, errorInfo) {
        console.error('Import preview gagal dirender.', error, errorInfo);
    }

    render() {
        if (this.state.error) {
            return (
                <div className="simpeg-card rounded-2xl border border-red-200 bg-red-50 p-5 text-red-800" role="alert">
                    <div className="flex items-start gap-3">
                        <AlertCircle className="mt-0.5 h-5 w-5 shrink-0" aria-hidden="true" />
                        <div>
                            <h2 className="font-bold">Preview tidak dapat ditampilkan</h2>
                            <p className="mt-1 text-sm">Respons preview diterima, tetapi ada data yang tidak dapat dirender oleh halaman.</p>
                            <p className="mt-2 break-words font-mono text-xs text-red-700">{this.state.error?.message || 'Kesalahan JavaScript tidak diketahui.'}</p>
                            <button type="button" onClick={() => window.location.reload()} className="mt-4 inline-flex min-h-10 items-center rounded-xl bg-red-700 px-4 py-2 text-sm font-bold text-white hover:bg-red-800">Muat ulang halaman</button>
                        </div>
                    </div>
                </div>
            );
        }

        return this.props.children;
    }
}

function XlsxImportPage({ title, backUrl, previewUrl, storeUrl, templateUrl, columns, preview: initialPreview, fileField = 'file_excel', directUpload = false }) {
    const [file, setFile] = useState(null);
    const [preview, setPreview] = useState(initialPreview);
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);
    const uploadForm = useForm({ [fileField]: null });
    const saveForm = useForm({ preview_token: '' });

    async function previewFile(event) {
        event.preventDefault();
        if (!file) {
            setError('Pilih file XLSX terlebih dahulu.');
            return;
        }

        setError('');

        if (directUpload) {
            uploadForm.transform((data) => ({ ...data, [fileField]: file })).post(storeUrl, {
                forceFormData: true,
                preserveScroll: true,
                onError: (errors) => {
                    const firstError = Object.values(errors || {})[0];
                    setError(Array.isArray(firstError) ? firstError[0] : firstError || 'File XLSX tidak dapat diproses.');
                },
            });
            return;
        }

        setLoading(true);
        const payload = new FormData();
        payload.append(fileField, file);

        try {
            const response = await fetch(previewUrl, {
                method: 'POST',
                body: payload,
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
            });
            const result = await response.json();
            if (!response.ok) throw new Error(result.message || result.errors?.[fileField]?.[0] || 'File XLSX tidak dapat diproses.');
            setPreview(result);
        } catch (requestError) {
            setError(requestError.message);
            setPreview(null);
        } finally {
            setLoading(false);
        }
    }

    function saveImport() {
        if (!preview?.token || !preview?.rows?.length || preview.invalid_count > 0) return;
        saveForm.setData('preview_token', preview.token);
        saveForm.post(storeUrl, { preserveScroll: true });
    }

    const previewRows = Array.isArray(preview?.preview) ? preview.preview : [];
    const previewColumns = Array.isArray(preview?.columns) ? preview.columns : (Array.isArray(columns) ? columns : []);
    const hasPreview = Boolean(preview);
    const previewCount = previewRows.length;
    const totalCount = Number(preview?.total || 0);
    const invalidCount = Number(preview?.invalid_count || 0);

    return (
        <LegacyPageShell title={title}>
            <Head title={title} />
            <div className="simpeg-import-page mx-auto max-w-[1400px]">
                <header className="simpeg-import-header">
                    <div className="simpeg-import-heading">
                        <Link href={backUrl} className="mb-5 inline-flex items-center gap-1.5 text-sm font-bold text-blue-600 transition hover:text-blue-700"><ArrowLeft className="h-4 w-4" aria-hidden="true" />Kembali ke daftar</Link>
                        <p className="simpeg-import-kicker">Import data</p>
                        <h1 className="simpeg-text mt-2 text-3xl font-extrabold tracking-tight sm:text-4xl">{title}</h1>
                        <p className="simpeg-muted mt-3 max-w-3xl text-sm leading-6 sm:text-base">{directUpload ? 'Gunakan template resmi, unggah file Excel, lalu proses data melalui alur import yang aman.' : 'Gunakan template resmi, unggah file Excel, lalu periksa preview sebelum data disimpan ke sistem.'}</p>
                    </div>
                    <div className="simpeg-import-stepper" aria-label="Tahapan import">
                        <Step number="1" label="Template" active />
                        <span className="simpeg-import-step-divider" aria-hidden="true" />
                        <Step number="2" label={directUpload ? 'Import' : 'Unggah'} active={!hasPreview} />
                        {!directUpload && <><span className="simpeg-import-step-divider" aria-hidden="true" /><Step number="3" label="Preview" active={hasPreview} /></>}
                    </div>
                </header>

                <div className="grid min-w-0 gap-5 lg:grid-cols-[minmax(0,1.05fr)_minmax(0,0.95fr)]">
                    <section className="simpeg-import-card">
                        <div className="simpeg-import-card-heading">
                            <span className="simpeg-import-card-icon"><FileSpreadsheet className="h-5 w-5" aria-hidden="true" /></span>
                            <div><p className="simpeg-import-step-label">Langkah 1</p><h2 className="simpeg-text mt-1 text-lg font-bold">Siapkan template</h2><p className="simpeg-muted mt-1 text-sm leading-5">Gunakan struktur kolom resmi agar data terbaca konsisten.</p></div>
                        </div>
                        <div className="simpeg-import-card-body">
                            <div className="simpeg-import-template-card">
                                <div className="min-w-0">
                                    <p className="simpeg-text text-sm font-bold">Format file XLSX</p>
                                    <ul className="simpeg-import-template-list">
                                        <li>Jangan mengubah nama, urutan, atau jumlah kolom.</li>
                                        <li>Isi data mulai dari baris yang disediakan template.</li>
                                        <li>Pastikan NIK dan nomor identitas disimpan sebagai teks.</li>
                                    </ul>
                                </div>
                                <a href={templateUrl} download className="simpeg-action-primary inline-flex min-h-11 shrink-0 items-center justify-center gap-2 rounded-xl px-4 py-2 text-sm font-bold"><Download className="h-4 w-4" aria-hidden="true" />Unduh template</a>
                            </div>
                        </div>
                    </section>

                    <section className="simpeg-import-card">
                        <div className="simpeg-import-card-heading">
                            <span className="simpeg-import-card-icon"><UploadCloud className="h-5 w-5" aria-hidden="true" /></span>
                            <div><p className="simpeg-import-step-label">Langkah 2</p><h2 className="simpeg-text mt-1 text-lg font-bold">{directUpload ? 'Import file' : 'Unggah file'}</h2><p className="simpeg-muted mt-1 text-sm leading-5">{directUpload ? 'Pilih file XLSX yang sudah diisi untuk diproses.' : 'Pilih file XLSX yang sudah diisi untuk diperiksa.'}</p></div>
                        </div>
                        <div className="simpeg-import-card-body">
                            <form onSubmit={previewFile} className="simpeg-import-upload-grid">
                                <label htmlFor="generic-import-file" className={`simpeg-import-dropzone ${file ? 'simpeg-import-dropzone--selected' : ''}`}>
                                    <span className="simpeg-import-dropzone-icon">{file ? <CheckCircle2 className="h-6 w-6" aria-hidden="true" /> : <UploadCloud className="h-6 w-6" aria-hidden="true" />}</span>
                                    <span className="min-w-0">
                                        <span className={`simpeg-import-file-name simpeg-text block text-sm font-bold ${file ? 'text-blue-700' : ''}`}>{file?.name || 'Pilih file XLSX dari perangkat'}</span>
                                        <span className="simpeg-muted mt-1 block text-xs">{file ? `${(file.size / 1024 / 1024).toFixed(2)} MB · ${directUpload ? 'Siap diproses' : 'Siap diperiksa'}` : 'Format .xlsx · Maksimal 5 MB'}</span>
                                    </span>
                                    <input id="generic-import-file" type="file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required onChange={(event) => { const selectedFile = event.target.files?.[0] || null; setFile(selectedFile); uploadForm.setData(fileField, selectedFile); setPreview(null); setError(''); }} className="sr-only" />
                                </label>
                                <button type="submit" disabled={loading || (directUpload && uploadForm.processing)} className="simpeg-action-primary inline-flex min-h-11 items-center justify-center gap-2 rounded-xl px-5 py-2 text-sm font-bold disabled:cursor-not-allowed disabled:opacity-60">{loading || uploadForm.processing ? <><span className="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white" aria-hidden="true" />{directUpload ? 'Memproses...' : 'Memeriksa...'}</> : directUpload ? <><Save className="h-4 w-4" aria-hidden="true" />Proses import</> : <><ShieldCheck className="h-4 w-4" aria-hidden="true" />Preview file</>}</button>
                            </form>
                            {error && <div className="mt-3 flex items-start gap-2 rounded-xl border border-red-200 bg-red-50 px-3 py-3 text-sm font-semibold text-red-700" role="alert"><AlertCircle className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />{error}</div>}
                        </div>
                    </section>
                </div>

                {!directUpload && preview && <section className="simpeg-import-card simpeg-import-preview-card">
                    <div className="simpeg-import-preview-header">
                        <div className="min-w-0">
                            <div className="flex items-center gap-2"><span className="simpeg-import-card-icon h-9 w-9 rounded-lg"><Database className="h-4 w-4" aria-hidden="true" /></span><div><p className="simpeg-import-step-label">Langkah 3</p><h2 className="simpeg-text mt-1 text-lg font-bold">Preview validasi</h2></div></div>
                            <div className="simpeg-import-summary"><span className="simpeg-import-summary-item">Menampilkan {previewCount} dari {totalCount} baris</span><span className={`simpeg-import-summary-item ${invalidCount > 0 ? 'border-red-200 bg-red-50 text-red-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700'}`}>{invalidCount > 0 ? `${invalidCount} baris perlu diperbaiki` : 'Semua baris sesuai'}</span></div>
                        </div>
                        <button type="button" onClick={saveImport} disabled={saveForm.processing || !preview.token || !preview.rows?.length || invalidCount > 0} className="simpeg-action-primary inline-flex min-h-11 shrink-0 items-center justify-center gap-2 rounded-xl px-5 py-2 text-sm font-bold disabled:cursor-not-allowed disabled:opacity-60">{saveForm.processing ? 'Menyimpan...' : <><Save className="h-4 w-4" aria-hidden="true" />Proses import</>}</button>
                    </div>
                    <div className="p-4 sm:p-5">
                        {invalidCount > 0 && <div className="mb-4 flex items-start gap-2 rounded-xl border border-red-200 bg-red-50 px-3 py-3 text-sm font-semibold text-red-700" role="alert"><AlertCircle className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />Perbaiki baris yang ditandai sebelum proses import dijalankan.</div>}
                        {saveForm.errors.preview_token && <p className="mb-3 text-sm font-semibold text-red-600" role="alert">{saveForm.errors.preview_token}</p>}
                        <div className="simpeg-import-table-wrap rounded-xl border border-slate-200">
                            <table className="min-w-[1100px] w-full text-left text-xs">
                                <thead className="text-xs uppercase tracking-wide text-slate-500"><tr><th className="px-5 py-4">Status</th><th className="px-5 py-4">Validasi</th>{previewColumns.map((column, index) => <th key={`${column || 'kolom'}-${index}`} className="px-5 py-4">{column || `Kolom ${index + 1}`}</th>)}</tr></thead>
                                <tbody className="divide-y divide-slate-100">{previewRows.map((row, rowIndex) => { const errors = Array.isArray(row?.errors) ? row.errors : []; const values = Array.isArray(row?.values) ? row.values : []; const status = row?.status || 'Perlu diperiksa'; return <tr key={row?.row_number || rowIndex} className="align-top transition hover:bg-slate-50"><td className="px-5 py-4"><span className={`inline-flex whitespace-nowrap rounded-full px-2 py-1 font-bold ${statusStyles[status] || statusStyles['Perlu diperiksa']}`}>{statusLabels[status] || status}</span><span className="mt-1 block text-slate-400">Baris {row?.row_number || rowIndex + 2}</span></td><td className={`max-w-72 px-5 py-4 ${errors.length ? 'font-semibold text-red-600' : 'text-slate-600'}`}>{errors.length ? errors.join('; ') : 'Sesuai'}</td>{values.map((value, index) => <td key={index} className="max-w-56 px-5 py-4 text-slate-700">{value || '-'}</td>)}</tr>; })}</tbody>
                            </table>
                        </div>
                    </div>
                </section>}
            </div>
        </LegacyPageShell>
    );
}

export default function XlsxImport(props) {
    return <ImportRenderErrorBoundary><XlsxImportPage {...props} /></ImportRenderErrorBoundary>;
}
