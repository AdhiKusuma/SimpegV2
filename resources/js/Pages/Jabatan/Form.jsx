import { Head, Link, router, useForm } from '@inertiajs/react';
import LegacyPageShell from '../../Components/LegacyPageShell';
import SearchableCombobox from '../../Components/SearchableCombobox';

export default function JabatanForm({ mode, formMode, employee, currentJob, employees, positions, units, record }) {
    const isEdit = mode === 'edit';
    const form = useForm(isEdit ? {
        kode_jabatan: record?.kode_jabatan ? String(record.kode_jabatan) : '',
        unit_kerja: record?.unit_kerja || '',
        tmt_jabatan: record?.tmt_jabatan || '',
        no_sk: record?.no_sk || '',
        tgl_sk: record?.tgl_sk || '',
    } : {
        id_peg: employee?.id_peg || '',
        form_mode: formMode,
        kode_jabatan: '',
        unit_kerja: '',
        no_sk: '',
        tgl_sk: '',
        tmt_mulai: '',
    });

    function selectEmployee(event) {
        const id = event.target.value;
        if (id) {
            router.get('/jabatan/create', { id_peg: id }, { preserveState: true });
        }
    }

    function submit(event) {
        event.preventDefault();
        if (isEdit) {
            form.transform((data) => ({ ...data, _method: 'put' })).post(`/jabatan/${record.id_jab}`);
            return;
        }

        form.post('/jabatan');
    }

    return (
        <LegacyPageShell title={isEdit ? 'Koreksi Jabatan' : 'Input Jabatan'}>
            <Head title={isEdit ? 'Koreksi Jabatan' : 'Input Jabatan'} />
            <div className="mb-6"><Link href="/jabatan" className="text-sm font-bold text-blue-600 hover:text-blue-700">← Kembali ke Data Jabatan</Link><p className="mb-2 mt-4 text-sm font-semibold uppercase tracking-[0.18em] text-blue-600">Riwayat Jabatan</p><h1 className="text-3xl font-extrabold tracking-tight text-slate-900">{isEdit ? 'Koreksi data jabatan' : 'Tetapkan jabatan pegawai'}</h1><p className="mt-2 max-w-2xl text-slate-500">Alur ini menggunakan tabel <span className="font-mono text-xs">tb_jabatan</span> sistem tanpa mengubah struktur database.</p></div>

            {!isEdit && !employee && <section className="max-w-2xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"><h2 className="text-lg font-extrabold text-slate-900">Pilih pegawai aktif</h2><p className="mt-2 text-sm text-slate-500">Pilih pegawai untuk memuat status jabatan saat ini dan form yang sesuai.</p><SearchableCombobox id="employee-select" label="Pegawai" options={employees} optionValue={(item) => item.id_peg} optionLabel={(item) => item.nama} optionDescription={(item) => `NIK: ${item.nik || item.id_peg}`} placeholder="Cari nama, NIK, atau ID pegawai" onChange={(id) => { if (id) { selectEmployee({ target: { value: id } }); } }} className="mt-5" /> </section>}

            {employee && <section className="max-w-4xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div className="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 pb-5"><div><p className="text-lg font-extrabold text-slate-900">{employee.nama}</p><p className="mt-1 text-sm text-slate-500">NIK: <span className="font-mono">{employee.nik || '-'}</span> · ID pegawai: <span className="font-mono">{employee.id_peg}</span></p></div><span className={`rounded-full px-3 py-1.5 text-xs font-bold ${formMode === 'history' ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700'}`}>{isEdit ? record?.status_jab : formMode === 'history' ? 'Tambah riwayat Non' : 'Jabatan Aktif'}</span></div>
                {currentJob && !isEdit && <p className="mt-5 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800">Jabatan aktif saat ini: <strong>{currentJob.jabatan || '-'}</strong>. Data baru akan disimpan sebagai riwayat <strong>Non</strong>.</p>}
                <form onSubmit={submit} className="mt-6 grid gap-5 md:grid-cols-2">
                    <SearchableCombobox id="kode_jabatan" label="Master jabatan" value={form.data.kode_jabatan} options={positions} optionValue={(item) => item.kode_jabatan} optionLabel={(item) => item.nama_jabatan} optionDescription={(item) => `Kode ${item.kode_jabatan}`} placeholder="Cari nama atau kode jabatan" description="Pilih jabatan yang tersedia di master. Nama jabatan mengikuti data master." onChange={(code) => form.setData('kode_jabatan', code)} error={form.errors.kode_jabatan} className="md:col-span-2" />
                    <SearchableCombobox id="unit_kerja" label="Unit kerja" value={form.data.unit_kerja} options={units} optionValue={(unit) => unit.kode_kantor_detail} optionLabel={(unit) => unit.nama_kantor || unit.kode_kantor_detail} optionDescription={(unit) => unit.nama_kantor ? unit.kode_kantor_detail : ''} placeholder="Cari unit kerja" onChange={(code) => form.setData('unit_kerja', code)} error={form.errors.unit_kerja} />
                    <div><label htmlFor="no_sk" className="mb-2 block text-xs font-bold uppercase tracking-wide text-slate-600">Nomor SK</label><input id="no_sk" value={form.data.no_sk} onChange={(event) => form.setData('no_sk', event.target.value)} maxLength="64" required className="h-11 w-full rounded-xl border border-slate-200 px-3 text-sm outline-none focus:border-blue-600 focus:ring-4 focus:ring-blue-600/10" />{form.errors.no_sk && <p className="mt-1 text-xs text-red-600">{form.errors.no_sk}</p>}</div>
                    <div><label htmlFor="tgl_sk" className="mb-2 block text-xs font-bold uppercase tracking-wide text-slate-600">Tanggal SK</label><input id="tgl_sk" type="date" value={form.data.tgl_sk} onChange={(event) => form.setData('tgl_sk', event.target.value)} required className="h-11 w-full rounded-xl border border-slate-200 px-3 text-sm outline-none focus:border-blue-600 focus:ring-4 focus:ring-blue-600/10" />{form.errors.tgl_sk && <p className="mt-1 text-xs text-red-600">{form.errors.tgl_sk}</p>}</div>
                    <div><label htmlFor={isEdit ? 'tmt_jabatan' : 'tmt_mulai'} className="mb-2 block text-xs font-bold uppercase tracking-wide text-slate-600">TMT mulai</label><input id={isEdit ? 'tmt_jabatan' : 'tmt_mulai'} type="date" value={form.data[isEdit ? 'tmt_jabatan' : 'tmt_mulai']} onChange={(event) => form.setData(isEdit ? 'tmt_jabatan' : 'tmt_mulai', event.target.value)} required className="h-11 w-full rounded-xl border border-slate-200 px-3 text-sm outline-none focus:border-blue-600 focus:ring-4 focus:ring-blue-600/10" />{form.errors[isEdit ? 'tmt_jabatan' : 'tmt_mulai'] && <p className="mt-1 text-xs text-red-600">{form.errors[isEdit ? 'tmt_jabatan' : 'tmt_mulai']}</p>}</div>
                    <div className="flex flex-wrap justify-end gap-2 md:col-span-2"><Link href="/jabatan" className="inline-flex min-h-11 items-center rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-600">Batal</Link><button type="submit" disabled={form.processing} className="min-h-11 rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-60">{form.processing ? 'Menyimpan...' : isEdit ? 'Simpan koreksi' : 'Simpan jabatan'}</button></div>
                    {Object.keys(form.errors).length > 0 && <p className="text-sm text-red-600 md:col-span-2" role="alert">Periksa kembali data yang ditandai sebelum menyimpan.</p>}
                </form>
            </section>}
        </LegacyPageShell>
    );
}
