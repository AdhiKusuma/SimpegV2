import { Head, Link, useForm } from '@inertiajs/react';
import LegacyPageShell from '../../Components/LegacyPageShell';
import { EmployeeCombobox } from '../../Components/SearchableCombobox';

const today = new Date().toISOString().slice(0, 10);
const defaults = { id_peg: '', diklat: '', penyelenggara: '', tempat: '', biaya: '', angkatan: '', tahun: today.slice(0, 4), date_reg: today };

function formatCurrency(value) {
    if (value === null || value === undefined || value === '') return '';
    return Number(value).toLocaleString('id-ID');
}

export default function DiklatForm({ mode, record, employees }) {
    const isEdit = mode === 'edit';
    const form = useForm({ ...defaults, ...(record || {}) });

    function submit(event) {
        event.preventDefault();
        if (isEdit) {
            form.transform((data) => ({ ...data, _method: 'put' })).post(`/diklat/${record.id_diklat}`, { forceFormData: true });
            return;
        }
        form.post('/diklat');
    }

    const input = (name, label, type = 'text', required = false) => <div><label htmlFor={name} className="mb-2 block text-xs font-bold uppercase tracking-wide text-slate-600">{label}{required && <span className="text-red-600"> *</span>}</label><input id={name} name={name} type={type} value={name === 'biaya' ? formatCurrency(form.data[name]) : form.data[name] || ''} onChange={(event) => form.setData(name, name === 'biaya' ? event.target.value.replace(/[^0-9]/g, '') : event.target.value)} required={required} className="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm outline-none focus:border-blue-600 focus:bg-white focus:ring-4 focus:ring-blue-600/10" />{form.errors[name] && <p className="mt-1 text-xs text-red-600" role="alert">{form.errors[name]}</p>}</div>;

    return (
        <LegacyPageShell title={`${isEdit ? 'Edit' : 'Tambah'} Data Diklat`}>
            <Head title={`${isEdit ? 'Edit' : 'Tambah'} Data Diklat`} />
            <div className="mx-auto max-w-4xl"><div className="mb-6"><Link href="/diklat" className="text-sm font-bold text-blue-600 hover:text-blue-700">← Kembali ke Diklat</Link><p className="mt-4 mb-2 text-sm font-semibold uppercase tracking-[0.18em] text-blue-600">Pengembangan Pegawai</p><h1 className="text-3xl font-extrabold tracking-tight text-slate-900">{isEdit ? 'Edit' : 'Tambah'} data diklat</h1><p className="mt-2 text-slate-500">Lengkapi data pelatihan pegawai sesuai kolom sistem.</p></div>
                <form onSubmit={submit} className="simpeg-form-card"><div className="grid gap-5 sm:grid-cols-2"><div className="sm:col-span-2"><EmployeeCombobox employees={employees} value={form.data.id_peg} onChange={(value) => form.setData('id_peg', value)} disabled={isEdit} error={form.errors.id_peg} />{isEdit && <p className="mt-2 text-xs text-slate-500">Pegawai tidak dapat diubah saat edit, sesuai alur sistem.</p>}</div>{input('diklat', 'Nama diklat / pelatihan', 'text', true)}{input('penyelenggara', 'Penyelenggara')}{input('tempat', 'Lokasi / tempat')}{input('tahun', 'Tahun', 'number', true)}{input('angkatan', 'Angkatan')}{input('biaya', 'Biaya (Rp)')}{input('date_reg', 'Tanggal pelatihan', 'date', true)}</div><div className="mt-8 flex justify-end gap-3"><Link href="/diklat" className="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-600">Batal</Link><button type="submit" disabled={form.processing} className="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-60">{form.processing ? 'Menyimpan...' : isEdit ? 'Update Data' : 'Simpan Data'}</button></div></form>
            </div>
        </LegacyPageShell>
    );
}
