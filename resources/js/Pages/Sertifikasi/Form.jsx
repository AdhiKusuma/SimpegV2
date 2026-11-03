import { Head, Link, useForm } from '@inertiajs/react';
import LegacyPageShell from '../../Components/LegacyPageShell';
import { EmployeeCombobox } from '../../Components/SearchableCombobox';

const defaults = { id_peg: '', sertifikasi: '', penyelenggara: '', sertifikat: '', tgl_sertifikat: '', tgl_expired: '' };

export default function SertifikasiForm({ mode, record, employees }) {
    const isEdit = mode === 'edit';
    const form = useForm({ ...defaults, ...(record || {}) });

    function submit(event) {
        event.preventDefault();
        if (isEdit) {
            form.transform((data) => ({ ...data, _method: 'put' })).post('/sertifikasi/' + record.id_sertif, { forceFormData: true });
        } else {
            form.post('/sertifikasi');
        }
    }

    const input = (name, label, type = 'text', required = false) => <div><label htmlFor={name} className="mb-2 block text-xs font-bold uppercase tracking-wide text-slate-600">{label}{required && <span className="text-red-600"> *</span>}</label><input id={name} type={type} value={form.data[name] || ''} onChange={(event) => form.setData(name, event.target.value)} required={required} className="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm" />{form.errors[name] && <p className="mt-1 text-xs text-red-600">{form.errors[name]}</p>}</div>;

    return (
        <LegacyPageShell title={(isEdit ? 'Edit' : 'Tambah') + ' Data Sertifikasi'}>
            <Head title={(isEdit ? 'Edit' : 'Tambah') + ' Data Sertifikasi'} />
            <div className="mx-auto max-w-4xl">
                <div className="mb-6">
                    <Link href="/sertifikasi" className="text-sm font-bold text-amber-600">← Kembali ke Sertifikasi</Link>
                    <p className="mb-2 mt-4 text-sm font-semibold uppercase tracking-[0.18em] text-amber-600">Kompetensi Pegawai</p>
                    <h1 className="text-3xl font-extrabold tracking-tight text-slate-900">{isEdit ? 'Edit' : 'Tambah'} data sertifikasi</h1>
                </div>
                <form onSubmit={submit} className="simpeg-form-card">
                    <div className="grid gap-5 sm:grid-cols-2">
                        <div className="sm:col-span-2">
                            <EmployeeCombobox employees={employees} value={form.data.id_peg} onChange={(value) => form.setData('id_peg', value)} disabled={isEdit} error={form.errors.id_peg} />
                        </div>
                        {input('sertifikasi', 'Nama sertifikasi', 'text', true)}
                        {input('penyelenggara', 'Penyelenggara')}
                        {input('sertifikat', 'Nomor sertifikat')}
                        {input('tgl_sertifikat', 'Tanggal sertifikat', 'date')}
                        {input('tgl_expired', 'Tanggal expired', 'date')}
                    </div>
                    <div className="mt-8 flex justify-end gap-3">
                        <Link href="/sertifikasi" className="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-600">Batal</Link>
                        <button type="submit" disabled={form.processing} className="rounded-lg bg-amber-600 px-5 py-2.5 text-sm font-bold text-white">{form.processing ? 'Menyimpan...' : isEdit ? 'Update Data' : 'Simpan Data'}</button>
                    </div>
                </form>
            </div>
        </LegacyPageShell>
    );
}
