import { Head, Link, router } from '@inertiajs/react';
import LegacyPageShell from '../../Components/LegacyPageShell';
import MobileFilterGroup from '../../Components/MobileFilterGroup';
import SearchableCombobox from '../../Components/SearchableCombobox';

export default function Nominative({ items, offices, positions, statuses, filters }) {
    function submit(event) {
        event.preventDefault();
        const form = new FormData(event.currentTarget);
        router.get('/laporan/nominatif', Object.fromEntries(form.entries()), { preserveState: true, replace: true });
    }

    const exportQuery = new URLSearchParams(Object.entries(filters).filter(([, value]) => value));
    const statusOptions = [
        { value: '', label: 'Semua status' },
        ...statuses.map((status) => ({ value: status, label: status })),
    ];
    const activeFilterCount = [filters.kantor, filters.jabatan, filters.status_kepeg].filter(Boolean).length;

    return (
        <LegacyPageShell title="Laporan Nominatif Pegawai">
            <div className="simpeg-report-page">
            <Head title="Laporan Nominatif Pegawai" />
            <div className="mb-6">
                <p className="mb-2 text-sm font-semibold uppercase tracking-[0.18em] text-blue-600">Laporan Kepegawaian</p>
                <h1 className="text-3xl font-extrabold tracking-tight text-slate-900">Laporan nominatif pegawai</h1>
                <p className="mt-2 text-slate-500">Daftar pegawai aktif berdasarkan kantor, jabatan, status, dan pencarian.</p>
            </div>

            <section className="simpeg-card mb-6 rounded-2xl border p-4 shadow-sm sm:p-5" aria-label="Filter laporan nominatif">
                <form onSubmit={submit} className="simpeg-filter-toolbar simpeg-filter-toolbar--mobile-panel simpeg-nominative-filter grid min-w-0 grid-cols-[minmax(0,1fr)_auto] gap-4 sm:grid-cols-2 xl:grid-cols-[minmax(0,1.5fr)_repeat(3,minmax(0,1fr))_auto_auto_auto] xl:items-end">
                    <div className="simpeg-nominative-filter__search simpeg-mobile-filter-search min-w-0">
                        <label htmlFor="nominative-search" className="mb-2 block text-xs font-bold uppercase tracking-wide text-slate-500">Cari</label>
                        <input id="nominative-search" name="search" defaultValue={filters.search} placeholder="Cari nama atau ID pegawai" className="simpeg-input h-12 w-full rounded-xl border px-4 text-sm outline-none focus:ring-4 focus:ring-[var(--simpeg-focus-ring)]" />
                    </div>
                    <MobileFilterGroup id="nominative-mobile-filters" activeCount={activeFilterCount} toggleClassName="simpeg-mobile-filter-toggle--after-search">
                        <SearchableCombobox id="nominative-office" name="kantor" label="Kantor" value={filters.kantor} options={offices} optionValue={(office) => office.kode} optionLabel={(office) => office.nama} placeholder="Cari kantor" emptyMessage="Kantor tidak ditemukan." disabled={offices.length === 1} className="min-w-0" />
                        <SearchableCombobox id="nominative-position" name="jabatan" label="Jabatan" value={filters.jabatan} options={positions} placeholder="Cari jabatan" emptyMessage="Jabatan tidak ditemukan." className="min-w-0" />
                        <SearchableCombobox id="nominative-status" name="status_kepeg" label="Status" value={filters.status_kepeg} options={statusOptions} placeholder="Semua status" emptyMessage="Status tidak ditemukan." searchable={false} className="min-w-0" />
                        <button type="submit" className="simpeg-primary simpeg-mobile-filter-apply">Terapkan filter</button>
                    </MobileFilterGroup>
                    <div className="simpeg-nominative-filter__actions simpeg-mobile-filter-actions--after-search grid grid-cols-1 gap-3 sm:col-span-2 sm:grid-cols-2 xl:contents">
                        <button type="submit" className="simpeg-primary simpeg-desktop-filter-apply inline-flex h-12 w-full items-center justify-center rounded-xl px-5 text-sm font-bold transition focus:outline-none focus:ring-4 focus:ring-[var(--simpeg-focus-ring)]">Terapkan</button>
                        <a href={`/laporan/nominatif/export?${exportQuery.toString()}`} className="inline-flex h-12 w-full items-center justify-center whitespace-nowrap rounded-xl bg-emerald-600 px-4 text-sm font-bold text-white transition hover:bg-emerald-700 focus:outline-none focus:ring-4 focus:ring-emerald-600/20">Export XLSX</a>
                        <a href={`/laporan/nominatif/export?type=pdf&${exportQuery.toString()}`} target="_blank" rel="noreferrer" className="inline-flex h-12 w-full items-center justify-center whitespace-nowrap rounded-xl border border-slate-300 px-4 text-sm font-bold text-slate-700 transition hover:border-blue-300 hover:text-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-600/20">Cetak / Simpan PDF</a>
                    </div>
                </form>
            </section>

            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div className="border-b border-slate-100 px-4 py-3">
                    <span className="text-sm text-slate-500">{items.total} pegawai aktif</span>
                </div>

                <div className="overflow-x-auto">
                    <table className="min-w-full text-left">
                        <thead className="border-b border-slate-100 text-xs uppercase tracking-wide text-slate-500">
                            <tr><th className="px-5 py-4">No</th><th className="px-5 py-4">Pegawai</th><th className="px-5 py-4">Jabatan</th><th className="px-5 py-4">Unit kerja</th><th className="px-5 py-4">Status</th><th className="px-5 py-4">Pendidikan</th><th className="px-5 py-4">TMT</th></tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {items.data.length === 0 ? <tr><td colSpan="7" className="px-5 py-12 text-center text-sm text-slate-500">Data nominatif tidak ditemukan.</td></tr> : items.data.map((item) => <tr key={item.id_peg} className="align-top transition hover:bg-slate-50">
                                <td className="px-5 py-4 text-sm text-slate-500">{item.no}.</td>
                                <td className="px-5 py-4"><Link href={`/pegawai/${item.id_peg}`} className="font-bold text-blue-700 hover:underline">{item.nama || '-'}</Link><span className="mt-1 block font-mono text-xs text-slate-500">{item.id_peg}</span></td>
                                <td className="px-5 py-4 text-sm text-slate-700">{item.jabatan || '-'}</td>
                                <td className="px-5 py-4 text-sm text-slate-600">{item.nama_kantor || '-'}</td>
                                <td className="px-5 py-4"><span className="inline-flex rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700">{item.status_kepeg || '-'}</span></td>
                                <td className="px-5 py-4 text-sm text-slate-600"><span className="block font-semibold">{item.jenjang || '-'}</span><span className="mt-1 block text-xs text-slate-400">{item.nama_sekolah || '-'}</span></td>
                                <td className="px-5 py-4 text-sm text-slate-600">{item.tmt_jabatan || '-'}</td>
                            </tr>)}
                        </tbody>
                    </table>
                </div>
                <div className="simpeg-report-pagination flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 p-4 text-sm text-slate-500"><span>Menampilkan {items.from || 0}–{items.to || 0} dari {items.total} data</span><div className="flex gap-2">{items.links.filter((link) => link.url).map((link) => <Link key={link.label} href={link.url} preserveState className={`rounded-lg border px-3 py-2 ${link.active ? 'border-blue-600 bg-blue-600 text-white' : 'border-slate-200 hover:border-blue-600 hover:text-blue-600'}`} dangerouslySetInnerHTML={{ __html: link.label }} />)}</div></div>
            </section>
            </div>
        </LegacyPageShell>
    );
}
