import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import LegacyPageShell from '../../Components/LegacyPageShell';
import { EmployeeCombobox } from '../../Components/SearchableCombobox';
import SelectCombobox from '../../Components/SelectCombobox';

const emptyRecord = { id_peg: '', jns_mutasi: '', no_mutasi: '', tgl_mutasi: new Date().toISOString().slice(0, 10), tmt: new Date().toISOString().slice(0, 10), sk_mutasi: '' };

export default function MutationForm({ mode, record, employees, types }) {
    const isEdit = mode === 'edit';
    const { flash } = usePage().props;
    const form = useForm({ ...emptyRecord, ...(record || {}), sk_mutasi: null });
    const [submitError, setSubmitError] = useState('');

    function submit(event) {
        event.preventDefault();
        setSubmitError('');

        const options = {
            forceFormData: true,
            onError: (errors) => {
                setSubmitError('Data belum tersimpan. Periksa pesan validasi di bawah, lalu coba lagi.');
                const firstInvalidField = Object.keys(errors)[0];
                if (firstInvalidField) {
                    document.getElementById(firstInvalidField)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            },
            onHttpException: () => {
                setSubmitError('Server gagal menyimpan perubahan. Muat ulang halaman dan coba lagi.');
                return false;
            },
            onNetworkError: () => {
                setSubmitError('Koneksi terputus. Perubahan belum dipastikan tersimpan; periksa koneksi lalu coba lagi.');
                return false;
            },
        };

        if (isEdit) {
            form.post(`/mutasi/${record.id_mutasi}`, options);
            return;
        }
        form.post('/mutasi', options);
    }

    const field = (name, label, type = 'text', required = true) => <div><label htmlFor={name} className="mb-2 block text-xs font-bold uppercase tracking-wide text-slate-600">{label}{required && <span className="text-red-600"> *</span>}</label><input id={name} name={name} type={type} value={form.data[name] || ''} onChange={(event) => form.setData(name, event.target.value)} required={required} className="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm outline-none focus:border-red-600 focus:bg-white focus:ring-4 focus:ring-red-600/10" />{form.errors[name] && <p className="mt-1 text-xs text-red-600" role="alert">{form.errors[name]}</p>}</div>;

    return (
        <LegacyPageShell title={`${isEdit ? 'Edit' : 'Tambah'} Data Pemberhentian`}>
            <Head title={`${isEdit ? 'Edit' : 'Tambah'} Data Pemberhentian`} />
            <div className="mx-auto max-w-4xl"><div className="mb-6"><Link href="/mutasi" className="text-sm font-bold text-red-600 hover:text-red-700">← Kembali ke Mutasi</Link><p className="mt-4 mb-2 text-sm font-semibold uppercase tracking-[0.18em] text-red-600">Mutasi Kepegawaian</p><h1 className="text-3xl font-extrabold tracking-tight text-slate-900">{isEdit ? 'Edit' : 'Tambah'} data pemberhentian</h1><p className="mt-2 text-slate-500">Semua perubahan status pegawai dan jabatan mengikuti transaksi sistem.</p></div>
                <form onSubmit={submit} encType="multipart/form-data" className="simpeg-form-card" noValidate>
                    {flash?.error && <div className="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800" role="alert" aria-live="assertive">{flash.error}</div>}
                    {submitError && <div className="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800" role="alert" aria-live="assertive">{submitError}</div>}
                    {Object.keys(form.errors).length > 0 && <div className="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert"><p className="font-bold">Perubahan belum tersimpan.</p><ul className="mt-2 list-disc space-y-1 pl-5">{Object.entries(form.errors).map(([name, message]) => <li key={name}>{message}</li>)}</ul></div>}
                    <div className="grid gap-5 sm:grid-cols-2">
                    <div className="sm:col-span-2">{isEdit ? <div><label className="mb-2 block text-xs font-bold uppercase tracking-wide text-slate-600">Pegawai <span className="text-red-600">*</span></label><div className="flex h-11 items-center rounded-xl border border-slate-200 bg-slate-100 px-3 text-sm text-slate-700">{employees.find((employee) => employee.id_peg === form.data.id_peg)?.nama || 'Pegawai'} — {form.data.id_peg}</div></div> : <EmployeeCombobox employees={employees} value={form.data.id_peg} onChange={(value) => form.setData('id_peg', value)} label="Pegawai" error={form.errors.id_peg} />}</div>
                    <SelectCombobox id="jns_mutasi" label="Jenis pemberhentian" value={form.data.jns_mutasi} onChange={(value) => form.setData('jns_mutasi', value)} options={types} emptyLabel="Pilih jenis" required error={form.errors.jns_mutasi} />
                    {field('no_mutasi', 'Nomor SK')}
                    {field('tgl_mutasi', 'Tanggal SK', 'date')}
                    {field('tmt', 'TMT efektif nonaktif', 'date')}
                    <div><label htmlFor="sk_mutasi" className="mb-2 block text-xs font-bold uppercase tracking-wide text-slate-600">Upload SK (PDF/JPG)</label><input id="sk_mutasi" name="sk_mutasi" type="file" accept=".pdf,.jpg,.jpeg,.png" onChange={(event) => form.setData('sk_mutasi', event.target.files[0] || null)} className="block h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-red-50 file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-red-700" />{isEdit && record.sk_mutasi && <p className="mt-2 text-xs text-slate-500">File saat ini: {record.sk_mutasi}. Upload file baru untuk mengganti.</p>}{form.errors.sk_mutasi && <p className="mt-1 text-xs text-red-600" role="alert">{form.errors.sk_mutasi}</p>}</div>
                    </div>
                    <div className="mt-8 flex justify-end gap-3"><Link href="/mutasi" className="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-600">Batal</Link><button type="submit" disabled={form.processing} className="rounded-lg bg-red-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-red-700 disabled:cursor-wait disabled:opacity-60">{form.processing ? 'Menyimpan...' : isEdit ? 'Update Data' : 'Simpan Data'}</button></div>
                </form>
            </div>
        </LegacyPageShell>
    );
}
