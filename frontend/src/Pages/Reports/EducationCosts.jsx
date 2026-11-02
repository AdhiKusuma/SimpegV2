import { Head, router } from '@inertiajs/react';
import LegacyPageShell from '../../Components/LegacyPageShell';
import MobileFilterGroup from '../../Components/MobileFilterGroup';
import SelectCombobox from '../../Components/SelectCombobox';

const currency = new Intl.NumberFormat('id-ID');

const quarterOptions = [
    ['1', 'Januari - Maret'],
    ['2', 'April - Juni'],
    ['3', 'Juli - September'],
    ['4', 'Oktober - Desember'],
];

export default function EducationCosts({ rows, years, filters, totals }) {
    function submit(event) {
        event.preventDefault();
        router.get('/laporan/rekap-biaya-pendidikan', Object.fromEntries(new FormData(event.currentTarget).entries()), { preserveState: true, replace: true });
    }

    const exportQuery = new URLSearchParams(Object.entries(filters).filter(([, value]) => value));

    return (
        <LegacyPageShell title="Rekapitulasi Biaya Pendidikan">
            <Head title="Rekapitulasi Biaya Pendidikan" />
            <div className="mb-6">
                <p className="mb-2 text-sm font-semibold uppercase tracking-[0.18em] text-blue-600">Laporan Pengembangan Pegawai</p>
                <h1 className="text-3xl font-extrabold tracking-tight text-slate-900">Rekapitulasi biaya pendidikan</h1>
                <p className="mt-2 text-slate-500">Daftar kegiatan pengembangan sumber daya manusia dan realisasi biayanya.</p>
            </div>

            <section className="mb-6 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <form onSubmit={submit} className="simpeg-filter-toolbar simpeg-filter-toolbar--mobile-panel grid gap-4 lg:grid-cols-[1fr_1fr_auto] lg:items-end">
                    <MobileFilterGroup id="education-cost-mobile-filters" activeCount={Number(Boolean(filters.tahun && filters.tahun !== 'Semua')) + Number(Boolean(filters.kuartal && filters.kuartal !== 'Semua'))}>
                        <SelectCombobox id="education-cost-year" name="tahun" label="Tahun" value={filters.tahun} options={years} emptyLabel="Semua tahun" emptyValue="Semua" />
                        <SelectCombobox id="education-cost-quarter" name="kuartal" label="Kuartal" value={filters.kuartal} options={quarterOptions.map(([value, label]) => ({ value, label }))} emptyLabel="Semua kuartal" emptyValue="Semua" />
                        <button type="submit" className="simpeg-primary simpeg-mobile-filter-apply">Terapkan</button>
                    </MobileFilterGroup>
                    <div className="simpeg-mobile-filter-actions simpeg-mobile-filter-actions--full flex flex-wrap gap-2">
                        <button type="submit" className="simpeg-desktop-filter-apply h-11 rounded-xl bg-blue-600 px-5 text-sm font-bold text-white transition hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-600/20">Terapkan</button>
                        <a href={`/laporan/rekap-biaya-pendidikan/export?type=excel&${exportQuery.toString()}`} className="inline-flex min-h-11 items-center rounded-xl bg-emerald-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-emerald-700 focus:outline-none focus:ring-4 focus:ring-emerald-600/20">Unduh XLSX</a>
                        <a href={`/laporan/rekap-biaya-pendidikan/export?type=pdf&${exportQuery.toString()}`} target="_blank" rel="noreferrer" className="inline-flex min-h-11 items-center rounded-xl border border-slate-300 px-4 py-2 text-sm font-bold text-slate-700 transition hover:border-blue-300 hover:text-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-600/20">Cetak / Simpan PDF</a>
                    </div>
                </form>
            </section>

            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div className="overflow-x-auto">
                    <table className="min-w-[980px] w-full text-left">
                        <thead className="border-b border-slate-100 text-xs uppercase tracking-wide text-slate-500">
                            <tr><th className="px-5 py-4 text-center font-semibold">No</th><th className="px-5 py-4 font-semibold">Kategori pengembangan</th><th className="px-5 py-4 font-semibold">Detail kegiatan</th><th className="px-5 py-4 font-semibold">Penyelenggara</th><th className="px-5 py-4 text-center font-semibold">Tanggal</th><th className="px-5 py-4 text-center font-semibold">Peserta</th><th className="px-5 py-4 text-right font-semibold">Total biaya</th></tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {rows.length === 0 ? <tr><td colSpan="7" className="px-5 py-12 text-center text-sm text-slate-500">Data rekap tidak ditemukan.</td></tr> : rows.map((row, index) => <tr key={`${row.tanggal_export}-${row.pengembangan_sdm}-${index}`} className="align-top transition hover:bg-slate-50">
                                <td className="px-5 py-4 text-center text-sm text-slate-500">{index + 1}.</td>
                                <td className="px-5 py-4 font-semibold text-slate-800">{row.kategori}</td>
                                <td className="max-w-xs px-5 py-4 text-sm text-slate-700">{row.pengembangan_sdm}</td>
                                <td className="px-5 py-4 text-sm font-semibold text-slate-600">{row.pihak_pelaksana}</td>
                                <td className="px-5 py-4 text-center"><span className="whitespace-nowrap rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700">{row.tanggal}</span></td>
                                <td className="px-5 py-4 text-center text-sm font-bold text-slate-700">{row.jumlah_sdm}</td>
                                <td className="px-5 py-4 text-right font-mono text-sm font-bold text-slate-800">Rp {currency.format(row.total_biaya)}</td>
                            </tr>)}
                        </tbody>
                        <tfoot className="border-t border-slate-100 bg-slate-50 font-bold text-slate-700">
                            <tr><td colSpan="5" className="px-5 py-4 text-right uppercase">Grand total</td><td className="px-5 py-4 text-center">{currency.format(totals.peserta)}</td><td className="px-5 py-4 text-right font-mono">Rp {currency.format(totals.biaya)}</td></tr>
                        </tfoot>
                    </table>
                </div>
            </section>
        </LegacyPageShell>
    );
}
