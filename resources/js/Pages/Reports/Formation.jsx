import { Head, router } from '@inertiajs/react';
import LegacyPageShell from '../../Components/LegacyPageShell';
import SearchableCombobox from '../../Components/SearchableCombobox';

export default function Formation({ rows, totals, offices, filters }) {
    function submit(event) {
        event.preventDefault();
        const form = new FormData(event.currentTarget);
        router.get('/laporan/formasi', Object.fromEntries(form.entries()), { preserveState: true, replace: true });
    }

    const exportQuery = new URLSearchParams(Object.entries(filters).filter(([, value]) => value));
    const officeOptions = [{ kode: '', nama: 'Semua kantor / global' }, ...(offices.length > 1 ? [{ kode: 'PUSAT', nama: 'Kantor pusat dan kanwil' }] : []), ...offices];

    return (
        <LegacyPageShell title="Laporan Formasi Pegawai">
            <div className="simpeg-report-page">
            <Head title="Laporan Formasi Pegawai" />
            <div className="mb-6">
                <p className="mb-2 text-sm font-semibold uppercase tracking-[0.18em] text-blue-600">Laporan Kepegawaian</p>
                <h1 className="text-3xl font-extrabold tracking-tight text-slate-900">Laporan formasi pegawai</h1>
                <p className="mt-2 text-slate-500">Perbandingan kuota jabatan, pegawai yang sudah terisi, dan kebutuhan formasi.</p>
            </div>

            <section className="simpeg-formation-filter simpeg-card mb-6 rounded-2xl border p-4 shadow-sm sm:p-5" aria-label="Filter laporan formasi">
                <form onSubmit={submit} className="simpeg-filter-toolbar grid min-w-0 gap-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end lg:gap-5">
                    <SearchableCombobox id="formation-office" name="kode_cabang" label="Kantor cabang" value={filters.kode_cabang} options={officeOptions} optionValue={(office) => office.kode} optionLabel={(office) => office.nama} placeholder="Cari kantor cabang" emptyMessage="Kantor tidak ditemukan." disabled={offices.length === 1} className="min-w-0" />
                    <div className="simpeg-formation-filter__actions grid grid-cols-1 gap-3 sm:grid-cols-2 lg:flex lg:shrink-0">
                        <button type="submit" className="simpeg-primary simpeg-formation-filter__action inline-flex w-full items-center justify-center rounded-xl px-6 text-sm font-bold transition focus:outline-none focus:ring-4 focus:ring-[var(--simpeg-focus-ring)] lg:w-auto">Terapkan</button>
                        <a href={`/laporan/formasi/export?${exportQuery.toString()}`} className="simpeg-formation-filter__action inline-flex w-full items-center justify-center whitespace-nowrap rounded-xl bg-emerald-600 px-5 text-sm font-bold text-white transition hover:bg-emerald-700 focus:outline-none focus:ring-4 focus:ring-emerald-600/20 lg:w-auto">Export XLSX</a>
                        <a href={`/laporan/formasi/export?type=pdf&${exportQuery.toString()}`} target="_blank" rel="noreferrer" className="simpeg-formation-filter__action inline-flex w-full items-center justify-center whitespace-nowrap rounded-xl border border-slate-300 px-5 text-sm font-bold text-slate-700 transition hover:border-blue-300 hover:text-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-600/20 lg:w-auto">Cetak / Simpan PDF</a>
                    </div>
                </form>
            </section>

            <section className="simpeg-report-table overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div className="overflow-x-auto">
                    <table className="min-w-[760px] w-full text-left">
                        <thead className="border-b border-slate-100 text-xs uppercase tracking-wide text-slate-500">
                            <tr><th className="px-5 py-4 text-center font-semibold">No</th><th className="px-5 py-4 font-semibold">Jabatan</th><th className="px-5 py-4 text-center font-semibold">Kuota</th><th className="px-5 py-4 text-center font-semibold">Terisi</th><th className="px-5 py-4 text-center font-semibold">Sisa</th><th className="px-5 py-4 font-semibold">Status formasi</th></tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {rows.length === 0 ? <tr><td colSpan="6" className="px-5 py-12 text-center text-sm text-slate-500">Data formasi belum tersedia.</td></tr> : rows.map((row, index) => <tr key={`${row.sumber}-${row.nama_jabatan}-${row.kuota}-${index}`} className={`align-top transition hover:bg-slate-50 ${row.sumber === 'NonMaster' ? 'bg-amber-50/50' : ''}`}>
                                <td className="px-5 py-4 text-center text-sm text-slate-500">{index + 1}.</td>
                                <td className="px-5 py-4 font-semibold text-slate-800">{row.sumber === 'NonMaster' && <span className="mr-2 text-amber-600" title="Jabatan tidak terdaftar di master" aria-label="Jabatan tidak terdaftar di master">⚠</span>}{row.nama_jabatan}</td>
                                <td className="px-5 py-4 text-center text-sm font-bold text-slate-700">{row.kuota}</td>
                                <td className="px-5 py-4 text-center text-sm font-bold text-slate-700"><span>{row.terisi}</span>{row.kuota > 0 && <span className="mx-auto mt-2 block h-1.5 max-w-24 overflow-hidden rounded-full bg-slate-200"><span className="block h-full rounded-full bg-blue-600" style={{ width: `${row.persentase}%` }} /></span>}</td>
                                <td className={`px-5 py-4 text-center text-sm font-bold ${row.sisa < 0 ? 'text-amber-700' : row.sisa > 0 ? 'text-red-600' : 'text-emerald-700'}`}>{row.sisa < 0 ? `+${Math.abs(row.sisa)}` : row.sisa}</td>
                                <td className="px-5 py-4"><span className={`inline-flex rounded-full px-3 py-1 text-xs font-bold ${row.sisa > 0 ? 'bg-red-50 text-red-700' : row.sisa < 0 ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700'}`}>{row.status}</span></td>
                            </tr>)}
                        </tbody>
                        <tfoot className="border-t border-slate-100 bg-slate-50 font-bold text-slate-700">
                            <tr><td colSpan="2" className="px-5 py-4 text-right uppercase">Grand total</td><td className="px-5 py-4 text-center">{totals.kuota}</td><td className="px-5 py-4 text-center">{totals.terisi}</td><td className="px-5 py-4 text-center">{totals.sisa < 0 ? `+${Math.abs(totals.sisa)}` : totals.sisa}</td><td className="px-5 py-4" /></tr>
                        </tfoot>
                    </table>
                </div>
            </section>
            </div>
        </LegacyPageShell>
    );
}
