import { Head, Link, useForm, usePage, router } from '@inertiajs/react';
import { useState } from 'react';
import LegacyPageShell from '../../Components/LegacyPageShell';
import MobileFilterGroup from '../../Components/MobileFilterGroup';
import SelectCombobox from '../../Components/SelectCombobox';
import SearchableCombobox from '../../Components/SearchableCombobox';

export default function DiklatIndex({ items, years, trainingTypes, offices, filters, canManage }) {
    const { flash } = usePage().props;
    const [deleteTarget, setDeleteTarget] = useState(null);
    const deleteForm = useForm({ reason: '' });
    const withFilters = (url) => {
        const params = new URLSearchParams();
        Object.entries(filters).forEach(([key, value]) => { if (value) params.set(key, value); });
        const query = params.toString();
        return query ? `${url}?${query}` : url;
    };

    function submit(event) {
        event.preventDefault();
        router.get('/diklat', Object.fromEntries(new FormData(event.currentTarget).entries()), { preserveState: true, replace: true });
    }

    function submitDelete(event) {
        event.preventDefault();
        deleteForm.delete(`/diklat/${deleteTarget.id_diklat}`, { preserveScroll: true, onSuccess: () => { setDeleteTarget(null); deleteForm.reset(); } });
    }

    return (
        <LegacyPageShell title="Daftar Diklat Pegawai">
            <Head title="Daftar Diklat Pegawai" />
            <div className="mb-6 flex flex-wrap items-end justify-between gap-4"><div><p className="mb-2 text-sm font-semibold uppercase tracking-[0.18em] text-blue-600">Pengembangan Pegawai</p><h1 className="text-3xl font-extrabold tracking-tight text-slate-900">Daftar pelatihan & diklat</h1><p className="mt-2 text-slate-500">Menampilkan riwayat pelatihan dan diklat pegawai.</p></div><div className="flex flex-wrap gap-2"><a href={withFilters('/diklat/export')} className="rounded-xl border border-emerald-200 px-4 py-2.5 text-sm font-bold text-emerald-700 transition hover:bg-emerald-50">Export XLSX</a>{canManage && <Link href="/diklat/import" className="rounded-xl border border-blue-200 px-4 py-2.5 text-sm font-bold text-blue-700 hover:bg-blue-50 focus:outline-none focus:ring-4 focus:ring-blue-600/20">Import XLSX</Link>}{canManage && <Link href="/diklat/create" className="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-600/20">+ Tambah Data</Link>}</div></div>
            {flash?.success && <div className="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800" role="status">{flash.success}</div>}
            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><form onSubmit={submit} className="simpeg-filter-toolbar simpeg-filter-toolbar--mobile-panel grid gap-4 border-b border-slate-100 p-4 lg:grid-cols-[160px_1fr_220px_1fr_auto] lg:items-end">
                <MobileFilterGroup id="training-mobile-filters" activeCount={[filters.tahun, filters.diklat, filters.kantor].filter((value) => value && value !== 'Semua').length} toggleClassName="simpeg-mobile-filter-toggle--after-search">
                    <SelectCombobox id="diklat-year" name="tahun" label="Tahun" value={filters.tahun} options={years} emptyLabel="Semua" />
                    <SearchableCombobox id="diklat-type" name="diklat" label="Jenis diklat" value={filters.diklat} options={trainingTypes} placeholder="Cari jenis diklat" emptyMessage="Jenis diklat tidak ditemukan." />
                    <SearchableCombobox id="diklat-office" name="kantor" label="Unit kerja" value={filters.kantor} options={offices} optionValue={(office) => office.kode} optionLabel={(office) => office.nama} placeholder="Cari unit kerja" emptyMessage="Unit kerja tidak ditemukan." />
                    <button type="submit" className="simpeg-primary simpeg-mobile-filter-apply">Terapkan</button>
                </MobileFilterGroup>
                <div className="simpeg-mobile-filter-search"><label htmlFor="diklat-search" className="mb-2 block text-xs font-bold uppercase tracking-wide text-slate-500">Pencarian</label><input id="diklat-search" name="search" defaultValue={filters.search} placeholder="Nama pegawai atau penyelenggara" className="h-11 w-full rounded-xl border border-slate-200 px-4 text-sm outline-none focus:border-blue-600 focus:ring-4 focus:ring-blue-600/10" /></div>
                <button type="submit" className="simpeg-desktop-filter-apply h-11 rounded-xl bg-blue-600 px-5 text-sm font-bold text-white hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-600/20">Terapkan</button>
            </form>
                <div className="overflow-x-auto"><table className="min-w-full text-left"><thead className="border-b border-slate-100 text-xs uppercase tracking-wide text-slate-500"><tr><th className="px-5 py-4">Pegawai</th><th className="px-5 py-4">Diklat</th><th className="px-5 py-4">Penyelenggara / tempat</th><th className="px-5 py-4">Angkatan</th><th className="px-5 py-4">Tahun</th><th className="px-5 py-4">Biaya</th>{canManage && <th className="px-5 py-4 text-right">Aksi</th>}</tr></thead><tbody className="divide-y divide-slate-100">{items.data.length === 0 ? <tr><td colSpan={canManage ? 7 : 6} className="px-5 py-12 text-center text-sm text-slate-500">Data diklat tidak ditemukan.</td></tr> : items.data.map((item) => <tr key={item.id_diklat} className="align-top transition hover:bg-slate-50"><td className="px-5 py-4"><span className="block font-bold text-slate-800">{item.nama_pegawai || '-'}</span><span className="mt-1 font-mono text-xs text-slate-500">{item.id_peg || '-'}</span></td><td className="px-5 py-4 text-sm font-semibold text-slate-800">{item.diklat}</td><td className="px-5 py-4 text-sm text-slate-600"><span className="block">{item.penyelenggara || '-'}</span><span className="mt-1 block text-xs text-slate-400">{item.tempat || '-'}</span></td><td className="px-5 py-4 text-sm text-slate-600">{item.angkatan || '-'}</td><td className="px-5 py-4 text-sm text-slate-600">{item.tahun || '-'}</td><td className="px-5 py-4 text-sm text-slate-600">{item.biaya === null || item.biaya === '' ? '-' : `Rp ${Number(item.biaya).toLocaleString('id-ID')}`}</td>{canManage && <td className="whitespace-nowrap px-5 py-4 text-right"><Link href={`/diklat/${item.id_diklat}/edit`} className="mr-2 rounded-lg border border-blue-200 px-3 py-2 text-sm font-bold text-blue-600 hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-600/20">Edit</Link><button type="button" onClick={() => { setDeleteTarget(item); deleteForm.setData('reason', ''); }} className="rounded-lg border border-red-200 px-3 py-2 text-sm font-bold text-red-600 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600/20">Hapus</button></td>}</tr>)}</tbody></table></div>
                <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 p-4 text-sm text-slate-500"><span>Menampilkan {items.from || 0}–{items.to || 0} dari {items.total} data</span><div className="flex gap-2">{items.links.filter((link) => link.url).map((link) => <Link key={link.label} href={link.url} preserveState className={`rounded-lg border px-3 py-2 ${link.active ? 'border-blue-600 bg-blue-600 text-white' : 'border-slate-200 hover:border-blue-600 hover:text-blue-600'}`} dangerouslySetInnerHTML={{ __html: link.label }} />)}</div></div>
            </section>
            {deleteTarget && <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" role="presentation"><div className="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="delete-diklat-title"><h2 id="delete-diklat-title" className="text-lg font-extrabold text-slate-900">Hapus data diklat?</h2><p className="mt-2 text-sm text-slate-600">Data <strong>{deleteTarget.diklat}</strong> akan disimpan ke Recycle Bin sebelum dihapus.</p><form onSubmit={submitDelete} className="mt-5"><label htmlFor="delete-diklat-reason" className="mb-2 block text-sm font-semibold text-slate-800">Alasan penghapusan</label><textarea id="delete-diklat-reason" value={deleteForm.data.reason} onChange={(event) => deleteForm.setData('reason', event.target.value)} rows="3" required className="w-full rounded-xl border border-slate-200 p-3 text-sm outline-none focus:border-red-500 focus:ring-4 focus:ring-red-500/10" placeholder="Contoh: data duplikat" />{deleteForm.errors.reason && <p className="mt-2 text-sm text-red-600" role="alert">{deleteForm.errors.reason}</p>}<div className="mt-5 flex justify-end gap-3"><button type="button" onClick={() => setDeleteTarget(null)} className="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-600">Batal</button><button type="submit" disabled={deleteForm.processing} className="rounded-lg bg-red-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-red-700 disabled:opacity-60">{deleteForm.processing ? 'Menghapus...' : 'Ya, Hapus'}</button></div></form></div></div>}
        </LegacyPageShell>
    );
}
