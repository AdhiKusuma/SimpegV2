import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import LegacyPageShell from '../../Components/LegacyPageShell';

const statusLabels = { new: 'Data baru', update: 'Update data', rename: 'Ganti ID', invalid: 'Perlu diperiksa' };
const statusStyles = { new: 'bg-emerald-50 text-emerald-700', update: 'bg-amber-50 text-amber-700', rename: 'bg-red-50 text-red-700', invalid: 'bg-red-50 text-red-700' };

export default function EmployeesImport() {
    const [file, setFile] = useState(null);
    const [preview, setPreview] = useState(null);
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);
    const saveForm = useForm({ preview_token: '' });

    async function previewFile(event) {
        event.preventDefault();
        if (!file) {
            setError('Pilih file XLSX terlebih dahulu.');
            return;
        }

        setLoading(true);
        setError('');
        const payload = new FormData();
        payload.append('file_xlsx', file);

        try {
            const response = await fetch('/pegawai/import/preview', {
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
        } catch (requestError) {
            setError(requestError.message);
        } finally {
            setLoading(false);
        }
    }

    function saveImport() {
        if (!preview?.token || !preview?.rows?.length || preview.invalid_count > 0) {
            return;
        }

        saveForm.setData('preview_token', preview.token);
        saveForm.post('/pegawai/import', { preserveScroll: true });
    }

    return (
        <LegacyPageShell title="Import Data Pegawai">
            <Head title="Import Data Pegawai" />
            <div className="mx-auto max-w-6xl">
                <div className="mb-6"><Link href="/pegawai" className="text-sm font-bold text-blue-600 hover:text-blue-700">Kembali ke data pegawai</Link><p className="mb-2 mt-5 text-sm font-semibold uppercase tracking-[0.18em] text-blue-600">Data Pegawai</p><h1 className="text-3xl font-extrabold tracking-tight text-slate-900">Import pegawai</h1><p className="mt-2 text-slate-500">Gunakan template resmi, unggah file Excel, lalu cek preview sebelum data disimpan ke sistem.</p></div>

                <section className="simpeg-form-card">
                    <div className="rounded-xl border border-blue-100 bg-blue-50 p-4 text-sm text-blue-900"><h2 className="font-extrabold">Format file</h2><p className="mt-2 leading-6">Gunakan file XLSX dari template resmi. Jangan mengubah nama, urutan, atau jumlah 18 kolom agar preview dan import dapat membaca data dengan benar.</p><a href="/pegawai/import/template" download className="mt-4 inline-flex min-h-11 items-center rounded-lg bg-blue-600 px-3 py-2 text-sm font-bold text-white hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-600/20">Unduh template XLSX</a></div>

                    <form onSubmit={previewFile} className="mt-6 grid gap-4 lg:grid-cols-[1fr_auto] lg:items-end"><div><label htmlFor="employee-import-file" className="mb-2 block text-sm font-bold text-slate-800">File XLSX</label><input id="employee-import-file" type="file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required onChange={(event) => { setFile(event.target.files?.[0] || null); setPreview(null); setError(''); }} className="block min-h-11 w-full rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm file:mr-4 file:rounded-lg file:border-0 file:bg-blue-100 file:px-3 file:py-2 file:font-bold file:text-blue-700" />{error && <p className="mt-2 text-sm font-semibold text-red-600" role="alert">{error}</p>}</div><button type="submit" disabled={loading} className="inline-flex min-h-11 items-center justify-center rounded-xl bg-blue-600 px-5 py-2 text-sm font-bold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60 focus:outline-none focus:ring-4 focus:ring-blue-600/20">{loading ? 'Memeriksa...' : 'Preview file'}</button></form>

                    {preview && <div className="mt-8"><div className="mb-4 flex flex-wrap items-center justify-between gap-3"><div><h2 className="text-lg font-extrabold text-slate-900">Preview import</h2><p className="mt-1 text-sm text-slate-500">Menampilkan {preview.preview.length} dari {preview.total} baris. Proses simpan akan menggunakan file yang sudah dikunci server.</p>{preview.invalid_count > 0 && <><p className="mt-1 text-sm font-semibold text-red-600" role="alert">{preview.invalid_count} baris belum memenuhi validasi wajib. Perbaiki file XLSX lalu unggah kembali.</p><ul className="mt-2 space-y-1 text-sm text-red-700">{preview.invalid_rows.slice(0, 8).map((item) => <li key={item.row_number}>Baris {item.row_number}: {item.errors.join('; ')}</li>)}{preview.invalid_count > 8 && <li>dan {preview.invalid_count - 8} baris lainnya.</li>}</ul></>}</div><button type="button" onClick={saveImport} disabled={saveForm.processing || !preview.token || preview.rows.length === 0 || preview.invalid_count > 0} className="inline-flex min-h-11 items-center rounded-xl bg-emerald-700 px-5 py-2 text-sm font-bold text-white transition hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-60 focus:outline-none focus:ring-4 focus:ring-emerald-700/20">{saveForm.processing ? 'Menyimpan...' : 'Proses import'}</button></div><div className="overflow-x-auto rounded-xl border border-slate-200"><table className="min-w-[1250px] w-full text-left text-xs"><thead className="border-b border-slate-100 text-xs uppercase tracking-wide text-slate-500"><tr><th className="px-5 py-4 font-semibold">Status</th><th className="px-5 py-4 font-semibold">Validasi</th>{preview.columns.map((column, index) => <th key={`${column}-${index}`} className="px-5 py-4 font-semibold">{column || `Kolom ${index + 1}`}</th>)}</tr></thead><tbody className="divide-y divide-slate-100">{preview.preview.map((row, index) => <tr key={index} className="align-top transition hover:bg-slate-50"><td className="px-5 py-4"><span className={`inline-flex whitespace-nowrap rounded-full px-2 py-1 font-bold ${statusStyles[row.status] || statusStyles.invalid}`}>{statusLabels[row.status] || row.status}</span></td><td className="max-w-64 px-5 py-4 text-slate-600">{row.errors?.length ? row.errors.join('; ') : 'Sesuai'}</td>{row.values.map((value, valueIndex) => <td key={valueIndex} className="max-w-48 px-5 py-4 text-slate-700">{value || '-'}</td>)}</tr>)}</tbody></table></div>{saveForm.errors.preview_token && <p className="mt-3 text-sm font-semibold text-red-600" role="alert">{saveForm.errors.preview_token}</p>}</div>}
                </section>
            </div>
        </LegacyPageShell>
    );
}
