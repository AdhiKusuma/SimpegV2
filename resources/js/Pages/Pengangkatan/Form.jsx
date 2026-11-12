import { Head, Link, useForm } from '@inertiajs/react';
import LegacyPageShell from '../../Components/LegacyPageShell';
import { EmployeeCombobox } from '../../Components/SearchableCombobox';
import SelectCombobox from '../../Components/SelectCombobox';

const defaults = {
    id_peg: '',
    jns_mutasi: '',
    tgl_mutasi: '',
    no_mutasi: '',
    tmt: '',
    sk_mutasi: null,
};

export default function PengangkatanForm({ mode, record, employees, types }) {
    const isEdit = mode === 'edit';
    const form = useForm({ ...defaults, ...(record || {}) });

    function submit(event) {
        event.preventDefault();

        if (isEdit) {
            form.transform((data) => ({ tmt: data.tmt, _method: 'put' })).post(`/pengangkatan/${record.id_angkat}`);
            return;
        }

        form.post('/pengangkatan', { forceFormData: true });
    }

    const labelClass = 'mb-2 block text-sm font-medium simpeg-muted';
    const inputClass = 'h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm outline-none focus:border-blue-600 focus:bg-white focus:ring-4 focus:ring-blue-600/10';

    const input = (name, label, type = 'text', required = false, disabled = false) => (
        <div className="min-w-0">
            <label htmlFor={name} className={labelClass}>
                {label}{required && <span className="text-red-600"> *</span>}
            </label>
            <input
                id={name}
                name={name}
                type={type}
                value={form.data[name] || ''}
                onChange={(event) => form.setData(name, event.target.value)}
                required={required}
                disabled={disabled}
                className={`${inputClass} disabled:cursor-not-allowed disabled:bg-slate-100`}
            />
            {form.errors[name] && <p className="mt-1 text-xs text-red-600" role="alert">{form.errors[name]}</p>}
        </div>
    );

    return (
        <LegacyPageShell title={`${isEdit ? 'Edit' : 'Tambah'} Pengangkatan Pegawai`}>
            <Head title={`${isEdit ? 'Edit' : 'Tambah'} Pengangkatan Pegawai`} />

<div className="simpeg-form-page w-full">
                <div className="mb-6">
                    <Link href="/pengangkatan" className="text-sm font-medium text-blue-600 hover:text-blue-700">← Kembali ke Pengangkatan</Link>
                    <p className="mt-4 text-sm font-medium text-blue-600">Referensi kepegawaian</p>
                    <h1 className="mt-2 text-3xl font-medium text-slate-900">{isEdit ? 'Edit' : 'Tambah'} pengangkatan pegawai</h1>
                    <p className="mt-2 max-w-2xl text-slate-500">Catat pengangkatan pegawai berdasarkan jenis, tanggal, dan dokumen keputusan.</p>
                </div>

<form onSubmit={submit} encType="multipart/form-data" className="simpeg-form-card simpeg-standard-form">
                    {isEdit ? (
                        <>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="min-w-0">
                                    <span className={labelClass}>ID pegawai</span>
                                    <p className="simpeg-input flex h-11 items-center rounded-xl border px-3 font-mono text-sm">{record.id_peg_baru || record.id_peg || '-'}</p>
                                </div>
                                {input('jns_mutasi', 'Jenis pengangkatan', 'text', false, true)}
                                {input('no_mutasi', 'Nomor SK', 'text', false, true)}
                                {input('tgl_mutasi', 'Tanggal SK', 'date', false, true)}
                                {input('tmt', 'TMT', 'date', true)}
                            </div>
                            <p className="mt-5 text-xs text-slate-500">Sesuai handler sistem, mode edit hanya memperbarui TMT pengangkatan.</p>
                        </>
                    ) : (
                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="min-w-0">
                                <EmployeeCombobox employees={employees} value={form.data.id_peg || ''} onChange={(value) => form.setData('id_peg', value)} label="Pegawai" error={form.errors.id_peg} />
                            </div>
                            <div className="min-w-0">
                                <SelectCombobox id="jns_mutasi" name="jns_mutasi" label="Jenis pengangkatan" value={form.data.jns_mutasi || ''} onChange={(value) => form.setData('jns_mutasi', value)} options={types} emptyLabel="Pilih jenis" required error={form.errors.jns_mutasi} />
                            </div>
                            {input('no_mutasi', 'Nomor SK pengangkatan', 'text', true)}
                            {input('tgl_mutasi', 'Tanggal SK', 'date', true)}
                            {input('tmt', 'TMT', 'date', true)}
                            <div className="min-w-0 md:col-span-2">
                                <label htmlFor="sk_mutasi" className={labelClass}>SK pengangkatan <span className="text-xs font-normal text-slate-400">(PDF, opsional)</span></label>
                                <input id="sk_mutasi" name="sk_mutasi" type="file" accept="application/pdf,.pdf" onChange={(event) => form.setData('sk_mutasi', event.target.files?.[0] || null)} className="block min-h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-blue-50 file:px-3 file:py-2 file:font-medium file:text-blue-700" />
                                {form.errors.sk_mutasi && <p className="mt-1 text-xs text-red-600" role="alert">{form.errors.sk_mutasi}</p>}
                            </div>
                        </div>
                    )}

                    <div className="mt-6 flex flex-wrap justify-end gap-3 border-t border-slate-100 pt-5">
                        <Link href="/pengangkatan" className="inline-flex min-h-11 items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600">Batal</Link>
                        <button type="submit" disabled={form.processing} className="min-h-11 rounded-lg bg-blue-600 px-5 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60">{form.processing ? 'Menyimpan...' : isEdit ? 'Update TMT' : 'Simpan pengangkatan'}</button>
                    </div>
                </form>
            </div>
        </LegacyPageShell>
    );
}
