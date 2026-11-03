import { Head, Link, useForm } from '@inertiajs/react';
import LegacyPageShell from '../../Components/LegacyPageShell';
import SearchableCombobox, { EmployeeCombobox } from '../../Components/SearchableCombobox';
import SelectCombobox from '../../Components/SelectCombobox';

const defaults = { id_peg: '', hukuman: '', keterangan: '', pejabat_sk: '', jabatan_sk: '', no_sk: '', tgl_sk: new Date().toISOString().slice(0, 10), dokumen: null, pejabat_pulih: '', jabatan_pulih: '', no_pulih: '', tgl_pulih: '' };

export default function PelanggaranForm({ mode, record, employees, officials, types }) {
    const isEdit = mode === 'edit';
    const form = useForm({ ...defaults, ...(record || {}), dokumen: null });

    function selectOfficial(field, value) {
        const official = officials.find((item) => item.nama === value);
        const positionField = field === 'pejabat_sk' ? 'jabatan_sk' : 'jabatan_pulih';
        form.setData(field, value);
        form.setData(positionField, official?.jabatan || '');
    }

    function submit(event) {
        event.preventDefault();
        if (isEdit) {
            form.transform((data) => ({ ...data, _method: 'put' })).post(`/pelanggaran/${record.id_hukum}`, { forceFormData: true });
            return;
        }
        form.post('/pelanggaran', { forceFormData: true });
    }

    const input = (name, label, type = 'text', required = false) => <div><label htmlFor={name} className="mb-2 block text-xs font-bold uppercase tracking-wide text-slate-600">{label}{required && <span className="text-red-600"> *</span>}</label><input id={name} name={name} type={type} value={form.data[name] || ''} onChange={(event) => form.setData(name, event.target.value)} required={required} readOnly={name === 'jabatan_sk' || name === 'jabatan_pulih'} className="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm outline-none focus:border-amber-600 focus:bg-white focus:ring-4 focus:ring-amber-600/10 read-only:bg-slate-100" />{form.errors[name] && <p className="mt-1 text-xs text-red-600" role="alert">{form.errors[name]}</p>}</div>;
    const select = (name, label, options, required = false) => <SelectCombobox id={name} name={name} label={label} value={form.data[name] || ''} onChange={(value) => form.setData(name, value)} options={options} emptyLabel={`Pilih ${label.toLowerCase()}`} required={required} error={form.errors[name]} />;

    return (
        <LegacyPageShell title={`${isEdit ? 'Edit' : 'Tambah'} Data Pelanggaran`}>
            <Head title={`${isEdit ? 'Edit' : 'Tambah'} Data Pelanggaran`} />
            <div className="mx-auto max-w-5xl"><div className="mb-6"><Link href="/pelanggaran" className="text-sm font-bold text-amber-600 hover:text-amber-700">← Kembali ke Pelanggaran</Link><p className="mt-4 mb-2 text-sm font-semibold uppercase tracking-[0.18em] text-amber-600">Disiplin Pegawai</p><h1 className="text-3xl font-extrabold tracking-tight text-slate-900">{isEdit ? 'Edit' : 'Tambah'} data pelanggaran</h1><p className="mt-2 text-slate-500">Form pencatatan hukuman disiplin dan detail pemulihan opsional.</p></div>
                <form onSubmit={submit} encType="multipart/form-data" className="simpeg-form-card"><div className="grid gap-5 sm:grid-cols-2">
                    <div className="sm:col-span-2"><EmployeeCombobox employees={employees} value={form.data.id_peg} onChange={(value) => form.setData('id_peg', value)} label="Pegawai" error={form.errors.id_peg} /></div>
                    {select('hukuman', 'Jenis sanksi', types, true)}
                    {input('keterangan', 'Keterangan')}
                    <SearchableCombobox id="pejabat_sk" label="Pejabat pengesah" value={form.data.pejabat_sk || ''} options={officials} optionValue={(official) => official.nama} optionLabel={(official) => official.nama} optionDescription={(official) => official.jabatan} placeholder="Cari pejabat pengesah" emptyMessage="Pejabat tidak ditemukan." required onChange={(value) => selectOfficial('pejabat_sk', value)} error={form.errors.pejabat_sk} />
                    {input('jabatan_sk', 'Jabatan pengesah', 'text', true)}
                    {input('no_sk', 'Nomor SK', 'text', true)}
                    {input('tgl_sk', 'Tanggal SK', 'date', true)}
                    <div><label htmlFor="dokumen" className="mb-2 block text-xs font-bold uppercase tracking-wide text-slate-600">Upload dokumen (PDF/JPG)</label><input id="dokumen" name="dokumen" type="file" accept=".pdf,.jpg,.jpeg,.png" onChange={(event) => form.setData('dokumen', event.target.files[0] || null)} className="block h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-amber-50 file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-amber-700" />{isEdit && record.dokumen && <p className="mt-2 text-xs text-slate-500">File saat ini: {record.dokumen}. Upload file baru untuk mengganti.</p>}{form.errors.dokumen && <p className="mt-1 text-xs text-red-600" role="alert">{form.errors.dokumen}</p>}</div>
                </div><div className="my-8 border-t border-slate-100 pt-6"><h2 className="mb-5 text-sm font-extrabold uppercase tracking-wide text-slate-500">Detail pemulihan (opsional)</h2><div className="grid gap-5 sm:grid-cols-2"><SearchableCombobox id="pejabat_pulih" label="Pejabat pemulih" value={form.data.pejabat_pulih || ''} options={officials} optionValue={(official) => official.nama} optionLabel={(official) => official.nama} optionDescription={(official) => official.jabatan} placeholder="Cari pejabat pemulih" emptyMessage="Pejabat tidak ditemukan." onChange={(value) => selectOfficial('pejabat_pulih', value)} error={form.errors.pejabat_pulih} />{input('jabatan_pulih', 'Jabatan pemulih')}{input('no_pulih', 'Nomor SK pemulihan')}{input('tgl_pulih', 'Tanggal pemulihan', 'date')}</div></div><div className="flex justify-end gap-3"><Link href="/pelanggaran" className="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-600">Batal</Link><button type="submit" disabled={form.processing} className="rounded-lg bg-amber-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-amber-700 disabled:opacity-60">{form.processing ? 'Menyimpan...' : isEdit ? 'Update Data' : 'Simpan Data'}</button></div></form>
            </div>
        </LegacyPageShell>
    );
}
