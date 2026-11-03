import { Head, Link, router, usePage } from '@inertiajs/react';
import LegacyPageShell from '../../Components/LegacyPageShell';
import MobileFilterGroup from '../../Components/MobileFilterGroup';
import SelectCombobox from '../../Components/SelectCombobox';

export default function PengangkatanIndex({ items, years, filters, canManage }) {
    const { flash } = usePage().props;

    function submit(event) {
        event.preventDefault();
        router.get('/pengangkatan', Object.fromEntries(new FormData(event.currentTarget).entries()), { preserveState: true, replace: true });
    }

    return (
        <LegacyPageShell title="Pengangkatan Pegawai">
            <Head title="Pengangkatan Pegawai" />

            <div className="mb-6 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p className="text-sm font-medium text-blue-600">Referensi kepegawaian</p>
                    <h1 className="mt-2 text-3xl font-medium text-slate-900">Pengangkatan pegawai</h1>
                    <p className="mt-2 max-w-2xl text-slate-500">Daftar riwayat pengangkatan pegawai dari tabel sistem.</p>
                </div>
                {canManage && <Link href="/pengangkatan/create" className="inline-flex min-h-11 items-center rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-600/20">+ Pengangkatan baru</Link>}
            </div>

            {flash?.success && <div className="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800" role="status">{flash.success}</div>}

            <section className="simpeg-card overflow-hidden rounded-2xl border shadow-sm">
                <form onSubmit={submit} className="simpeg-filter-toolbar simpeg-filter-toolbar--mobile-panel grid gap-4 border-b border-slate-100 p-4 lg:grid-cols-[minmax(0,1fr)_180px_auto] lg:items-end">
                    <div className="simpeg-mobile-filter-search">
                        <label htmlFor="appointment-search" className="mb-2 block text-sm font-medium simpeg-muted">Pencarian</label>
                        <input id="appointment-search" name="search" defaultValue={filters.search} placeholder="Nama, ID pegawai, jenis, atau nomor SK" className="simpeg-input h-11 w-full rounded-xl border px-4 text-sm outline-none focus:border-blue-600 focus:ring-4 focus:ring-blue-600/10" />
                    </div>
                    <MobileFilterGroup id="appointment-mobile-filters" activeCount={Number(Boolean(filters.tahun && filters.tahun !== 'Semua'))} toggleClassName="simpeg-mobile-filter-toggle--after-search">
                        <SelectCombobox id="appointment-year" name="tahun" label="Tahun SK" value={filters.tahun} options={years} emptyLabel="Semua tahun" />
                        <button type="submit" className="simpeg-primary simpeg-mobile-filter-apply">Terapkan</button>
                    </MobileFilterGroup>
                    <div className="simpeg-mobile-filter-actions--after-search flex gap-2">
                        <button type="submit" className="simpeg-desktop-filter-apply h-11 rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-600/20">Terapkan</button>
                        <Link href="/pengangkatan" className="inline-flex h-11 items-center rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:border-blue-600 hover:text-blue-600 focus:outline-none focus:ring-4 focus:ring-blue-600/20">Reset</Link>
                    </div>
                </form>

                <div className="overflow-x-auto">
                    <table className="min-w-[840px] text-left">
                        <thead className="border-b border-slate-100 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th className="px-5 py-4 font-semibold">Pegawai</th>
                                <th className="px-5 py-4 font-semibold">Pengangkatan</th>
                                <th className="px-5 py-4 font-semibold">Tanggal SK / TMT</th>
                                <th className="px-5 py-4 font-semibold">Nomor SK</th>
                                <th className="px-5 py-4 font-semibold">Lampiran</th>
                                {canManage && <th className="px-5 py-4 text-right font-semibold">Aksi</th>}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {items.data.length === 0 ? (
                                <tr><td colSpan={canManage ? 6 : 5} className="px-5 py-12 text-center text-sm simpeg-muted">Data pengangkatan tidak ditemukan.</td></tr>
                            ) : items.data.map((item) => (
                                <tr key={item.id_angkat} className="align-top transition hover:bg-slate-50">
                                    <td className="px-5 py-4">
                                        <span className="block font-medium text-slate-800">{item.nama_pegawai}</span>
                                        <span className="mt-1 block text-xs simpeg-muted">ID pegawai: {item.id_peg_baru || '-'}</span>
                                    </td>
                                    <td className="px-5 py-4"><span className="inline-flex rounded-full bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700">{item.jns_mutasi || '-'}</span></td>
                                    <td className="px-5 py-4 text-sm text-slate-600"><span className="block">{item.tgl_mutasi || '-'}</span><span className="mt-1 block text-xs simpeg-muted">TMT: {item.tmt || '-'}</span></td>
                                    <td className="px-5 py-4 text-sm text-slate-600">{item.no_mutasi || '-'}</td>
                                    <td className="px-5 py-4">{item.document_url ? <a href={item.document_url} target="_blank" rel="noreferrer" className="inline-flex min-h-10 items-center rounded-lg border border-blue-200 px-3 py-2 text-sm font-medium text-blue-700 hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-600/20">Lihat SK</a> : <span className="text-sm simpeg-muted">Tidak ada file</span>}</td>
                                    {canManage && <td className="whitespace-nowrap px-5 py-4 text-right"><Link href={`/pengangkatan/${item.id_angkat}/edit`} className="inline-flex min-h-10 items-center rounded-lg border border-blue-200 px-3 py-2 text-sm font-medium text-blue-700 hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-600/20">Edit TMT</Link></td>}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 p-4 text-sm simpeg-muted">
                    <span>Menampilkan {items.from || 0}–{items.to || 0} dari {items.total} data</span>
                    <div className="flex gap-2">{items.links.filter((link) => link.url).map((link) => <Link key={link.label} href={link.url} preserveState className={`rounded-lg border px-3 py-2 ${link.active ? 'border-blue-600 bg-blue-600 text-white' : 'border-slate-200 hover:border-blue-600 hover:text-blue-600'}`} dangerouslySetInnerHTML={{ __html: link.label }} />)}</div>
                </div>
            </section>
        </LegacyPageShell>
    );
}
