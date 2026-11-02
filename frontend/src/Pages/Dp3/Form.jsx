import { Head, Link, useForm } from '@inertiajs/react';
import LegacyPageShell from '../../Components/LegacyPageShell';
import { EmployeeCombobox } from '../../Components/SearchableCombobox';
import SelectCombobox from '../../Components/SelectCombobox';

const defaults = { id_peg: '', periode_awal: '', periode_akhir: '', pejabat_penilai: '', atasan_pejabat_penilai: '', nilai_kesetiaan: 0, nilai_prestasi: 0, nilai_tgjwb: 0, nilai_ketaatan: 0, nilai_kejujuran: 0, nilai_kerjasama: 0, nilai_prakarsa: 0, nilai_kepemimpinan: 0, hasil_penilaian: 'Baik' };
const scores = [['nilai_kesetiaan', 'Kesetiaan'], ['nilai_prestasi', 'Prestasi'], ['nilai_tgjwb', 'Tanggung jawab'], ['nilai_ketaatan', 'Ketaatan'], ['nilai_kejujuran', 'Kejujuran'], ['nilai_kerjasama', 'Kerjasama'], ['nilai_prakarsa', 'Prakarsa'], ['nilai_kepemimpinan', 'Kepemimpinan']];

export default function Dp3Form({ mode, record, employees, results }) {
    const isEdit = mode === 'edit';
    const form = useForm({ ...defaults, ...(record || {}) });

    function submit(event) {
        event.preventDefault();
        if (isEdit) {
            form.transform((data) => ({ ...data, _method: 'put' })).post(`/dp3/${record.id_dp3}`);
            return;
        }
        form.post('/dp3');
    }

    const input = (name, label, type = 'text', required = false, extra = {}) => <div><label htmlFor={name} className="mb-2 block text-xs font-bold uppercase tracking-wide text-slate-600">{label}{required && <span className="text-red-600"> *</span>}</label><input id={name} name={name} type={type} value={form.data[name] ?? ''} onChange={(event) => form.setData(name, event.target.value)} required={required} {...extra} className="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm outline-none focus:border-blue-700 focus:bg-white focus:ring-4 focus:ring-blue-700/10" />{form.errors[name] && <p className="mt-1 text-xs text-red-600" role="alert">{form.errors[name]}</p>}</div>;

    return (
        <LegacyPageShell title={`${isEdit ? 'Edit' : 'Tambah'} Data SKP`}>
            <Head title={`${isEdit ? 'Edit' : 'Tambah'} Data SKP`} />
            <div className="mx-auto max-w-5xl"><div className="mb-6"><Link href={isEdit ? `/dp3/${record.id_dp3}` : '/dp3'} className="text-sm font-bold text-blue-700 hover:text-blue-800">← Kembali ke SKP</Link><p className="mb-2 mt-4 text-sm font-semibold uppercase tracking-[0.18em] text-blue-700">Riwayat Kepegawaian</p><h1 className="text-3xl font-extrabold tracking-tight text-slate-900">{isEdit ? 'Edit' : 'Tambah'} data SKP</h1><p className="mt-2 text-slate-500">Kolom mengikuti form penilaian SKP sistem.</p></div><form onSubmit={submit} className="simpeg-form-card"><div className="grid gap-5 sm:grid-cols-2"><div className="sm:col-span-2"><EmployeeCombobox employees={employees} value={form.data.id_peg || ''} onChange={(value) => form.setData('id_peg', value)} disabled={isEdit} error={form.errors.id_peg} />{isEdit && <p className="mt-2 text-xs text-slate-500">Pegawai tidak diubah saat edit, sesuai alur sistem.</p>}</div>{input('periode_awal', 'Periode penilaian mulai', 'date', true)}{input('periode_akhir', 'Periode penilaian sampai', 'date', true)}{input('pejabat_penilai', 'Nama pejabat penilai', 'text', true, { maxLength: 64 })}{input('atasan_pejabat_penilai', 'Nama atasan pejabat penilai', 'text', true, { maxLength: 64 })}</div><div className="mt-8"><h2 className="text-lg font-bold text-slate-900">Penilaian</h2><p className="mt-1 text-sm text-slate-500">Isi angka sesuai dokumen SKP. Nilai kosong mengikuti perilaku sistem dan disimpan sebagai nol.</p><div className="mt-4 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">{scores.map(([name, label]) => input(name, label, 'number', false, { min: 0, max: 999 }))}</div></div><div className="mt-8 max-w-md"><SelectCombobox id="hasil_penilaian" name="hasil_penilaian" label="Hasil penilaian" value={form.data.hasil_penilaian || ''} onChange={(value) => form.setData('hasil_penilaian', value)} options={results} required error={form.errors.hasil_penilaian} /></div><div className="mt-8 flex flex-wrap justify-end gap-3"><Link href={isEdit ? `/dp3/${record.id_dp3}` : '/dp3'} className="inline-flex min-h-11 items-center rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-600">Batal</Link><button type="submit" disabled={form.processing} className="min-h-11 rounded-lg bg-blue-700 px-5 py-2.5 text-sm font-bold text-white hover:bg-blue-800 disabled:opacity-60">{form.processing ? 'Menyimpan...' : isEdit ? 'Update data' : 'Simpan data'}</button></div></form></div>
        </LegacyPageShell>
    );
}
