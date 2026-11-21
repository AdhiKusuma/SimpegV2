import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import IconAction from '../../Components/IconAction';
import PageJumpControls from '../../Components/PageJumpControls';
import { useState } from 'react';
import LegacyPageShell from '../../Components/LegacyPageShell';
import MobileFilterGroup from '../../Components/MobileFilterGroup';
import SearchableCombobox from '../../Components/SearchableCombobox';
import SelectCombobox from '../../Components/SelectCombobox';

export default function PenghargaanIndex({ items, years, offices, filters, canManage }) {
    const { flash } = usePage().props;
    const [deleteTarget, setDeleteTarget] = useState(null);
    const deleteForm = useForm({ reason: '' });

    function submit(event) {
        event.preventDefault();
        router.get('/penghargaan', Object.fromEntries(new FormData(event.currentTarget).entries()), { preserveState: true, replace: true });
    }

    function submitDelete(event) {
        event.preventDefault();
        deleteForm.delete(`/penghargaan/${deleteTarget.id_penghargaan}`, { preserveScroll: true, onSuccess: () => { setDeleteTarget(null); deleteForm.reset(); } });
    }

    return (
        <LegacyPageShell title="Penghargaan Pegawai">
            <Head title="Penghargaan Pegawai" />
            <div className="mb-6 flex flex-wrap items-end justify-between gap-4"><div><p className="mb-2 text-sm font-semibold uppercase tracking-[0.18em] text-amber-600">Riwayat Kepegawaian</p><h1 className="text-3xl font-extrabold tracking-tight text-slate-900">Penghargaan pegawai</h1><p className="mt-2 max-w-2xl text-slate-500">Daftar penghargaan pegawai yang tercatat.</p></div>{canManage && <Link href="/penghargaan/create" className="simpeg-action-primary inline-flex min-h-11 items-center rounded-xl px-4 py-2.5 text-sm font-bold shadow-sm transition focus:outline-none focus:ring-4 focus:ring-blue-600/20">+ Tambah data</Link>}</div>
            {flash?.success && <div className="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800" role="status">{flash.success}</div>}
            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><form onSubmit={submit} className="simpeg-filter-toolbar simpeg-filter-toolbar--mobile-panel grid gap-4 border-b border-slate-100 p-4 lg:grid-cols-[minmax(0,1.35fr)_180px_minmax(200px,1fr)_auto] lg:items-end">
                    <MobileFilterGroup id="award-mobile-filters" activeCount={Number(Boolean(filters.search)) + Number(Boolean(filters.tahun && filters.tahun !== 'Semua')) + Number(Boolean(filters.kantor))}>
                        <div className="simpeg-mobile-filter-search"><label htmlFor="award-search" className="mb-2 block text-xs font-bold uppercase tracking-wide text-slate-500">Pencarian</label><input id="award-search" name="search" defaultValue={filters.search} placeholder="Nama pegawai, penghargaan, atau pemberi" className="h-11 w-full rounded-xl border border-slate-200 px-4 text-sm outline-none focus:border-amber-600 focus:ring-4 focus:ring-amber-600/10" /></div>
                        <SelectCombobox id="award-year" name="tahun" label="Tahun" value={filters.tahun} options={years} emptyLabel="Semua" />
                        <SearchableCombobox id="award-office" name="kantor" label="Kantor / area" value={filters.kantor} options={offices} optionValue={(office) => office.kode} optionLabel={(office) => office.nama} placeholder="Semua kantor" emptyMessage="Kantor tidak ditemukan." />
                        <button type="submit" className="simpeg-primary simpeg-mobile-filter-apply">Terapkan</button>
                    </MobileFilterGroup>
                    <div className="simpeg-mobile-filter-actions--after-search flex gap-2"><button type="submit" className="simpeg-desktop-filter-apply h-11 rounded-xl bg-amber-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-amber-700 focus:outline-none focus:ring-4 focus:ring-amber-600/20">Terapkan</button></div>
                </form><div className="overflow-x-auto"><table className="min-w-[760px] text-left"><thead className="border-b border-slate-100 text-xs uppercase tracking-wide text-slate-500"><tr><th className="px-5 py-4">Pegawai</th><th className="px-5 py-4">Penghargaan</th><th className="px-5 py-4">Tahun</th><th className="px-5 py-4">Pemberi</th>{canManage && <th className="px-5 py-4 text-right">Aksi</th>}</tr></thead><tbody className="divide-y divide-slate-100">{items.data.length === 0 ? <tr><td colSpan={canManage ? 5 : 4} className="px-5 py-12 text-center text-sm text-slate-500">Data penghargaan tidak ditemukan.</td></tr> : items.data.map((item) => <tr key={item.id_penghargaan} className="align-top transition hover:bg-slate-50"><td className="px-5 py-4"><span className="block font-bold text-slate-800">{item.nama_pegawai}</span><span className="mt-1 block font-mono text-xs text-slate-500">{item.id_peg || '-'}</span></td><td className="px-5 py-4 font-semibold text-slate-800">{item.penghargaan || '-'}</td><td className="px-5 py-4"><span className="inline-flex rounded-full bg-amber-50 px-3 py-1 text-xs font-bold text-amber-700">{item.tahun || '-'}</span></td><td className="px-5 py-4 text-sm text-slate-600">{item.pemberi || '-'}</td>{canManage && <td className="whitespace-nowrap px-5 py-4 text-right"><IconAction href={`/penghargaan/${item.id_penghargaan}/edit`} label="Edit" className="mr-2" /><IconAction variant="delete" onClick={() => { setDeleteTarget(item); deleteForm.setData('reason', ''); }} label="Hapus" /></td>}</tr>)}</tbody></table></div><div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 p-4 text-sm text-slate-500"><span>Menampilkan {items.from || 0}–{items.to || 0} dari {items.total} data</span><div className="flex gap-2"><PageJumpControls paginator={items} /></div></div></section>
            {deleteTarget && <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" role="presentation"><div className="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="delete-award-title"><h2 id="delete-award-title" className="text-lg font-extrabold text-slate-900">Hapus data penghargaan?</h2><p className="mt-2 text-sm text-slate-600">Data <strong>{deleteTarget.penghargaan}</strong> akan disimpan ke Recycle Bin sebelum dihapus.</p><form onSubmit={submitDelete} className="mt-5"><label htmlFor="delete-award-reason" className="mb-2 block text-sm font-semibold text-slate-800">Alasan penghapusan</label><textarea id="delete-award-reason" value={deleteForm.data.reason} onChange={(event) => deleteForm.setData('reason', event.target.value)} rows="3" required className="w-full rounded-xl border border-slate-200 p-3 text-sm outline-none focus:border-red-500 focus:ring-4 focus:ring-red-500/10" placeholder="Contoh: data duplikat" />{deleteForm.errors.reason && <p className="mt-2 text-sm text-red-600" role="alert">{deleteForm.errors.reason}</p>}<div className="mt-5 flex justify-end gap-3"><button type="button" onClick={() => setDeleteTarget(null)} className="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-600">Batal</button><button type="submit" disabled={deleteForm.processing} className="rounded-lg bg-red-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-red-700 disabled:opacity-60">{deleteForm.processing ? 'Menghapus...' : 'Ya, hapus'}</button></div></form></div></div>}
        </LegacyPageShell>
    );
}
