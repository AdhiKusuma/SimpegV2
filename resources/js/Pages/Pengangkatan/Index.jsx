import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import IconAction from '../../Components/IconAction';
import PageJumpControls from '../../Components/PageJumpControls';
import { useState } from 'react';
import LegacyPageShell from '../../Components/LegacyPageShell';
import MobileFilterGroup from '../../Components/MobileFilterGroup';
import SelectCombobox from '../../Components/SelectCombobox';
import { Eye, EyeOff } from 'lucide-react';

export default function PengangkatanIndex({ items, years, filters, canManage }) {
    const { flash } = usePage().props;
    const [deleteTarget, setDeleteTarget] = useState(null);
    const deleteForm = useForm({ reason: '' });

    function submit(event) {
        event.preventDefault();
        router.get('/pengangkatan', Object.fromEntries(new FormData(event.currentTarget).entries()), { preserveState: true, replace: true });
    }

    function submitDelete(event) {
        event.preventDefault();
        deleteForm.delete('/pengangkatan/' + deleteTarget.id_angkat, {
            preserveScroll: true,
            onSuccess: () => {
                setDeleteTarget(null);
                deleteForm.reset();
            },
        });
    }

    return (
        <LegacyPageShell title="Pengangkatan Pegawai">
            <Head title="Pengangkatan Pegawai" />

            <div className="mb-8 flex flex-wrap items-end justify-between gap-5">
                <div>
                    <p className="text-xs font-bold uppercase tracking-[0.18em] text-blue-600">Referensi kepegawaian</p>
                    <h1 className="mt-2 text-3xl font-bold tracking-tight text-slate-900">Pengangkatan pegawai</h1>
                    <p className="mt-2 max-w-2xl text-sm leading-6 text-slate-500">Daftar riwayat pengangkatan pegawai.</p>
                </div>
                {canManage && <div className="flex flex-wrap items-center gap-3"><Link href="/pengangkatan/import" className="inline-flex min-h-11 items-center rounded-xl border border-blue-200 bg-white px-4 py-2.5 text-sm font-semibold text-blue-700 shadow-sm transition hover:bg-blue-50 focus:outline-none focus:ring-4 focus:ring-blue-600/20">Import XLSX</Link><Link href="/pengangkatan/create" className="simpeg-action-primary inline-flex min-h-11 items-center rounded-xl px-4 py-2.5 text-sm font-semibold shadow-sm transition focus:outline-none focus:ring-4 focus:ring-blue-600/20">+ Pengangkatan baru</Link></div>}
            </div>

            {flash?.success && <div className="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800" role="status">{flash.success}</div>}

            <section className="simpeg-filter-list-shell">
                <form onSubmit={submit} className="simpeg-filter-toolbar simpeg-filter-toolbar--mobile-panel">
                    <MobileFilterGroup id="appointment-mobile-filters" activeCount={Number(Boolean(filters.search)) + Number(Boolean(filters.tahun && filters.tahun !== 'Semua'))}>
                        <div className="simpeg-mobile-filter-search">
                            <label htmlFor="appointment-search" className="mb-2 block text-xs font-bold uppercase tracking-wide simpeg-muted">Pencarian</label>
                            <input id="appointment-search" name="search" defaultValue={filters.search} placeholder="Cari nama atau ID pegawai" className="simpeg-input h-12 w-full rounded-xl border px-4 text-sm outline-none transition focus:border-blue-600 focus:ring-4 focus:ring-blue-600/10" />
                        </div>
                        <SelectCombobox id="appointment-year" name="tahun" label="Tahun SK" value={filters.tahun} options={years} emptyLabel="Semua tahun" />
                        <button type="submit" className="simpeg-primary simpeg-mobile-filter-apply">Terapkan</button>
                    </MobileFilterGroup>
                    <div className="simpeg-mobile-filter-actions--after-search flex gap-2">
                        <button type="submit" className="simpeg-desktop-filter-apply h-12 rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-600/20">Terapkan</button>
                    </div>
                </form>

                <div className="overflow-x-auto">
                    <table className="w-full min-w-[1080px] text-left">
                        <colgroup>
                            <col className="w-[24%]" />
                            <col className="w-[16%]" />
                            <col className="w-[22%]" />
                            <col className="w-[23%]" />
                            <col className="w-[15%]" />
                        </colgroup>
                        <thead className="simpeg-card-soft border-b text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th className="whitespace-nowrap px-6 py-4 font-bold">Pegawai</th>
                                <th className="whitespace-nowrap px-6 py-4 font-bold">Pengangkatan</th>
                                <th className="whitespace-nowrap px-6 py-4 font-bold">Tanggal SK / Masa berlaku</th>
                                <th className="whitespace-nowrap px-6 py-4 font-bold">Nomor SK</th>
                                <th className="whitespace-nowrap px-6 py-4 font-bold">Aksi</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {items.data.length === 0 ? (
                                <tr><td colSpan="5" className="px-5 py-12 text-center text-sm simpeg-muted">Data pengangkatan tidak ditemukan.</td></tr>
                            ) : items.data.map((item) => (
                                <tr key={item.id_angkat} className="align-top transition hover:bg-slate-50">
                                    <td className="px-6 py-5">
                                        <span className="block font-semibold leading-5 text-slate-800">{item.nama_pegawai}</span>
                                        <span className="mt-1 block text-xs simpeg-muted">ID pegawai: {item.id_peg_baru || '-'}</span>
                                    </td>
                                    <td className="px-6 py-5"><span className="inline-flex rounded-full bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-700">{item.jns_mutasi || '-'}</span></td>
                                    <td className="px-6 py-5 text-sm text-slate-600"><span className="block whitespace-nowrap font-medium">SK: {item.tgl_mutasi || '-'}</span><span className="mt-1 block text-xs simpeg-muted">Mulai: {item.tmt || '-'}</span>{item.tmt_selesai && <span className="mt-1 block text-xs simpeg-muted">Selesai: {item.tmt_selesai}</span>}</td>
                                    <td className="px-6 py-5 text-sm text-slate-600">{item.no_mutasi || '-'}</td>
                                    <td className="whitespace-nowrap px-6 py-5"><div className="flex justify-end gap-2">{item.document_url ? <a href={item.document_url} target="_blank" rel="noreferrer" title="Lihat SK" aria-label="Lihat SK" className="simpeg-utility inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border transition hover:bg-blue-50 focus:outline-none focus:ring-4 focus:ring-blue-600/20 dark:hover:bg-blue-950/30"><Eye aria-hidden="true" className="h-4 w-4" /></a> : <span title="Dokumen SK tidak tersedia" aria-label="Dokumen SK tidak tersedia" className="simpeg-utility inline-flex h-9 w-9 shrink-0 cursor-not-allowed items-center justify-center rounded-lg border opacity-60"><EyeOff aria-hidden="true" className="h-4 w-4" /></span>}{canManage && <><IconAction href={"/pengangkatan/" + item.id_angkat + "/edit"} label="Edit masa berlaku" /><IconAction variant="delete" onClick={() => { setDeleteTarget(item); deleteForm.setData('reason', ''); }} label="Hapus" /></>}</div></td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <div className="simpeg-card-soft flex flex-wrap items-center justify-between gap-3 border-t px-6 py-4 text-sm simpeg-muted">
                    <span>Menampilkan {items.from || 0}–{items.to || 0} dari {items.total} data</span>
                    <div className="flex gap-2"><PageJumpControls paginator={items} /></div>
                </div>
            </section>

            {deleteTarget && <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" role="presentation"><div className="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="delete-appointment-title"><h2 id="delete-appointment-title" className="text-lg font-extrabold text-slate-900">Hapus data pengangkatan?</h2><p className="mt-2 text-sm text-slate-600">Data pengangkatan <strong>{deleteTarget.nama_pegawai}</strong> akan dipindahkan ke Recycle Bin sebelum dihapus dari daftar.</p><form onSubmit={submitDelete} className="mt-5"><label htmlFor="delete-appointment-reason" className="mb-2 block text-sm font-semibold text-slate-800">Alasan penghapusan</label><textarea id="delete-appointment-reason" value={deleteForm.data.reason} onChange={(event) => deleteForm.setData('reason', event.target.value)} rows="3" required className="w-full rounded-xl border border-slate-200 p-3 text-sm outline-none focus:border-red-500 focus:ring-4 focus:ring-red-500/10" placeholder="Contoh: data duplikat atau salah input" />{deleteForm.errors.reason && <p className="mt-2 text-sm text-red-600" role="alert">{deleteForm.errors.reason}</p>}<div className="mt-5 flex justify-end gap-3"><button type="button" onClick={() => setDeleteTarget(null)} className="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-600">Batal</button><button type="submit" disabled={deleteForm.processing} className="rounded-lg bg-red-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-red-700 disabled:opacity-60">{deleteForm.processing ? 'Menghapus...' : 'Ya, hapus'}</button></div></form></div></div>}
        </LegacyPageShell>
    );
}
