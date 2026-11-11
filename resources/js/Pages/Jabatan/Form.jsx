import { Head, Link, router, useForm } from '@inertiajs/react';
import { useEffect } from 'react';
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

    const fieldLabels = {
        id_peg: 'Pegawai',
        form_mode: 'Mode jabatan',
        kode_jabatan: 'Master jabatan',
        unit_kerja: 'Unit kerja',
        no_sk: 'Nomor SK',
        tgl_sk: 'Tanggal SK',
        tmt_jabatan: 'TMT mulai',
        tmt_mulai: 'TMT mulai',
    };
    const fieldIds = {
        id_peg: 'employee-select',
        kode_jabatan: 'kode_jabatan',
        unit_kerja: 'unit_kerja',
        no_sk: 'no_sk',
        tgl_sk: 'tgl_sk',
        tmt_jabatan: 'tmt_jabatan',
        tmt_mulai: 'tmt_mulai',
    };
    const errorEntries = Object.entries(form.errors);

    useEffect(() => {
        const firstField = errorEntries
            .map(([name]) => fieldIds[name])
            .find(Boolean);

        if (firstField) {
            document.getElementById(firstField)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }, [form.errors]);

    function inputClass(name) {
        return `h-11 w-full rounded-xl border px-3 text-sm outline-none transition focus:border-blue-600 focus:ring-4 focus:ring-blue-600/10 ${form.errors[name] ? 'simpeg-input-error border-red-500 focus:border-red-600 focus:ring-red-600/20' : 'border-slate-200'}`;
    }

    function fieldLabel(name, label) {
        return <label htmlFor={fieldIds[name] || name} className={`mb-2 block text-xs font-bold uppercase tracking-wide ${form.errors[name] ? 'simpeg-label-error' : 'text-slate-600'}`}>
            {label}
            {form.errors[name] && <span className="ml-2 inline-flex rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-extrabold uppercase tracking-wide text-red-700">Perlu diperiksa</span>}
        </label>;
    }

    function fieldError(name) {
        return form.errors[name] && <p id={`${fieldIds[name] || name}-error`} className="simpeg-field-error mt-1 text-xs font-semibold" role="alert">{form.errors[name]}</p>;
    }

    function selectEmployee(event) {
        const id = event.target.value;
        if (id) {
            router.get('/jabatan/create', { id_peg: id }, { preserveState: true });
        }
    }

    function submit(event) {
        event.preventDefault();
        if (isEdit) {
            form.put(`/jabatan/${record.id_jab}`);
            return;
        }

        form.post('/jabatan');
    }

    return (
        <LegacyPageShell title={isEdit ? 'Koreksi Jabatan' : 'Input Jabatan'}>
            <Head title={isEdit ? 'Koreksi Jabatan' : 'Input Jabatan'} />
            <div className="mb-6"><Link href="/jabatan" className="text-sm font-bold text-blue-600 hover:text-blue-700">← Kembali ke Data Jabatan</Link><p className="mb-2 mt-4 text-sm font-semibold uppercase tracking-[0.18em] text-blue-600">Riwayat Jabatan</p><h1 className="text-3xl font-extrabold tracking-tight text-slate-900">{isEdit ? 'Koreksi data jabatan' : 'Tetapkan jabatan pegawai'}</h1><p className="mt-2 max-w-2xl text-slate-500">Lengkapi jabatan dan unit kerja pegawai sesuai data yang berlaku.</p></div>

            {!isEdit && !employee && <section className="max-w-2xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"><h2 className="text-lg font-extrabold text-slate-900">Pilih pegawai aktif</h2><p className="mt-2 text-sm text-slate-500">Pilih pegawai untuk memuat status jabatan saat ini dan form yang sesuai.</p><SearchableCombobox id="employee-select" label="Pegawai" options={employees} optionValue={(item) => item.id_peg} optionLabel={(item) => item.nama} optionDescription={(item) => `ID: ${item.id_peg}`} placeholder="Cari nama atau ID pegawai" onChange={(id) => { if (id) { selectEmployee({ target: { value: id } }); } }} className="mt-5" /> </section>}

            {employee && <section className="max-w-4xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div className="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 pb-5"><div><p className="text-lg font-extrabold text-slate-900">{employee.nama}</p><p className="mt-1 text-sm text-slate-500">NIK: <span className="font-mono">{employee.nik || '-'}</span> · ID pegawai: <span className="font-mono">{employee.id_peg}</span></p></div><span className={`rounded-full px-3 py-1.5 text-xs font-bold ${formMode === 'history' ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700'}`}>{isEdit ? record?.status_jab : formMode === 'history' ? 'Tambah riwayat Non' : 'Jabatan Aktif'}</span></div>
                {currentJob && !isEdit && <p className="mt-5 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800">Jabatan aktif saat ini: <strong>{currentJob.jabatan || '-'}</strong>. Data baru akan disimpan sebagai riwayat <strong>Non</strong>.</p>}
                <form onSubmit={submit} className="mt-6 grid gap-5 md:grid-cols-2">
                    {errorEntries.length > 0 && <div className="simpeg-validation-summary rounded-xl border px-4 py-4 text-sm md:col-span-2" role="alert" aria-live="assertive">
                        <p className="font-extrabold">Periksa data berikut sebelum menyimpan:</p>
                        <ul className="mt-2 list-disc space-y-1 pl-5">
                            {errorEntries.map(([name, message]) => <li key={name}>
                                {fieldIds[name] ? <a href={`#${fieldIds[name]}`} className="font-bold underline underline-offset-2">{fieldLabels[name] || name}</a> : <span className="font-bold">{fieldLabels[name] || name}</span>}: {message}
                            </li>)}
                        </ul>
                    </div>}
                    <SearchableCombobox id="kode_jabatan" label="Master jabatan" value={form.data.kode_jabatan} options={positions} optionValue={(item) => item.kode_jabatan} optionLabel={(item) => item.nama_jabatan} optionDescription={(item) => `Kode ${item.kode_jabatan}`} placeholder="Cari nama atau kode jabatan" description="Pilih jabatan yang tersedia di master. Nama jabatan mengikuti data master." onChange={(code) => form.setData('kode_jabatan', code)} error={form.errors.kode_jabatan} className="md:col-span-2" />
                    <SearchableCombobox id="unit_kerja" label="Unit kerja" value={form.data.unit_kerja} options={units} optionValue={(unit) => unit.kode_kantor_detail} optionLabel={(unit) => unit.nama_kantor || unit.kode_kantor_detail} optionDescription={(unit) => unit.nama_kantor ? unit.kode_kantor_detail : ''} placeholder="Cari unit kerja" onChange={(code) => form.setData('unit_kerja', code)} error={form.errors.unit_kerja} />
                    <div>{fieldLabel('no_sk', 'Nomor SK')}<input id="no_sk" aria-invalid={Boolean(form.errors.no_sk)} aria-describedby={form.errors.no_sk ? 'no_sk-error' : undefined} value={form.data.no_sk} onChange={(event) => form.setData('no_sk', event.target.value)} maxLength="64" required className={inputClass('no_sk')} />{fieldError('no_sk')}</div>
                    <div>{fieldLabel('tgl_sk', 'Tanggal SK')}<input id="tgl_sk" type="date" aria-invalid={Boolean(form.errors.tgl_sk)} aria-describedby={form.errors.tgl_sk ? 'tgl_sk-error' : undefined} value={form.data.tgl_sk} onChange={(event) => form.setData('tgl_sk', event.target.value)} required className={inputClass('tgl_sk')} />{fieldError('tgl_sk')}</div>
                    <div>{fieldLabel(isEdit ? 'tmt_jabatan' : 'tmt_mulai', 'TMT mulai')}<input id={isEdit ? 'tmt_jabatan' : 'tmt_mulai'} type="date" aria-invalid={Boolean(form.errors[isEdit ? 'tmt_jabatan' : 'tmt_mulai'])} aria-describedby={form.errors[isEdit ? 'tmt_jabatan' : 'tmt_mulai'] ? `${isEdit ? 'tmt_jabatan' : 'tmt_mulai'}-error` : undefined} value={form.data[isEdit ? 'tmt_jabatan' : 'tmt_mulai']} onChange={(event) => form.setData(isEdit ? 'tmt_jabatan' : 'tmt_mulai', event.target.value)} required className={inputClass(isEdit ? 'tmt_jabatan' : 'tmt_mulai')} />{fieldError(isEdit ? 'tmt_jabatan' : 'tmt_mulai')}</div>
                    <div className="flex flex-wrap justify-end gap-2 md:col-span-2"><Link href="/jabatan" className="inline-flex min-h-11 items-center rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-600">Batal</Link><button type="submit" disabled={form.processing} className="min-h-11 rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-60">{form.processing ? 'Menyimpan...' : isEdit ? 'Simpan koreksi' : 'Simpan jabatan'}</button></div>
                </form>
            </section>}
        </LegacyPageShell>
    );
}
