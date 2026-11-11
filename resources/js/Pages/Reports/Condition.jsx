import { Fragment } from 'react';
import { Head, router } from '@inertiajs/react';
import LegacyPageShell from '../../Components/LegacyPageShell';
import MobileFilterGroup from '../../Components/MobileFilterGroup';
import SearchableCombobox from '../../Components/SearchableCombobox';

export default function Condition({ ranges, counts, categories, offices, filters, year }) {
    function submit(event) {
        event.preventDefault();
        const form = new FormData(event.currentTarget);
        router.get('/laporan/keadaan', Object.fromEntries(form.entries()), { preserveState: true, replace: true });
    }

    const exportQuery = new URLSearchParams(Object.entries(filters).filter(([, value]) => value));
    const activeFilterCount = Number(Boolean(filters.kantor)) + Number(String(year) !== String(new Date().getFullYear()));

    return (
        <LegacyPageShell title="Laporan Keadaan Pegawai">
            <Head title="Laporan Keadaan Pegawai" />
            <div className="mb-6">
                <p className="simpeg-muted mb-2 text-sm font-semibold uppercase tracking-[0.18em]">Laporan Kepegawaian</p>
                <h1 className="simpeg-text text-3xl font-extrabold tracking-tight">Laporan keadaan pegawai</h1>
                <p className="simpeg-muted mt-2">Rekapitulasi pegawai aktif berdasarkan rentang usia, pendidikan, status, jabatan, dan hukuman disiplin.</p>
            </div>

            <section className="simpeg-condition-filter simpeg-card mb-6 rounded-2xl border p-4 shadow-sm sm:p-5">
                <form onSubmit={submit} className="simpeg-filter-toolbar simpeg-condition-filter__form simpeg-filter-toolbar--mobile-panel grid min-w-0 grid-cols-1 gap-4 lg:items-end">
                    <MobileFilterGroup id="condition-mobile-filters" activeCount={activeFilterCount}>
                        <SearchableCombobox id="condition-office" name="kantor" label="Kantor cabang" value={filters.kantor} options={offices} optionValue={(office) => office.kode} optionLabel={(office) => office.nama} placeholder="Cari kantor cabang" emptyMessage="Kantor tidak ditemukan." disabled={offices.length === 1} />
                        <div className="min-w-0">
                            <label htmlFor="condition-year" className="simpeg-muted mb-2 block text-xs font-bold uppercase tracking-wide">Tahun hukuman</label>
                            <input id="condition-year" name="tahun" type="number" min="2000" max="2100" defaultValue={year} className="simpeg-input h-12 w-full rounded-xl border px-4 text-sm outline-none transition focus:ring-4 focus:ring-[var(--simpeg-focus-ring)]" />
                        </div>
                        <button type="submit" className="simpeg-primary simpeg-mobile-filter-apply">Terapkan</button>
                    </MobileFilterGroup>
                    <div className="simpeg-mobile-filter-actions--top simpeg-condition-filter__actions grid min-w-0 gap-2 sm:grid-cols-3">
                        <button type="submit" className="simpeg-primary simpeg-desktop-filter-apply simpeg-condition-filter__action simpeg-condition-filter__apply inline-flex h-12 items-center justify-center rounded-xl px-4 text-sm font-bold transition focus:outline-none focus:ring-4 focus:ring-[var(--simpeg-focus-ring)]">Terapkan</button>
                        <a href={`/laporan/keadaan/export?${exportQuery.toString()}`} className="simpeg-condition-filter__action simpeg-condition-filter__export inline-flex h-12 items-center justify-center whitespace-nowrap rounded-xl bg-emerald-600 px-4 text-sm font-bold text-white transition hover:bg-emerald-700 focus:outline-none focus:ring-4 focus:ring-emerald-600/20">Export XLSX</a>
                        <a href={`/laporan/keadaan/export?type=pdf&${exportQuery.toString()}`} target="_blank" rel="noreferrer" className="simpeg-utility simpeg-condition-filter__action inline-flex h-12 items-center justify-center whitespace-nowrap rounded-xl border px-4 text-sm font-bold transition focus:outline-none focus:ring-4 focus:ring-[var(--simpeg-focus-ring)]">Cetak / Simpan PDF</a>
                    </div>
                </form>
            </section>

            <section className="simpeg-card overflow-hidden rounded-2xl border shadow-sm">
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[900px] text-left">
                        <caption className="sr-only">Laporan keadaan pegawai berdasarkan rentang usia</caption>
                        <thead className="simpeg-card-soft simpeg-muted text-xs uppercase tracking-wide">
                            <tr className="border-b border-[var(--simpeg-border)]"><th rowSpan="2" className="px-5 py-4 font-semibold">No</th><th rowSpan="2" className="px-5 py-4 font-semibold">Jenis laporan</th><th colSpan={ranges.length} className="px-5 py-4 text-center font-semibold">Rentang usia (tahun)</th><th rowSpan="2" className="px-5 py-4 text-center font-semibold">Total</th></tr>
                            <tr>{ranges.map((range) => <th key={range} className="px-5 py-4 text-center font-semibold">{range}</th>)}</tr>
                        </thead>
                        <tbody className="divide-y divide-[var(--simpeg-border)]">
                            {categories.map((category, categoryIndex) => <Fragment key={category.key}>
                                <tr key={`${category.key}-header`} className="simpeg-card-soft"><td className="simpeg-muted px-5 py-4 text-center font-bold">{categoryIndex + 1}</td><td colSpan={ranges.length + 2} className="simpeg-text px-5 py-4 font-extrabold tracking-wide">{category.label}</td></tr>
                                {category.sub.length === 0 ? <tr key={`${category.key}-empty`}><td className="px-5 py-12"></td><td colSpan={ranges.length + 1} className="simpeg-muted px-5 py-12 text-sm italic">Tidak ada data</td></tr> : category.sub.map((sub) => {
                                    const values = ranges.map((range) => Number(counts[range]?.[category.key]?.[sub] || 0));
                                    const total = values.reduce((sum, value) => sum + value, 0);
                                    return <tr key={`${category.key}-${sub}`} className="simpeg-text align-top transition hover:bg-[var(--simpeg-card-soft)]"><td className="px-5 py-4"></td><td className="simpeg-muted px-5 py-4 pl-8 text-sm">{sub}</td>{values.map((value, index) => <td key={`${sub}-${ranges[index]}`} className="px-5 py-4 text-center text-sm">{value || <span className="simpeg-subtle">-</span>}</td>)}<td className="px-5 py-4 text-center text-sm font-bold">{total || '-'}</td></tr>;
                                })}
                            </Fragment>)}
                        </tbody>
                    </table>
                </div>
            </section>
        </LegacyPageShell>
    );
}
