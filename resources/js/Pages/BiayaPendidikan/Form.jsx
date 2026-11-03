import { Head, Link, useForm } from '@inertiajs/react';
import LegacyPageShell from '../../Components/LegacyPageShell';
import SelectCombobox from '../../Components/SelectCombobox';

const today = new Date().toISOString().slice(0, 10);
const defaults = {
    kode_pengembangan: '',
    kode_pihak: '',
    pengembangan_sdm: '',
    pihak_pelaksana: '',
    waktu_pelaksanaan: '',
    jumlah_sdm: '1',
    total_biaya: '0',
    tgl_pengembangan_sdm: today,
};

function formatCurrency(value) {
    if (value === null || value === undefined || value === '') return '';
    return Number(value).toLocaleString('id-ID');
}

export default function BiayaPendidikanForm({ mode, record, categories, parties }) {
    const isEdit = mode === 'edit';
    const form = useForm({ ...defaults, ...(record || {}) });

    function submit(event) {
        event.preventDefault();
        if (isEdit) {
            form.put(`/biaya-pendidikan/${record.biaya_id}`, { preserveScroll: true });
            return;
        }

        form.post('/biaya-pendidikan', { preserveScroll: true });
    }

    const inputClass = 'h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm outline-none focus:border-blue-600 focus:bg-white focus:ring-4 focus:ring-blue-600/10';
    const labelClass = 'mb-2 block text-xs font-bold uppercase tracking-wide text-slate-600';

    return (
        <LegacyPageShell title={`${isEdit ? 'Edit' : 'Tambah'} Kegiatan Diklat`}>
            <Head title={`${isEdit ? 'Edit' : 'Tambah'} Kegiatan Diklat`} />
            <div className="mx-auto max-w-4xl">
                <div className="mb-6"><Link href="/biaya-pendidikan" className="text-sm font-bold text-blue-600 hover:text-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-600/20">Kembali ke daftar kegiatan</Link><p className="mt-4 mb-2 text-sm font-semibold uppercase tracking-[0.16em] text-blue-600">Pengembangan Pegawai</p><h1 className="text-3xl font-extrabold tracking-tight text-slate-900">{isEdit ? 'Edit' : 'Tambah'} kegiatan diklat</h1><p className="mt-2 text-slate-500">Isi kolom sesuai struktur tabel `tb_biaya_pendidikan` yang sudah digunakan SIMPEG.</p></div>

                <form onSubmit={submit} className="simpeg-form-card">
                    <div className="grid gap-5 sm:grid-cols-2">
                        <SelectCombobox id="kode_pengembangan" label="Kategori pengembangan" value={form.data.kode_pengembangan || ''} onChange={(value) => form.setData('kode_pengembangan', value)} options={categories} optionValue={(category) => category.code} optionLabel={(category) => `${category.code} - ${category.name}`} emptyLabel="Pilih kategori" required error={form.errors.kode_pengembangan} />
                        <SelectCombobox id="kode_pihak" label="Jenis pihak pelaksana" value={form.data.kode_pihak || ''} onChange={(value) => form.setData('kode_pihak', value)} options={parties} optionValue={(party) => party.code} optionLabel={(party) => `${party.code} - ${party.name}`} emptyLabel="Pilih jenis pihak" error={form.errors.kode_pihak} />
                        <div className="sm:col-span-2"><label htmlFor="pengembangan_sdm" className={labelClass}>Judul kegiatan atau pengembangan SDM <span className="text-red-600">*</span></label><input id="pengembangan_sdm" value={form.data.pengembangan_sdm || ''} onChange={(event) => form.setData('pengembangan_sdm', event.target.value)} required className={inputClass} placeholder="Nama kegiatan yang tercatat pada data sistem" />{form.errors.pengembangan_sdm && <p className="mt-1 text-xs text-red-600" role="alert">{form.errors.pengembangan_sdm}</p>}</div>
                        <div><label htmlFor="pihak_pelaksana" className={labelClass}>Nama pihak pelaksana</label><input id="pihak_pelaksana" value={form.data.pihak_pelaksana || ''} onChange={(event) => form.setData('pihak_pelaksana', event.target.value)} className={inputClass} placeholder="Instansi atau vendor" />{form.errors.pihak_pelaksana && <p className="mt-1 text-xs text-red-600" role="alert">{form.errors.pihak_pelaksana}</p>}</div>
                        <div><label htmlFor="tgl_pengembangan_sdm" className={labelClass}>Tanggal mulai <span className="text-red-600">*</span></label><input id="tgl_pengembangan_sdm" type="date" value={form.data.tgl_pengembangan_sdm || ''} onChange={(event) => form.setData('tgl_pengembangan_sdm', event.target.value)} required className={inputClass} />{form.errors.tgl_pengembangan_sdm && <p className="mt-1 text-xs text-red-600" role="alert">{form.errors.tgl_pengembangan_sdm}</p>}</div>
                        <div><label htmlFor="waktu_pelaksanaan" className={labelClass}>Durasi atau waktu</label><input id="waktu_pelaksanaan" value={form.data.waktu_pelaksanaan || ''} onChange={(event) => form.setData('waktu_pelaksanaan', event.target.value)} className={inputClass} placeholder="Contoh: 3 Hari" />{form.errors.waktu_pelaksanaan && <p className="mt-1 text-xs text-red-600" role="alert">{form.errors.waktu_pelaksanaan}</p>}</div>
                        <div><label htmlFor="jumlah_sdm" className={labelClass}>Jumlah peserta <span className="text-red-600">*</span></label><input id="jumlah_sdm" type="number" min="1" value={form.data.jumlah_sdm || ''} onChange={(event) => form.setData('jumlah_sdm', event.target.value)} required className={inputClass} />{form.errors.jumlah_sdm && <p className="mt-1 text-xs text-red-600" role="alert">{form.errors.jumlah_sdm}</p>}</div>
                        <div><label htmlFor="total_biaya" className={labelClass}>Total biaya (Rp)</label><input id="total_biaya" inputMode="numeric" value={formatCurrency(form.data.total_biaya)} onChange={(event) => form.setData('total_biaya', event.target.value.replace(/[^0-9]/g, ''))} className={`${inputClass} font-semibold`} placeholder="0" />{form.errors.total_biaya && <p className="mt-1 text-xs text-red-600" role="alert">{form.errors.total_biaya}</p>}</div>
                    </div>
                    <div className="mt-8 flex flex-wrap justify-end gap-3 border-t border-slate-100 pt-5"><Link href="/biaya-pendidikan" className="inline-flex min-h-11 items-center rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-600/20">Batal</Link><button type="submit" disabled={form.processing} className="inline-flex min-h-11 items-center rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-60 focus:outline-none focus:ring-2 focus:ring-blue-600/20">{form.processing ? 'Menyimpan...' : isEdit ? 'Update data' : 'Simpan data'}</button></div>
                </form>
            </div>
        </LegacyPageShell>
    );
}
