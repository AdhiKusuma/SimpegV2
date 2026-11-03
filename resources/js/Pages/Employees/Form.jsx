import { Head, Link, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import LegacyPageShell from '../../Components/LegacyPageShell';
import SelectCombobox from '../../Components/SelectCombobox';

const religions = ['Islam', 'Protestan', 'Katolik', 'Hindu', 'Budha', 'KongHuCu'];
const genders = ['Laki-laki', 'Perempuan'];
const bloodTypes = ['-', 'A', 'B', 'AB', 'O'];
const maritalStatuses = ['Menikah', 'Belum Menikah', 'Janda', 'Duda'];
const employmentStatuses = ['Tetap', 'Kontrak', 'Outsource'];

export default function EmployeeForm({ mode, record }) {
    const isEdit = mode === 'edit';
    const [preview, setPreview] = useState(record.foto_url);
    const form = useForm({
        id_peg: record.id_peg || '',
        nik: record.nik === '-' ? '' : record.nik || '',
        nama: record.nama || '',
        tempat_lhr: record.tempat_lhr || '',
        tgl_lhr: record.tgl_lhr || '',
        agama: record.agama || '',
        jk: record.jk || '',
        gol_darah: record.gol_darah || '-',
        status_nikah: record.status_nikah || '',
        status_kepeg: record.status_kepeg || '',
        alamat: record.alamat || '',
        telp: record.telp === '-' ? '' : record.telp || '',
        email: record.email || '',
        bpjstk: record.bpjstk === '-' ? '' : record.bpjstk || '',
        bpjskes: record.bpjskes === '-' ? '' : record.bpjskes || '',
        foto: null,
    });

    useEffect(() => () => {
        if (preview?.startsWith('blob:')) {
            URL.revokeObjectURL(preview);
        }
    }, [preview]);

    function choosePhoto(event) {
        const file = event.target.files?.[0];
        if (!file) {
            return;
        }

        form.setData('foto', file);
        setPreview(URL.createObjectURL(file));
    }

    function submit(event) {
        event.preventDefault();
        const url = isEdit ? `/pegawai/${encodeURIComponent(record.id_peg)}` : '/pegawai';
        form.transform((data) => isEdit ? { ...data, _method: 'put' } : data);
        form.post(url, { forceFormData: true, preserveScroll: true });
    }

    function input(name, label, type = 'text', required = false, className = '') {
        const numericOnly = ['nik', 'telp', 'bpjstk', 'bpjskes'].includes(name);
        return <div className={className}>
            <label htmlFor={`employee-${name}`} className="mb-2 block text-sm font-semibold text-slate-800">{label}{required && <span className="text-red-600"> *</span>}</label>
            <input id={`employee-${name}`} name={name} type={numericOnly && name === 'telp' ? 'tel' : type} inputMode={numericOnly ? 'numeric' : undefined} pattern={numericOnly ? '[0-9]*' : undefined} maxLength={name === 'nik' ? 16 : name === 'telp' ? 13 : numericOnly ? 12 : undefined} title={numericOnly ? 'Gunakan angka saja.' : undefined} value={form.data[name] || ''} onChange={(event) => form.setData(name, numericOnly ? event.target.value.replace(/[^0-9]/g, '') : event.target.value)} required={required} readOnly={name === 'id_peg' && isEdit} className="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm outline-none read-only:bg-slate-100 focus:border-blue-600 focus:ring-4 focus:ring-blue-600/10" />
            {form.errors[name] && <p className="mt-1 text-xs font-semibold text-red-600" role="alert">{form.errors[name]}</p>}
        </div>;
    }

    function select(name, label, options, required = false, className = '') {
        return <div className={className}>
            <SelectCombobox id={`employee-${name}`} name={name} label={label} value={form.data[name] || ''} onChange={(value) => form.setData(name, value)} options={options} emptyLabel={`Pilih ${label.toLowerCase()}`} required={required} error={form.errors[name]} />
        </div>;
    }

    return (
        <LegacyPageShell title={`${isEdit ? 'Edit' : 'Tambah'} Data Pegawai`}>
            <Head title={`${isEdit ? 'Edit' : 'Tambah'} Data Pegawai`} />
            <div className="mx-auto max-w-5xl">
                <div className="mb-6"><Link href={isEdit ? `/pegawai/${encodeURIComponent(record.id_peg)}` : '/pegawai'} className="text-sm font-bold text-blue-600 hover:text-blue-700">Kembali ke data pegawai</Link><p className="mb-2 mt-5 text-sm font-semibold uppercase tracking-[0.18em] text-blue-600">Data Pegawai</p><h1 className="text-3xl font-extrabold tracking-tight text-slate-900">{isEdit ? 'Edit' : 'Tambah'} biodata pegawai</h1><p className="mt-2 text-slate-500">Lengkapi identitas, data pribadi, kontak, dan foto pegawai dalam satu template yang konsisten.</p></div>

                <form onSubmit={submit} encType="multipart/form-data" className="simpeg-form-card simpeg-employee-form-card">
                    <section aria-labelledby="employee-identity" className="border-b border-slate-100 pb-8"><h2 id="employee-identity" className="mb-5 text-sm font-bold uppercase tracking-wide text-slate-500">Identitas utama</h2><div className="grid gap-5 sm:grid-cols-2">{input('id_peg', 'ID Pegawai', 'text', !isEdit)}{input('nama', 'Nama lengkap', 'text', true)}{input('nik', 'NIK', 'text', true)}</div></section>
                    <section aria-labelledby="employee-personal" className="border-b border-slate-100 py-8"><h2 id="employee-personal" className="mb-5 text-sm font-bold uppercase tracking-wide text-slate-500">Data pribadi</h2><div className="grid gap-5 sm:grid-cols-2">{input('tempat_lhr', 'Tempat lahir', 'text', true)}{input('tgl_lhr', 'Tanggal lahir', 'date', true)}{select('agama', 'Agama', religions, true)}{select('jk', 'Jenis kelamin', genders, true)}{select('gol_darah', 'Golongan darah', bloodTypes)}{select('status_nikah', 'Status pernikahan', maritalStatuses)}{select('status_kepeg', 'Status kepegawaian', employmentStatuses, true)}</div></section>
                    <section aria-labelledby="employee-contact" className="border-b border-slate-100 py-8"><h2 id="employee-contact" className="mb-5 text-sm font-bold uppercase tracking-wide text-slate-500">Kontak dan administrasi</h2><div className="grid gap-5 sm:grid-cols-2"><div className="sm:col-span-2"><label htmlFor="employee-alamat" className="mb-2 block text-sm font-semibold text-slate-800">Alamat <span className="text-red-600">*</span></label><textarea id="employee-alamat" name="alamat" value={form.data.alamat || ''} onChange={(event) => form.setData('alamat', event.target.value)} required rows="3" className="w-full rounded-xl border border-slate-200 px-3 py-3 text-sm outline-none focus:border-blue-600 focus:ring-4 focus:ring-blue-600/10" />{form.errors.alamat && <p className="mt-1 text-xs font-semibold text-red-600" role="alert">{form.errors.alamat}</p>}</div>{input('telp', 'No. telepon / WA', 'text', true)}{input('email', 'Email', 'email', true)}{input('bpjstk', 'No. BPJS Ketenagakerjaan')}{input('bpjskes', 'No. BPJS Kesehatan')}</div></section>
                    <section aria-labelledby="employee-photo" className="py-8"><h2 id="employee-photo" className="mb-5 text-sm font-bold uppercase tracking-wide text-slate-500">Foto profil</h2><div className="flex flex-wrap items-center gap-5"><div className="flex h-24 w-24 items-center justify-center overflow-hidden rounded-full border-2 border-slate-200 bg-slate-50">{preview ? <img src={preview} alt="Pratinjau foto pegawai" className="h-full w-full object-cover" /> : <span className="px-2 text-center text-xs font-semibold text-slate-400">Belum ada foto</span>}</div><div><label htmlFor="employee-foto" className="inline-flex min-h-11 cursor-pointer items-center rounded-xl border border-blue-200 px-4 py-2 text-sm font-bold text-blue-700 transition hover:bg-blue-50 focus-within:ring-4 focus-within:ring-blue-600/20">Pilih foto<input id="employee-foto" name="foto" type="file" accept=".jpg,.jpeg,.png,.gif,image/*" onChange={choosePhoto} className="sr-only" /></label><p className="mt-2 text-xs text-slate-500">JPG, PNG, atau GIF. Maksimal 2 MB. Kosongkan jika tidak ingin mengubah foto.</p>{form.errors.foto && <p className="mt-1 text-xs font-semibold text-red-600" role="alert">{form.errors.foto}</p>}</div></div></section>
                    <div className="flex flex-wrap justify-end gap-3 border-t border-slate-100 pt-6"><Link href={isEdit ? `/pegawai/${encodeURIComponent(record.id_peg)}` : '/pegawai'} className="inline-flex min-h-11 items-center rounded-xl border border-slate-300 px-4 py-2 text-sm font-bold text-slate-600 transition hover:border-slate-500 focus:outline-none focus:ring-4 focus:ring-blue-600/20">Batal</Link><button type="submit" disabled={form.processing} className="inline-flex min-h-11 items-center rounded-xl bg-blue-600 px-5 py-2 text-sm font-bold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60 focus:outline-none focus:ring-4 focus:ring-blue-600/20">{form.processing ? 'Menyimpan...' : isEdit ? 'Simpan perubahan' : 'Simpan pegawai'}</button></div>
                </form>
            </div>
        </LegacyPageShell>
    );
}
