import { Head, Link, useForm } from '@inertiajs/react';
import LegacyPageShell from '../../Components/LegacyPageShell';
import { EmployeeCombobox } from '../../Components/SearchableCombobox';

const defaults = { id_peg: '', seminar: '', tempat: '', penyelenggara: '', tgl_mulai: '', tgl_selesai: '', no_piagam: '', tgl_piagam: '' };

export default function SeminarForm({ mode, record, employees }) {
    const isEdit = mode === 'edit';
    const form = useForm({ ...defaults, ...(record || {}) });

    function submit(event) {
        event.preventDefault();
        if (isEdit) {
            form.transform((data) => ({ ...data, _method: 'put' })).post(`/seminar/${record.id_seminar}`);
            return;
        }
        form.post('/seminar');
    }

    const input = (name, label, type = 'text', maxLength = undefined, required = false) => <div><label htmlFor={name} className="mb-2 block text-xs font-bold uppercase tracking-wide text-slate-600">{label}{required && <span className="text-red-600"> *</span>}</label><input id={name} name={name} type={type} value={form.data[name] || ''} onChange={(event) => form.setData(name, event.target.value)} maxLength={maxLength} required={required} className="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm outline-none focus:border-cyan-700 focus:bg-white focus:ring-4 focus:ring-cyan-700/10" />{form.errors[name] && <p className="mt-1 text-xs text-red-600" role="alert">{form.errors[name]}</p>}</div>;

    return (
        <LegacyPageShell title={`${isEdit ? 'Edit' : 'Tambah'} Data Seminar`}>
            <Head title={`${isEdit ? 'Edit' : 'Tambah'} Data Seminar`} />
            <div className="mx-auto max-w-5xl"><div className="mb-6"><Link href="/seminar" className="text-sm font-bold text-cyan-800 hover:text-cyan-900">← Kembali ke Seminar</Link><p className="mb-2 mt-4 text-sm font-semibold uppercase tracking-[0.18em] text-cyan-700">Riwayat Kepegawaian</p><h1 className="text-3xl font-extrabold tracking-tight text-slate-900">{isEdit ? 'Edit' : 'Tambah'} data seminar</h1><p className="mt-2 text-slate-500">Kolom dan tanggal mengikuti form seminar sistem.</p></div><form onSubmit={submit} className="simpeg-form-card"><div className="grid gap-5 sm:grid-cols-2"><div className="sm:col-span-2"><EmployeeCombobox employees={employees} value={form.data.id_peg || ''} onChange={(value) => form.setData('id_peg', value)} disabled={isEdit} error={form.errors.id_peg} />{isEdit && <p className="mt-2 text-xs text-slate-500">Pegawai tidak dapat diubah saat edit, sesuai alur sistem.</p>}</div>{input('seminar', 'Nama seminar', 'text', 128, true)}{input('tempat', 'Tempat', 'text', 32, true)}{input('penyelenggara', 'Penyelenggara', 'text', 64, true)}{input('no_piagam', 'Nomor piagam', 'text', 32, true)}{input('tgl_mulai', 'Tanggal mulai', 'date', undefined, true)}{input('tgl_selesai', 'Tanggal selesai', 'date', undefined, true)}{input('tgl_piagam', 'Tanggal piagam', 'date')}</div><div className="mt-8 flex flex-wrap justify-end gap-3"><Link href="/seminar" className="inline-flex min-h-11 items-center rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-600">Batal</Link><button type="submit" disabled={form.processing} className="min-h-11 rounded-lg bg-cyan-700 px-5 py-2.5 text-sm font-bold text-white hover:bg-cyan-800 disabled:opacity-60">{form.processing ? 'Menyimpan...' : isEdit ? 'Update data' : 'Simpan data'}</button></div></form></div>
        </LegacyPageShell>
    );
}
