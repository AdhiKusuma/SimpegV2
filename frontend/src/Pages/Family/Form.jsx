import { Head, Link, useForm } from '@inertiajs/react';
import LegacyPageShell from '../../Components/LegacyPageShell';
import { EmployeeCombobox } from '../../Components/SearchableCombobox';
import SelectCombobox from '../../Components/SelectCombobox';

const defaults = {
    id_peg: '',
    nik: '',
    nama: '',
    tmp_lhr: '',
    tgl_lhr: '',
    pendidikan: '',
    pekerjaan: '',
    status_hub: '',
    anak_ke: '',
    bpjs_anak: '',
    hp: '',
    bpjs_pasangan: '',
};

const statuses = {
    children: ['Anak Kandung', 'Anak Tiri', 'Anak Angkat'],
    parents: ['Ayah Kandung', 'Ibu Kandung', 'Ayah Tiri', 'Ibu Tiri', 'Mertua L', 'Mertua P', 'Wali'],
    spouses: ['Suami', 'Istri'],
};

export default function FamilyForm({ type, label, mode, record, employees, employeeId, canRequest }) {
    const initial = { ...defaults, ...(record || {}), id_peg: record?.id_peg || employeeId || '' };
    ['nik', 'bpjs_anak', 'hp', 'bpjs_pasangan', 'pekerjaan'].forEach((field) => {
        if (initial[field] === '-') initial[field] = '';
    });
    const form = useForm(initial);
    const isEdit = mode === 'edit';
    const isParentCreate = type === 'parents' && !isEdit;
    const recordId = record?.id_anak || record?.id_ortu || record?.id_si;

    function submit(event) {
        event.preventDefault();
        const url = isEdit ? `/keluarga/${type}/${recordId}` : `/keluarga/${type}`;
        isEdit ? form.put(url) : form.post(url);
    }

    const field = (name, labelText, options = {}) => {
        const numericOnly = ['nik', 'bpjs_anak', 'hp', 'bpjs_pasangan'].includes(name);
        const occupation = name === 'pekerjaan';

        return <div><label htmlFor={name} className="mb-2 block text-sm font-semibold text-slate-800">{labelText}{options.required && <span className="text-red-600"> *</span>}</label><input id={name} name={name} type={options.type || 'text'} inputMode={numericOnly ? 'numeric' : undefined} pattern={numericOnly ? '[0-9]*' : occupation ? '.*[A-Za-z].*' : undefined} title={numericOnly ? 'Gunakan angka saja.' : occupation ? 'Pekerjaan harus memuat huruf.' : undefined} value={form.data[name] || ''} onChange={(event) => form.setData(name, numericOnly ? event.target.value.replace(/[^0-9]/g, '') : event.target.value)} required={options.required} maxLength={options.maxLength} className="h-11 w-full rounded-xl border border-slate-200 px-3 text-sm outline-none focus:border-blue-600 focus:ring-4 focus:ring-blue-600/10" />{form.errors[name] && <p className="mt-1 text-xs text-red-600" role="alert">{form.errors[name]}</p>}</div>;
    };

    return (
        <LegacyPageShell title={`${isEdit ? 'Edit' : 'Tambah'} ${label}`}>
            <Head title={`${isEdit ? 'Edit' : 'Tambah'} ${label}`} />
            <div className="mx-auto max-w-4xl">
                <div className="mb-6"><Link href={`/keluarga/${type}${form.data.id_peg ? `?uid=${encodeURIComponent(form.data.id_peg)}` : ''}`} className="text-sm font-bold text-blue-600 hover:text-blue-700">← Kembali ke {label}</Link><p className="mt-4 mb-2 text-sm font-semibold uppercase tracking-[0.18em] text-blue-600">Referensi Keluarga</p><h1 className="text-3xl font-extrabold tracking-tight text-slate-900">{isEdit ? 'Edit' : 'Tambah'} {label}</h1></div>
                <form onSubmit={submit} className="simpeg-form-card">
                    {canRequest && <div className="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-900" role="status">Perubahan akan diajukan untuk persetujuan kepala cabang dan belum langsung mengubah data aktif.</div>}
                    <div className="grid gap-5 sm:grid-cols-2">
                        <div className="sm:col-span-2"><EmployeeCombobox employees={employees} value={form.data.id_peg} onChange={(value) => form.setData('id_peg', value)} disabled={isEdit && type !== 'spouses'} error={form.errors.id_peg} /></div>
                        {!isParentCreate && field('nik', 'NIK', { maxLength: 16 })}
                        {field('nama', type === 'children' ? 'Nama Anak' : type === 'parents' ? 'Nama Orang Tua' : 'Nama Pasangan', { required: true, maxLength: isParentCreate ? 64 : undefined })}
                        {!isParentCreate && field('tmp_lhr', 'Tempat Lahir', { required: type === 'spouses', maxLength: 64 })}
                        {!isParentCreate && field('tgl_lhr', 'Tanggal Lahir', { type: 'date', required: type === 'spouses' })}
                        {!isParentCreate && field('pendidikan', 'Pendidikan', { maxLength: 26 })}
                        {!isParentCreate && field('pekerjaan', 'Pekerjaan', { maxLength: 64 })}
                        <SelectCombobox id="status_hub" label="Status Hubungan" value={form.data.status_hub} onChange={(value) => form.setData('status_hub', value)} options={statuses[type]} emptyLabel="Pilih status" required={type === 'spouses' || isParentCreate} error={form.errors.status_hub} />
                        {type === 'children' && field('anak_ke', 'Anak ke-', { type: 'number', maxLength: 3 })}
                        {type === 'children' && field('bpjs_anak', 'No. BPJS Anak', { maxLength: 32 })}
                        {type === 'spouses' && field('hp', 'No. HP', { maxLength: 13 })}
                        {type === 'spouses' && field('bpjs_pasangan', 'No. BPJS Pasangan', { maxLength: 20 })}
                    </div>
                    <div className="mt-8 flex justify-end gap-3"><Link href={`/keluarga/${type}${form.data.id_peg ? `?uid=${encodeURIComponent(form.data.id_peg)}` : ''}`} className="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-600">Batal</Link><button type="submit" disabled={form.processing} className="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-60">{form.processing ? (canRequest ? 'Mengirim...' : 'Menyimpan...') : canRequest ? 'Ajukan persetujuan' : isEdit ? 'Update Data' : 'Simpan Data'}</button></div>
                </form>
            </div>
        </LegacyPageShell>
    );
}
