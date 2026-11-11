import { Head, Link, useForm } from '@inertiajs/react';
import LegacyPageShell from '../../Components/LegacyPageShell';
import SearchableCombobox, { EmployeeCombobox } from '../../Components/SearchableCombobox';

const today = new Date().toISOString().slice(0, 10);
const defaults = {
    id_peg: '',
    diklat_id: '',
    diklat: '',
    penyelenggara: '',
    tempat: '',
    biaya: '',
    biaya_per_peserta: '',
    angkatan: '',
    tahun: today.slice(0, 4),
    date_reg: today,
};

function formatCurrency(value) {
    if (value === null || value === undefined || value === '') return '';
    return Number(value).toLocaleString('id-ID');
}

function readOnlyValue(value) {
    return value || '-';
}

export default function DiklatForm({ mode, record, employees, trainingMasters = [] }) {
    const isEdit = mode === 'edit';
    const form = useForm({ ...defaults, ...(record || {}), diklat_id: record?.diklat_id ? String(record.diklat_id) : '' });
    const linkedMode = !isEdit || Boolean(form.data.diklat_id);
    const selectedMaster = trainingMasters.find((master) => String(master.value) === String(form.data.diklat_id));

    function applyMaster(value) {
        const master = trainingMasters.find((item) => String(item.value) === String(value));
        form.setData('diklat_id', value);
        form.setData('diklat', master?.label || '');
        form.setData('penyelenggara', master?.penyelenggara || '');
        form.setData('tahun', master?.tahun || '');
        form.setData('date_reg', master?.date_reg || '');
        form.setData('biaya_per_peserta', master ? String(master.batas_per_peserta) : '');
        form.setData('biaya', master ? String(master.batas_per_peserta) : '');
    }

    function submit(event) {
        event.preventDefault();
        if (isEdit) {
            form.transform((data) => ({ ...data, _method: 'put' })).post(`/diklat/${record.id_diklat}`, { forceFormData: true });
            return;
        }
        form.post('/diklat');
    }

    const inputClass = 'h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm outline-none focus:border-blue-600 focus:bg-white focus:ring-4 focus:ring-blue-600/10';
    const readOnlyClass = `${inputClass} cursor-not-allowed bg-slate-100 text-slate-500`;
    const labelClass = 'mb-2 block text-xs font-bold uppercase tracking-wide text-slate-600';

    const input = (name, label, type = 'text', required = false) => (
        <div>
            <label htmlFor={name} className={labelClass}>{label}{required && <span className="text-red-600"> *</span>}</label>
            <input
                id={name}
                name={name}
                type={type}
                value={name === 'biaya' ? formatCurrency(form.data[name]) : form.data[name] || ''}
                onChange={(event) => form.setData(name, name === 'biaya' ? event.target.value.replace(/[^0-9]/g, '') : event.target.value)}
                required={required}
                className={inputClass}
            />
            {form.errors[name] && <p className="mt-1 text-xs text-red-600" role="alert">{form.errors[name]}</p>}
        </div>
    );

    return (
        <LegacyPageShell title={`${isEdit ? 'Edit' : 'Tambah'} Data Diklat`}>
            <Head title={`${isEdit ? 'Edit' : 'Tambah'} Data Diklat`} />
            <div className="mx-auto max-w-4xl">
                <div className="mb-6">
                    <Link href="/diklat" className="text-sm font-bold text-blue-600 hover:text-blue-700">← Kembali ke Diklat</Link>
                    <p className="mt-4 mb-2 text-sm font-semibold uppercase tracking-[0.18em] text-blue-600">Pengembangan Pegawai</p>
                    <h1 className="text-3xl font-extrabold tracking-tight text-slate-900">{isEdit ? 'Edit' : 'Tambah'} data diklat</h1>
                    <p className="mt-2 text-slate-500">Pilih master kegiatan agar penyelenggara, tanggal, kuota, dan biaya tetap konsisten.</p>
                </div>

                <form onSubmit={submit} className="simpeg-form-card">
                    <div className="grid gap-5 sm:grid-cols-2">
                        <div className="sm:col-span-2">
                            <EmployeeCombobox employees={employees} value={form.data.id_peg} onChange={(value) => form.setData('id_peg', value)} disabled={isEdit} error={form.errors.id_peg} />
                            {isEdit && <p className="mt-2 text-xs text-slate-500">Pegawai tidak dapat diubah saat edit, sesuai alur sistem.</p>}
                        </div>

                        {linkedMode ? (
                            <>
                                <div className="sm:col-span-2">
                                    <SearchableCombobox
                                        id="diklat_id"
                                        name="diklat_id"
                                        label="Master diklat / pelatihan"
                                        value={form.data.diklat_id}
                                        options={trainingMasters}
                                        optionValue={(master) => master.value}
                                        optionLabel={(master) => master.label}
                                        optionDescription={(master) => master.description}
                                        placeholder="Cari nama kegiatan diklat"
                                        emptyMessage="Master kegiatan belum tersedia. Tambahkan dari menu Daftar Kegiatan Diklat."
                                        required
                                        onChange={applyMaster}
                                        error={form.errors.diklat_id}
                                    />
                                </div>
                                <div>
                                    <label htmlFor="penyelenggara" className={labelClass}>Penyelenggara</label>
                                    <input id="penyelenggara" value={readOnlyValue(selectedMaster?.penyelenggara || form.data.penyelenggara)} readOnly className={readOnlyClass} />
                                </div>
                                <div>
                                    <label htmlFor="tahun" className={labelClass}>Tahun</label>
                                    <input id="tahun" value={readOnlyValue(selectedMaster?.tahun || form.data.tahun)} readOnly className={readOnlyClass} />
                                </div>
                                <div>
                                    <label htmlFor="date_reg" className={labelClass}>Tanggal pelatihan</label>
                                    <input id="date_reg" type="date" value={selectedMaster?.date_reg || form.data.date_reg || ''} readOnly className={readOnlyClass} />
                                </div>
                                <div>
                                    <label htmlFor="tempat" className={labelClass}>Lokasi / tempat</label>
                                    <input id="tempat" value={form.data.tempat || ''} onChange={(event) => form.setData('tempat', event.target.value)} className={inputClass} placeholder="Opsional" />
                                    {form.errors.tempat && <p className="mt-1 text-xs text-red-600" role="alert">{form.errors.tempat}</p>}
                                </div>
                                <div>
                                    <label htmlFor="angkatan" className={labelClass}>Angkatan</label>
                                    <input id="angkatan" value={form.data.angkatan || ''} onChange={(event) => form.setData('angkatan', event.target.value)} className={inputClass} placeholder="Opsional" />
                                    {form.errors.angkatan && <p className="mt-1 text-xs text-red-600" role="alert">{form.errors.angkatan}</p>}
                                </div>
                                <div>
                                    <label htmlFor="biaya_per_peserta" className={labelClass}>Biaya per peserta (Rp) <span className="text-red-600">*</span></label>
                                    <input id="biaya_per_peserta" name="biaya_per_peserta" inputMode="numeric" value={formatCurrency(form.data.biaya_per_peserta)} onChange={(event) => { const value = event.target.value.replace(/[^0-9]/g, ''); form.setData('biaya_per_peserta', value); form.setData('biaya', value); }} required className={inputClass} />
                                    {form.errors.biaya_per_peserta && <p className="mt-1 text-xs text-red-600" role="alert">{form.errors.biaya_per_peserta}</p>}
                                </div>
                                {selectedMaster && <div className="sm:col-span-2 grid gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm sm:grid-cols-3">
                                    <div><span className="block text-xs font-bold uppercase tracking-wide text-slate-500">Sisa kuota</span><span className="mt-1 block font-bold text-slate-800">{selectedMaster.sisa_kuota} dari {selectedMaster.target_peserta} peserta</span></div>
                                    <div><span className="block text-xs font-bold uppercase tracking-wide text-slate-500">Sisa anggaran</span><span className="mt-1 block font-bold text-slate-800">Rp {Number(selectedMaster.sisa_anggaran).toLocaleString('id-ID')}</span></div>
                                    <div><span className="block text-xs font-bold uppercase tracking-wide text-slate-500">Batas per peserta</span><span className="mt-1 block font-bold text-slate-800">Rp {Number(selectedMaster.batas_per_peserta).toLocaleString('id-ID')}</span></div>
                                </div>}
                            </>
                        ) : (
                            <>
                                {input('diklat', 'Nama diklat / pelatihan', 'text', true)}
                                {input('penyelenggara', 'Penyelenggara')}
                                {input('tempat', 'Lokasi / tempat')}
                                {input('tahun', 'Tahun', 'number', true)}
                                {input('angkatan', 'Angkatan')}
                                {input('biaya', 'Biaya (Rp)')}
                                {input('date_reg', 'Tanggal pelatihan', 'date', true)}
                            </>
                        )}
                    </div>
                    <div className="mt-8 flex justify-end gap-3">
                        <Link href="/diklat" className="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-600">Batal</Link>
                        <button type="submit" disabled={form.processing || (linkedMode && trainingMasters.length === 0)} className="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-60">{form.processing ? 'Menyimpan...' : isEdit ? 'Update Data' : 'Simpan Data'}</button>
                    </div>
                </form>
            </div>
        </LegacyPageShell>
    );
}
