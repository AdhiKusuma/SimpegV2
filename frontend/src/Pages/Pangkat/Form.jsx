import { Head, Link, useForm } from '@inertiajs/react';
import LegacyPageShell from '../../Components/LegacyPageShell';
import { EmployeeCombobox } from '../../Components/SearchableCombobox';
import SelectCombobox from '../../Components/SelectCombobox';

const defaults = { id_peg: '', pangkat: '', gol: '', jns_pangkat: '', tmt_pangkat: '', pejabat_sk: '', no_sk: '', tgl_sk: '' };

export default function PangkatForm({ mode, record, employees, golongan }) {
    const isEdit = mode === 'edit';
    const form = useForm({ ...defaults, ...(record || {}) });

    function submit(event) {
        event.preventDefault();
        if (isEdit) {
            form.transform((data) => ({ ...data, _method: 'put' })).post(`/pangkat/${record.id_pangkat}`);
            return;
        }
        form.post('/pangkat');
    }

    const input = (name, label, type = 'text', required = false) => <div><label htmlFor={name} className="mb-2 block text-xs font-bold uppercase tracking-wide text-slate-600">{label}{required && <span className="text-red-600"> *</span>}</label><input id={name} name={name} type={type} value={form.data[name] || ''} onChange={(event) => form.setData(name, event.target.value)} required={required} className="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm outline-none focus:border-blue-600 focus:bg-white focus:ring-4 focus:ring-blue-600/10" />{form.errors[name] && <p className="mt-1 text-xs text-red-600" role="alert">{form.errors[name]}</p>}</div>;

    return (
        <LegacyPageShell title={`${isEdit ? 'Edit' : 'Tambah'} Data Pangkat`}>
            <Head title={`${isEdit ? 'Edit' : 'Tambah'} Data Pangkat`} />
            <div className="mx-auto max-w-5xl"><div className="mb-6"><Link href="/pangkat" className="text-sm font-bold text-blue-600 hover:text-blue-700">← Kembali ke Riwayat Pangkat</Link><p className="mb-2 mt-4 text-sm font-semibold uppercase tracking-[0.18em] text-blue-600">Riwayat Kepegawaian</p><h1 className="text-3xl font-extrabold tracking-tight text-slate-900">{isEdit ? 'Edit' : 'Tambah'} data pangkat</h1><p className="mt-2 text-slate-500">Kolom dan validasi mengikuti form pangkat sistem.</p></div>
                <form onSubmit={submit} className="simpeg-form-card"><div className="grid gap-5 sm:grid-cols-2"><div className="sm:col-span-2"><EmployeeCombobox employees={employees} value={form.data.id_peg || ''} onChange={(value) => form.setData('id_peg', value)} disabled={isEdit} error={form.errors.id_peg} />{isEdit && <p className="mt-2 text-xs text-slate-500">Pegawai tidak dapat diubah saat edit, sesuai alur sistem.</p>}</div>{input('pangkat', 'Pangkat', 'text', true)}<SelectCombobox id="gol" name="gol" label="Golongan" value={form.data.gol || ''} onChange={(value) => form.setData('gol', value)} options={golongan} emptyLabel="Pilih golongan" required error={form.errors.gol} />{input('jns_pangkat', 'Jenis pangkat', 'text', true)}{input('tmt_pangkat', 'TMT pangkat', 'date')}{input('pejabat_sk', 'Pejabat pengesah SK', 'text', true)}{input('no_sk', 'Nomor SK', 'text', true)}{input('tgl_sk', 'Tanggal pengesahan SK', 'date', true)}</div><div className="mt-8 flex flex-wrap justify-end gap-3"><Link href="/pangkat" className="inline-flex min-h-11 items-center rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-600">Batal</Link><button type="submit" disabled={form.processing} className="min-h-11 rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-60">{form.processing ? 'Menyimpan...' : isEdit ? 'Update data' : 'Simpan data'}</button></div></form>
            </div>
        </LegacyPageShell>
    );
}
