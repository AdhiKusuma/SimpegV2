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
                <p className="mb-2 text-sm font-semibold uppercase tracking-[0.18em] text-blue-600">Laporan Kepegawaian</p>
                <h1 className="text-3xl font-extrabold tracking-tight text-slate-900">Laporan keadaan pegawai</h1>
                <p className="mt-2 text-slate-500">Rekapitulasi pegawai aktif berdasarkan rentang usia, pendidikan, status, jabatan, dan hukuman disiplin.</p>
            </div>

            <section className="simpeg-condition-filter simpeg-card mb-6 rounded-2xl border p-4 shadow-sm sm:p-5">
                <form onSubmit={submit} className="simpeg-filter-toolbar simpeg-condition-filter__form simpeg-filter-toolbar--mobile-panel grid min-w-0 grid-cols-1 gap-4 sm:grid-cols-2 lg:items-end">
                    <MobileFilterGroup id="condition-mobile-filters" activeCount={activeFilterCount}>
                        <SearchableCombobox id="condition-office" name="kantor" label="Kantor cabang" value={filters.kantor} options={offices} optionValue={(office) => office.kode} optionLabel={(office) => office.nama} placeholder="Cari kantor cabang" emptyMessage="Kantor tidak ditemukan." disabled={offices.length === 1} />
                        <div className="min-w-0">
                            <label htmlFor="condition-year" className="mb-2 block text-xs font-bold uppercase tracking-wide text-slate-500">Tahun hukuman</label>
                            <input id="condition-year" name="tahun" type="number" min="2000" max="2100" defaultValue={year} className="simpeg-input h-12 w-full rounded-xl border px-4 text-sm outline-none transition focus:ring-4 focus:ring-[var(--simpeg-focus-ring)]" />
                        </div>
                        <button type="submit" className="simpeg-primary simpeg-mobile-filter-apply">Terapkan</button>
                    </MobileFilterGroup>
                    <div className="simpeg-mobile-filter-actions--top simpeg-condition-filter__actions flex gap-2">
                        <button type="submit" className="simpeg-primary simpeg-desktop-filter-apply simpeg-condition-filter__action simpeg-condition-filter__apply inline-flex h-12 items-center justify-center rounded-xl px-4 text-sm font-bold transition focus:outline-none focus:ring-4 focus:ring-[var(--simpeg-focus-ring)]">Terapkan</button>
                        <a href={`/laporan/keadaan/export?${exportQuery.toString()}`} className="simpeg-condition-filter__action simpeg-condition-filter__export inline-flex h-12 items-center justify-center whitespace-nowrap rounded-xl bg-emerald-600 px-4 text-sm font-bold text-white transition hover:bg-emerald-700 focus:outline-none focus:ring-4 focus:ring-emerald-600/20">Unduh XLSX</a>
                    </div>
                </form>
            </section>

            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div className="overflow-x-auto">
                    <table className="min-w-[900px] w-full text-left">
                        <thead className="text-xs uppercase tracking-wide text-slate-500">
                            <tr className="border-b border-slate-100"><th rowSpan="2" className="px-5 py-4 font-semibold">No</th><th rowSpan="2" className="px-5 py-4 font-semibold">Jenis laporan</th><th colSpan={ranges.length} className="px-5 py-4 text-center font-semibold">Rentang usia (tahun)</th><th rowSpan="2" className="px-5 py-4 text-center font-semibold">Total</th></tr>
                            <tr>{ranges.map((range) => <th key={range} className="px-5 py-4 text-center font-semibold">{range}</th>)}</tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {categories.map((category, categoryIndex) => <Fragment key={category.key}>
                                <tr key={`${category.key}-header`} className="bg-slate-50"><td className="px-5 py-4 text-center font-bold text-slate-500">{categoryIndex + 1}</td><td colSpan={ranges.length + 2} className="px-5 py-4 font-extrabold tracking-wide text-slate-700">{category.label}</td></tr>
                                {category.sub.length === 0 ? <tr key={`${category.key}-empty`}><td className="px-5 py-12"></td><td colSpan={ranges.length + 1} className="px-5 py-12 text-sm italic text-slate-400">Tidak ada data</td></tr> : category.sub.map((sub) => {
                                    const values = ranges.map((range) => Number(counts[range]?.[category.key]?.[sub] || 0));
                                    const total = values.reduce((sum, value) => sum + value, 0);
                                    return <tr key={`${category.key}-${sub}`} className="align-top transition hover:bg-slate-50"><td className="px-5 py-4"></td><td className="px-5 py-4 pl-8 text-sm text-slate-700">{sub}</td>{values.map((value, index) => <td key={`${sub}-${ranges[index]}`} className="px-5 py-4 text-center text-sm">{value || <span className="text-slate-300">-</span>}</td>)}<td className="px-5 py-4 text-center text-sm font-bold text-slate-700">{total || '-'}</td></tr>;
                                })}
                            </Fragment>)}
                        </tbody>
                    </table>
                </div>
            </section>
        </LegacyPageShell>
    );
}
