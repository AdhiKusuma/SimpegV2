import { Head, Link, router, usePage } from '@inertiajs/react';
import LegacyPageShell from '../../Components/LegacyPageShell';
import MobileFilterGroup from '../../Components/MobileFilterGroup';
import SearchableCombobox from '../../Components/SearchableCombobox';
import SelectCombobox from '../../Components/SelectCombobox';

export default function EmployeesIndex({ employees, offices, divisions, positions, years, filters, canManage }) {
    const { flash } = usePage().props;
    function submit(event) {
        event.preventDefault();
        const form = new FormData(event.currentTarget);
        router.get('/pegawai', Object.fromEntries(form.entries()), { preserveState: false, replace: true });
    }

    return (
        <LegacyPageShell title="Data Pegawai">
            <Head title="Data Pegawai" />
            <div className="mb-6 flex flex-wrap items-end justify-between gap-4">
                <div><p className="mb-2 text-sm font-semibold uppercase tracking-[0.18em] text-blue-600">Data Pegawai</p><h1 className="text-3xl font-extrabold tracking-tight text-slate-900">Kelola data pegawai</h1><p className="mt-2 text-slate-500">Kelola data pegawai, jabatan, status aktif, dan histori purna.</p></div>
                {canManage && <div className="flex flex-wrap gap-2"><Link href="/pegawai/create" className="inline-flex min-h-11 items-center rounded-xl bg-blue-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-600/20">Tambah pegawai</Link><Link href="/pegawai/import" className="inline-flex min-h-11 items-center rounded-xl border border-emerald-200 px-4 py-2 text-sm font-bold text-emerald-700 transition hover:bg-emerald-50 focus:outline-none focus:ring-4 focus:ring-emerald-600/20">Import XLSX</Link></div>}
            </div>
            {flash?.success && <div className="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800" role="status">{flash.success}</div>}

            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <nav className="flex flex-wrap gap-2 border-b border-slate-100 p-4" aria-label="Filter status pegawai">
                    {[['active', 'Aktif'], ['nonjob', 'Belum Ada Jabatan'], ['purna', 'Purna']].map(([value, label]) => <Link key={value} href={`/pegawai?tab=${value}`} className={`rounded-full px-4 py-2 text-sm font-semibold transition focus:outline-none focus:ring-2 focus:ring-blue-600/30 ${filters.tab === value ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'}`}>{label}</Link>)}
                </nav>
                <form onSubmit={submit} className="simpeg-filter-toolbar simpeg-filter-toolbar--mobile-panel employees-index-filter-form grid gap-4 border-b border-slate-100 p-4">
                    <div className="simpeg-mobile-filter-search"><label htmlFor="search" className="mb-2 block text-xs font-bold uppercase tracking-wide text-slate-500">Cari nama / NIK</label><input id="search" name="search" defaultValue={filters.search} placeholder="Cari nama, NIK, atau ID pegawai" className="h-11 w-full rounded-xl border border-slate-200 px-4 text-sm outline-none focus:border-blue-600 focus:ring-4 focus:ring-blue-600/10" /></div>
                    <MobileFilterGroup id="employees-mobile-filters" activeCount={Number(Boolean(filters.kantor)) + Number(Boolean(filters.divisi)) + Number(Boolean(filters.jabatan)) + Number(Boolean(filters.year))} toggleClassName="simpeg-mobile-filter-toggle--after-search">
                        <div className="employees-index-filter-grid">
                            <SearchableCombobox id="kantor" name="kantor" label="Kantor / Area" value={filters.kantor} options={offices} optionValue={(office) => office.kode_kantor_detail} optionLabel={(office) => office.nama_kantor || office.kode_kantor_detail} placeholder="Cari kantor atau area" />
                            <SearchableCombobox id="employee-division" name="divisi" label="Unit kerja / Divisi" value={filters.divisi} options={divisions} placeholder="Cari unit kerja atau divisi" />
                            <SearchableCombobox id="employee-position" name="jabatan" label="Jabatan" value={filters.jabatan} options={positions} placeholder="Cari jabatan" />
                            <SelectCombobox id="employee-start-year" name="year" label="Tahun mulai kerja" value={filters.year} options={years} emptyLabel="Semua tahun" />
                            <button type="submit" className="simpeg-primary simpeg-mobile-filter-apply">Terapkan</button>
                            <button type="submit" className="simpeg-primary employees-index-desktop-filter-apply">Terapkan</button>
                        </div>
                    </MobileFilterGroup>
                    <input type="hidden" name="tab" value={filters.tab} />
                </form>

                <div className="overflow-x-auto"><table className="min-w-full text-left"><thead className="border-b border-slate-100 text-xs uppercase tracking-wide text-slate-500"><tr><th className="px-5 py-4">Pegawai</th><th className="px-5 py-4">TTL</th><th className="px-5 py-4">Jabatan & Unit</th><th className="px-5 py-4">Mulai</th><th className="px-5 py-4">Kontak</th><th className="px-5 py-4 text-right">Aksi</th></tr></thead><tbody className="divide-y divide-slate-100">{employees.data.length === 0 ? <tr><td colSpan="6" className="px-5 py-12 text-center text-sm text-slate-500">Data tidak ditemukan.</td></tr> : employees.data.map((employee) => <tr key={JSON.stringify([employee.id_peg, employee.jabatan, employee.kantor, employee.divisi])} className="align-top transition hover:bg-slate-50"><td className="px-5 py-4"><span className="block font-bold text-slate-800">{employee.nama || '-'}</span><span className="mt-1 inline-block rounded bg-slate-100 px-2 py-1 font-mono text-xs text-slate-500">{employee.id_peg}</span></td><td className="px-5 py-4 text-sm text-slate-600">{employee.ttl || '-'}</td><td className="px-5 py-4"><span className="block text-sm font-semibold text-slate-800">{employee.jabatan || 'Belum ada jabatan'}</span><span className="mt-1 block text-xs font-semibold text-blue-600">{employee.kantor || '-'}</span>{employee.divisi && <span className="mt-1 block text-xs text-slate-400">{employee.divisi}</span>}</td><td className="whitespace-nowrap px-5 py-4 text-sm text-slate-600">{employee.tmt_kerja || '-'}</td><td className="px-5 py-4 text-sm text-slate-600">{employee.telp || '-'}</td><td className="px-5 py-4 text-right"><Link href={`/pegawai/${employee.id_peg}`} className="rounded-lg border border-blue-200 px-3 py-2 text-sm font-bold text-blue-600 hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-600/20">Detail</Link></td></tr>)}</tbody></table></div>
                <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 p-4 text-sm text-slate-500"><span>Menampilkan {employees.from || 0}–{employees.to || 0} dari {employees.total} data</span><div className="flex gap-2">{employees.links.filter((link) => link.url).map((link) => <Link key={link.label} href={link.url} preserveState className={`rounded-lg border px-3 py-2 ${link.active ? 'border-blue-600 bg-blue-600 text-white' : 'border-slate-200 hover:border-blue-600 hover:text-blue-600'}`} dangerouslySetInnerHTML={{ __html: link.label }} />)}</div></div>
            </section>
        </LegacyPageShell>
    );
}
