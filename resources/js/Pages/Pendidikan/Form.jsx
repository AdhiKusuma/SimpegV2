import { Head, Link, useForm } from '@inertiajs/react';
import LegacyPageShell from '../../Components/LegacyPageShell';
import { EmployeeCombobox } from '../../Components/SearchableCombobox';
import SelectCombobox from '../../Components/SelectCombobox';

const defaults = { id_peg: '', id_sekolah: '', jenjang: '', nama_sekolah: '', lokasi: '', jurusan: '', no_ijazah: '', tgl_ijazah: '', kepala: '', status: '', th_masuk: '', th_lulus: '' };

export default function PendidikanForm({ mode, record, employees, levels, statuses }) {
    const isEdit = mode === 'edit';
    const form = useForm({ ...defaults, ...(record || {}) });

    function submit(event) {
        event.preventDefault();
        if (isEdit) {
            form.transform((data) => ({ ...data, _method: 'put' })).post(`/pendidikan/${record.id_pendidikan}`, { forceFormData: true });
            return;
        }
        form.post('/pendidikan');
    }

    const input = (name, label, type = 'text', required = false) => <div><label htmlFor={name} className="mb-2 block text-xs font-bold uppercase tracking-wide text-slate-600">{label}{required && <span className="text-red-600"> *</span>}</label><input id={name} name={name} type={type} value={form.data[name] || ''} onChange={(event) => form.setData(name, event.target.value)} required={required} className="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm outline-none focus:border-emerald-600 focus:bg-white focus:ring-4 focus:ring-emerald-600/10" />{form.errors[name] && <p className="mt-1 text-xs text-red-600" role="alert">{form.errors[name]}</p>}</div>;

    return (
        <LegacyPageShell title={`${isEdit ? 'Edit' : 'Tambah'} Data Pendidikan`}>
            <Head title={`${isEdit ? 'Edit' : 'Tambah'} Data Pendidikan`} />
            <div className="mx-auto max-w-5xl"><div className="mb-6"><Link href="/pendidikan" className="text-sm font-bold text-emerald-600 hover:text-emerald-700">← Kembali ke Pendidikan</Link><p className="mt-4 mb-2 text-sm font-semibold uppercase tracking-[0.18em] text-emerald-600">Riwayat Kepegawaian</p><h1 className="text-3xl font-extrabold tracking-tight text-slate-900">{isEdit ? 'Edit' : 'Tambah'} data pendidikan</h1><p className="mt-2 text-slate-500">Lengkapi data pendidikan formal pegawai.</p></div>
                <form onSubmit={submit} className="simpeg-form-card"><div className="grid gap-5 sm:grid-cols-2"><div className="sm:col-span-2"><EmployeeCombobox employees={employees} value={form.data.id_peg} onChange={(value) => form.setData('id_peg', value)} disabled={isEdit} error={form.errors.id_peg} />{isEdit && <p className="mt-2 text-xs text-slate-500">Pegawai tidak dapat diubah saat edit, sesuai alur sistem.</p>}</div>{input('id_sekolah', 'Kode sekolah', 'text')}<SelectCombobox id="jenjang" label="Jenjang" value={form.data.jenjang || ''} onChange={(value) => form.setData('jenjang', value)} options={levels} emptyLabel="Pilih jenjang" required error={form.errors.jenjang} />{input('nama_sekolah', 'Nama sekolah / universitas', 'text', true)}{input('lokasi', 'Lokasi')}{input('jurusan', 'Jurusan')}{input('th_masuk', 'Tahun masuk')}{input('th_lulus', 'Tahun lulus')}{input('no_ijazah', 'Nomor ijazah')}{input('tgl_ijazah', 'Tanggal ijazah', 'date')}{input('kepala', 'Kepala sekolah / dekan')}<SelectCombobox id="status" label="Status" value={form.data.status || ''} onChange={(value) => form.setData('status', value)} options={statuses} emptyLabel="-" error={form.errors.status} /></div><div className="mt-8 flex justify-end gap-3"><Link href="/pendidikan" className="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-600">Batal</Link><button type="submit" disabled={form.processing} className="rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-emerald-700 disabled:opacity-60">{form.processing ? 'Menyimpan...' : isEdit ? 'Update Data' : 'Simpan Data'}</button></div></form>
            </div>
        </LegacyPageShell>
    );
}
