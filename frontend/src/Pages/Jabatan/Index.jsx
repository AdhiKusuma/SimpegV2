import { Head, Link, router } from '@inertiajs/react';
import LegacyPageShell from '../../Components/LegacyPageShell';
import MobileFilterGroup from '../../Components/MobileFilterGroup';
import SearchableCombobox from '../../Components/SearchableCombobox';

export default function JabatanIndex({ items, units, positions, filters, canManage, canImport }) {
    function submit(event) {
        event.preventDefault();
        const form = new FormData(event.currentTarget);
        router.get('/jabatan', Object.fromEntries(form.entries()), { preserveState: true, replace: true });
    }

    return (
        <LegacyPageShell title="Data Jabatan Aktif">
            <Head title="Data Jabatan Aktif" />
            <div className="mb-6 flex flex-wrap items-end justify-between gap-4">
                <div>
                <p className="mb-2 text-sm font-semibold uppercase tracking-[0.18em] text-blue-600">Referensi Kepegawaian</p>
                <h1 className="text-3xl font-extrabold tracking-tight text-slate-900">Daftar jabatan aktif</h1>
                <p className="mt-2 text-slate-500">Menampilkan jabatan aktif dari tabel sistem yang sama. Belum ada perubahan struktur database.</p>
                </div>
                {(canImport || canManage) && <div className="flex flex-wrap gap-2">{canImport && <Link href="/jabatan/import" className="simpeg-utility inline-flex min-h-11 items-center rounded-xl border px-4 py-2.5 text-sm font-bold">Import jabatan</Link>}{canManage && <Link href="/jabatan/create" className="simpeg-primary inline-flex min-h-11 items-center rounded-xl px-4 py-2.5 text-sm font-bold">Tambah jabatan</Link>}</div>}
            </div>

            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <form onSubmit={submit} className="simpeg-filter-toolbar simpeg-filter-toolbar--mobile-panel grid gap-4 border-b border-slate-100 p-4 lg:grid-cols-[1fr_220px_220px_auto] lg:items-end">
                    <div className="simpeg-mobile-filter-search">
                        <label htmlFor="jabatan-search" className="mb-2 block text-xs font-bold uppercase tracking-wide text-slate-500">Cari</label>
                        <input id="jabatan-search" name="search" defaultValue={filters.search} placeholder="Nama, ID pegawai, jabatan, atau No. SK" className="h-11 w-full rounded-xl border border-slate-200 px-4 text-sm outline-none focus:border-blue-600 focus:ring-4 focus:ring-blue-600/10" />
                    </div>
                    <MobileFilterGroup id="positions-mobile-filters" activeCount={Number(Boolean(filters.unit)) + Number(Boolean(filters.jabatan))} toggleClassName="simpeg-mobile-filter-toggle--after-search">
                        <SearchableCombobox id="jabatan-unit" name="unit" label="Unit kerja" value={filters.unit} options={units} optionValue={(unit) => unit.kode} optionLabel={(unit) => unit.nama || unit.kode} placeholder="Cari unit kerja" maxOptions={units.length} />
                        <SearchableCombobox id="jabatan-position" name="jabatan" label="Nama jabatan" value={filters.jabatan} options={positions} optionValue={(position) => position.jabatan} optionLabel={(position) => position.jabatan} optionDescription={(position) => `Kode ${position.kode_jabatan}`} placeholder="Cari jabatan" />
                        <button type="submit" className="simpeg-primary simpeg-mobile-filter-apply">Terapkan</button>
                    </MobileFilterGroup>
                    <button type="submit" className="simpeg-desktop-filter-apply h-11 rounded-xl bg-blue-600 px-5 text-sm font-bold text-white transition hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-600/20">Terapkan</button>
                </form>

                <div className="overflow-x-auto">
                    <table className="min-w-full text-left">
                        <thead className="border-b border-slate-100 text-xs uppercase tracking-wide text-slate-500">
                            <tr><th className="px-5 py-4">Pegawai</th><th className="px-5 py-4">Jabatan</th><th className="px-5 py-4">Unit kerja</th><th className="px-5 py-4">Masa jabatan</th><th className="px-5 py-4">SK</th><th className="px-5 py-4">Status</th><th className="px-5 py-4 text-right">Aksi</th></tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {items.data.length === 0 ? <tr><td colSpan="7" className="px-5 py-12 text-center text-sm text-slate-500">Data jabatan aktif tidak ditemukan.</td></tr> : items.data.map((item) => <tr key={item.id_jab} className="align-top transition hover:bg-slate-50">
                                <td className="px-5 py-4"><span className="block font-bold text-slate-800">{item.nama || '-'}</span><span className="mt-1 font-mono text-xs text-slate-500">{item.id_peg || '-'}</span></td>
                                <td className="px-5 py-4"><span className="block font-semibold text-slate-800">{item.jabatan || '-'}</span><span className="mt-1 text-xs text-slate-500">Kode: {item.kode_jabatan || '-'}</span></td>
                                <td className="px-5 py-4 text-sm text-slate-600">{item.unit_kerja || '-'}</td>
                                <td className="px-5 py-4 text-sm text-slate-600"><span className="block">Mulai: {item.tmt_jabatan || '-'}</span><span className="mt-1 block text-xs text-slate-400">Sampai: {item.sampai_tgl || '-'}</span></td>
                                <td className="px-5 py-4 text-sm text-slate-600"><span className="block">{item.no_sk || '-'}</span><span className="mt-1 block text-xs text-slate-400">{item.tgl_sk || '-'}</span></td>
                                <td className="px-5 py-4"><span className="inline-flex rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700">{item.status}</span></td>
                                <td className="whitespace-nowrap px-5 py-4 text-right"><Link href={`/pegawai/${item.id_peg}`} className="mr-2 rounded-lg border border-blue-200 px-3 py-2 text-sm font-bold text-blue-600 hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-600/20">Profil</Link>{canManage && <Link href={`/jabatan/${item.id_jab}/edit`} className="rounded-lg border border-slate-200 px-3 py-2 text-sm font-bold text-slate-600 hover:border-blue-300 hover:text-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-600/20">Koreksi</Link>}</td>
                            </tr>)}
                        </tbody>
                    </table>
                </div>
                <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 p-4 text-sm text-slate-500"><span>Menampilkan {items.from || 0}–{items.to || 0} dari {items.total} data</span><div className="flex gap-2">{items.links.filter((link) => link.url).map((link) => <Link key={link.label} href={link.url} preserveState className={`rounded-lg border px-3 py-2 ${link.active ? 'border-blue-600 bg-blue-600 text-white' : 'border-slate-200 hover:border-blue-600 hover:text-blue-600'}`} dangerouslySetInnerHTML={{ __html: link.label }} />)}</div></div>
            </section>
        </LegacyPageShell>
    );
}
