import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import LegacyPageShell from '../../Components/LegacyPageShell';

const statusLabels = { 'Data baru': 'Data baru', 'Update data': 'Update data', 'Perlu diperiksa': 'Perlu diperiksa' };
const statusStyles = { 'Data baru': 'bg-emerald-50 text-emerald-700', 'Update data': 'bg-amber-50 text-amber-700', 'Perlu diperiksa': 'bg-red-50 text-red-700' };

export default function XlsxImport({ title, backUrl, previewUrl, storeUrl, templateUrl, columns, preview: initialPreview }) {
    const [file, setFile] = useState(null);
    const [preview, setPreview] = useState(initialPreview);
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);
    const saveForm = useForm({ data_rows: '' });

    async function previewFile(event) {
        event.preventDefault();
        if (!file) {
            setError('Pilih file XLSX terlebih dahulu.');
            return;
        }

        setLoading(true);
        setError('');
        const payload = new FormData();
        payload.append('file_excel', file);

        try {
            const response = await fetch(previewUrl, {
                method: 'POST',
                body: payload,
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
            });
            const result = await response.json();
            if (!response.ok) throw new Error(result.message || result.errors?.file_excel?.[0] || 'File XLSX tidak dapat diproses.');
            setPreview(result);
        } catch (requestError) {
            setError(requestError.message);
            setPreview(null);
        } finally {
            setLoading(false);
        }
    }

    function saveImport() {
        if (!preview?.rows?.length || preview.invalid_count > 0) return;
        saveForm.setData('data_rows', JSON.stringify(preview.rows));
        saveForm.post(storeUrl, { preserveScroll: true });
    }

    return (
        <LegacyPageShell title={title}>
            <Head title={title} />
            <div className="mx-auto max-w-7xl">
                <div className="mb-6">
                    <Link href={backUrl} className="text-sm font-bold text-blue-600 hover:text-blue-700">Kembali ke daftar</Link>
                    <p className="mb-2 mt-5 text-sm font-semibold uppercase tracking-[0.18em] text-blue-600">Import data</p>
                    <h1 className="text-3xl font-extrabold tracking-tight text-slate-900">{title}</h1>
                    <p className="mt-2 text-slate-500">Unggah XLSX, periksa validasi seluruh baris, lalu simpan setelah data sesuai.</p>
                </div>

                <section className="simpeg-form-card">
                    <div className="rounded-xl border border-blue-100 bg-blue-50 p-4 text-sm text-blue-900">
                        <h2 className="font-extrabold">Format dan validasi</h2>
                        <p className="mt-2 leading-6">Gunakan template resmi. Sistem memeriksa pegawai aktif, format tanggal, panjang kolom, angka, dan data ganda sebelum menyimpan. File hanya digunakan selama pemeriksaan dan tidak disimpan sebagai arsip.</p>
                        <a href={templateUrl} download className="mt-4 inline-flex min-h-11 items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-bold text-white hover:bg-blue-700">Unduh template XLSX</a>
                    </div>

                    <form onSubmit={previewFile} className="mt-6 grid gap-4 lg:grid-cols-[1fr_auto] lg:items-end">
                        <div>
                            <label htmlFor="generic-import-file" className="mb-2 block text-sm font-bold text-slate-800">File XLSX</label>
                            <input id="generic-import-file" type="file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required onChange={(event) => { setFile(event.target.files?.[0] || null); setPreview(null); setError(''); }} className="block min-h-11 w-full rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm file:mr-4 file:rounded-lg file:border-0 file:bg-blue-100 file:px-3 file:py-2 file:font-bold file:text-blue-700" />
                            {error && <p className="mt-2 text-sm font-semibold text-red-600" role="alert">{error}</p>}
                        </div>
                        <button type="submit" disabled={loading} className="inline-flex min-h-11 items-center justify-center rounded-xl bg-blue-600 px-5 py-2 text-sm font-bold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60">{loading ? 'Memeriksa...' : 'Preview file'}</button>
                    </form>

                    {preview && <div className="mt-8">
                        <div className="mb-4 flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <h2 className="text-lg font-extrabold text-slate-900">Preview validasi</h2>
                                <p className="mt-1 text-sm text-slate-500">Menampilkan {preview.preview?.length || 0} dari {preview.total || 0} baris.</p>
                                {preview.invalid_count > 0 && <p className="mt-2 text-sm font-semibold text-red-600" role="alert">{preview.invalid_count} baris perlu diperbaiki. Import belum dapat disimpan.</p>}
                            </div>
                            <button type="button" onClick={saveImport} disabled={saveForm.processing || !preview.rows?.length || preview.invalid_count > 0} className="inline-flex min-h-11 items-center rounded-xl bg-emerald-700 px-5 py-2 text-sm font-bold text-white hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-60">{saveForm.processing ? 'Menyimpan...' : 'Proses import'}</button>
                        </div>
                        {saveForm.errors.data_rows && <p className="mb-3 text-sm font-semibold text-red-600" role="alert">{saveForm.errors.data_rows}</p>}
                        <div className="overflow-x-auto rounded-xl border border-slate-200">
                            <table className="min-w-[1100px] w-full text-left text-xs">
                                <thead className="border-b border-slate-100 text-xs uppercase tracking-wide text-slate-500"><tr><th className="px-5 py-4">Status</th><th className="px-5 py-4">Validasi</th>{(preview.columns || columns).map((column, index) => <th key={`${column}-${index}`} className="px-5 py-4">{column}</th>)}</tr></thead>
                                <tbody className="divide-y divide-slate-100">{(preview.preview || []).map((row) => <tr key={row.row_number} className="align-top transition hover:bg-slate-50"><td className="px-5 py-4"><span className={`inline-flex whitespace-nowrap rounded-full px-2 py-1 font-bold ${statusStyles[row.status] || statusStyles['Perlu diperiksa']}`}>{statusLabels[row.status] || row.status}</span><span className="mt-1 block text-slate-400">Baris {row.row_number}</span></td><td className="max-w-72 px-5 py-4 text-slate-600">{row.errors?.length ? row.errors.join('; ') : 'Sesuai'}</td>{row.values.map((value, index) => <td key={index} className="max-w-56 px-5 py-4 text-slate-700">{value || '-'}</td>)}</tr>)}</tbody>
                            </table>
                        </div>
                    </div>}
                </section>
            </div>
        </LegacyPageShell>
    );
}
