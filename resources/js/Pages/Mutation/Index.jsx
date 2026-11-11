import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { FileSpreadsheet } from 'lucide-react';
import { useState } from 'react';
import LegacyPageShell from '../../Components/LegacyPageShell';
import MobileFilterGroup from '../../Components/MobileFilterGroup';
import SelectCombobox from '../../Components/SelectCombobox';

export default function MutationIndex({ items, filters, years, canManage }) {
    const { flash } = usePage().props;
    const [deleteTarget, setDeleteTarget] = useState(null);
    const deleteForm = useForm({ reason: '' });
    const exportParams = new URLSearchParams();
    if (filters.search) exportParams.set('search', filters.search);
    if (filters.year) exportParams.set('year', filters.year);
    const exportQuery = exportParams.toString();
    const exportHref = `/mutasi/export${exportQuery ? `?${exportQuery}` : ''}`;

    function submitDelete(event) {
        event.preventDefault();
        deleteForm.delete(`/mutasi/${deleteTarget.id_mutasi}`, {
            preserveScroll: true,
            onSuccess: () => {
                setDeleteTarget(null);
                deleteForm.reset();
            },
        });
    }

    return (
        <LegacyPageShell title="Penonaktifan Pegawai">
            <Head title="Penonaktifan Pegawai" />
            <div className="mb-6 flex flex-wrap items-end justify-between gap-4">
                <div><p className="mb-2 text-sm font-semibold uppercase tracking-[0.18em] text-red-600">Mutasi Kepegawaian</p><h1 className="text-3xl font-extrabold tracking-tight text-slate-900">Penonaktifan pegawai</h1><p className="mt-2 text-slate-500">Data pensiun, meninggal dunia, pengunduran diri, PTDH, dan pemberhentian lainnya.</p></div>
                <div className="flex flex-wrap items-center gap-3">
                    <a href={exportHref} className="simpeg-utility inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border px-4 text-sm font-bold"><FileSpreadsheet aria-hidden="true" className="h-4 w-4" />Export XLSX</a>
                    {canManage && <Link href="/mutasi/create" className="rounded-xl bg-red-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-red-700 focus:outline-none focus:ring-4 focus:ring-red-600/20">+ Tambah Data</Link>}
                </div>
            </div>

            {flash?.success && <div className="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800" role="status">{flash.success}</div>}
            {flash?.error && <div className="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800" role="alert">{flash.error}</div>}

            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <form method="get" action="/mutasi" className="simpeg-filter-toolbar simpeg-filter-toolbar--mobile-panel grid gap-4 border-b border-slate-100 p-4 lg:grid-cols-[minmax(0,1fr)_220px_auto] lg:items-end">
                    <div className="simpeg-mobile-filter-search"><label htmlFor="mutation-search" className="mb-2 block text-xs font-bold uppercase tracking-wide text-slate-500">Cari pegawai</label><input id="mutation-search" name="search" defaultValue={filters.search} placeholder="Cari nama atau ID pegawai" className="h-11 min-w-0 w-full rounded-xl border border-slate-200 px-4 text-sm outline-none focus:border-red-600 focus:ring-4 focus:ring-red-600/10" /></div>
                    <MobileFilterGroup id="mutation-mobile-filters" activeCount={Number(Boolean(filters.year))} toggleClassName="simpeg-mobile-filter-toggle--after-search">
                        <SelectCombobox id="mutation-year" name="year" label="Tahun penonaktifan (TMT)" value={filters.year} options={years} emptyLabel="Semua tahun" />
                        <button type="submit" className="simpeg-primary simpeg-mobile-filter-apply">Terapkan</button>
                    </MobileFilterGroup>
                    <button type="submit" className="simpeg-desktop-filter-apply h-11 rounded-xl border border-slate-300 px-5 text-sm font-bold text-slate-700 transition hover:border-red-600 hover:text-red-600 focus:outline-none focus:ring-4 focus:ring-red-600/20">Terapkan</button>
                </form>
                <div className="overflow-x-auto"><table className="min-w-full text-left"><thead className="border-b border-slate-100 text-xs uppercase tracking-wide text-slate-500"><tr><th className="px-5 py-4">Pegawai</th><th className="px-5 py-4">Jabatan terakhir</th><th className="px-5 py-4">Jenis</th><th className="px-5 py-4">Tanggal SK</th><th className="px-5 py-4">No. SK</th><th className="px-5 py-4">TMT</th><th className="px-5 py-4">Dokumen</th>{canManage && <th className="px-5 py-4 text-right">Aksi</th>}</tr></thead><tbody className="divide-y divide-slate-100">{items.data.length === 0 ? <tr><td colSpan={canManage ? 8 : 7} className="px-5 py-12 text-center text-sm text-slate-500">Data mutasi tidak ditemukan.</td></tr> : items.data.map((item) => <tr key={item.id_mutasi} className="align-top transition hover:bg-slate-50"><td className="px-5 py-4"><span className="block font-bold text-slate-800">{item.nama_pegawai}</span><span className="mt-1 font-mono text-xs text-slate-500">{item.id_peg || '-'}</span></td><td className="px-5 py-4 text-sm text-slate-600">{item.jabatan || '-'}</td><td className="px-5 py-4"><span className="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700">{item.jns_mutasi}</span></td><td className="whitespace-nowrap px-5 py-4 text-sm text-slate-600">{item.tgl_mutasi || '-'}</td><td className="px-5 py-4 text-sm font-semibold text-slate-700">{item.no_mutasi}</td><td className="whitespace-nowrap px-5 py-4 text-sm font-bold text-red-600">{item.tmt || '-'}</td><td className="px-5 py-4 text-sm">{item.document_url ? <a href={item.document_url} target="_blank" rel="noreferrer" className="font-bold text-blue-600 hover:text-blue-700">Lihat SK</a> : <span className="text-slate-400">-</span>}</td>{canManage && <td className="whitespace-nowrap px-5 py-4 text-right"><Link href={`/mutasi/${item.id_mutasi}/edit`} className="mr-2 rounded-lg border border-blue-200 px-3 py-2 text-sm font-bold text-blue-600 hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-600/20">Edit</Link><button type="button" onClick={() => { setDeleteTarget(item); deleteForm.setData('reason', ''); }} className="rounded-lg border border-red-200 px-3 py-2 text-sm font-bold text-red-600 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600/20">Hapus</button></td>}</tr>)}</tbody></table></div>
                <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 p-4 text-sm text-slate-500"><span>Menampilkan {items.from || 0}–{items.to || 0} dari {items.total} data</span><div className="flex gap-2">{items.links.filter((link) => link.url).map((link) => <Link key={link.label} href={link.url} preserveState className={`rounded-lg border px-3 py-2 ${link.active ? 'border-red-600 bg-red-600 text-white' : 'border-slate-200 hover:border-red-600 hover:text-red-600'}`} dangerouslySetInnerHTML={{ __html: link.label }} />)}</div></div>
            </section>

            {deleteTarget && <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" role="presentation"><div className="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="delete-mutation-title"><h2 id="delete-mutation-title" className="text-lg font-extrabold text-slate-900">Hapus data mutasi?</h2><p className="mt-2 text-sm text-slate-600">Data <strong>{deleteTarget.no_mutasi}</strong> akan disimpan ke Recycle Bin. Status pegawai dan jabatan terakhir akan dipulihkan sesuai alur sistem.</p><form onSubmit={submitDelete} className="mt-5"><label htmlFor="delete-mutation-reason" className="mb-2 block text-sm font-semibold text-slate-800">Alasan penghapusan</label><textarea id="delete-mutation-reason" value={deleteForm.data.reason} onChange={(event) => deleteForm.setData('reason', event.target.value)} rows="3" required className="w-full rounded-xl border border-slate-200 p-3 text-sm outline-none focus:border-red-500 focus:ring-4 focus:ring-red-500/10" placeholder="Contoh: salah input nomor SK" />{deleteForm.errors.reason && <p className="mt-2 text-sm text-red-600" role="alert">{deleteForm.errors.reason}</p>}<div className="mt-5 flex justify-end gap-3"><button type="button" onClick={() => setDeleteTarget(null)} className="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-600">Batal</button><button type="submit" disabled={deleteForm.processing} className="rounded-lg bg-red-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-red-700 disabled:opacity-60">{deleteForm.processing ? 'Menghapus...' : 'Ya, Hapus'}</button></div></form></div></div>}
        </LegacyPageShell>
    );
}
