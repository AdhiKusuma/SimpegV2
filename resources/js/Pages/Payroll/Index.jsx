import { Head, Link, router, useForm } from '@inertiajs/react';
import { Banknote, Download, FileSpreadsheet, LockKeyhole, Plus, Trash2, Upload } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import LegacyPageShell from '../../Components/LegacyPageShell';
import MobileFilterGroup from '../../Components/MobileFilterGroup';
import SearchableCombobox from '../../Components/SearchableCombobox';
import SelectCombobox from '../../Components/SelectCombobox';
import { formatRupiah } from '../../lib/payrollMoney';

const monthNames = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

const amountFields = [
    ['gaji_pokok', 'Gaji pokok dasar'], ['golongan_pegawai', 'Nominal golongan'], ['tunj_suami_istri', 'Tunj. suami/istri'], ['tunj_anak', 'Tunj. anak'],
    ['tunj_jabatan', 'Tunj. jabatan'], ['tunj_ops', 'Tunj. operasional'], ['tunj_pajak', 'Tunj. pajak'],
    ['tunj_kinerja', 'Tunj. kinerja'], ['tunj_fungsional', 'Tunj. fungsional'], ['tunj_masa_kerja', 'Tunj. masa kerja'],
    ['honor_kontrak', 'Honor kontrak'], ['thl', 'Tenaga harian lepas'], ['uang_makan_kontrak', 'Tunj. uang makan'],
    ['bpjs_kesehatan', 'BPJS kesehatan'], ['bpjs_tenaga_kerja', 'BPJS ketenagakerjaan'], ['keris', 'Potongan Keris'],
    ['total', 'Total'],
];

const summaryFields = [
    ['gaji_pokok', 'Gaji pokok dasar'], ['golongan_pegawai', 'Nominal golongan'], ['total', 'Total'],
];

export default function Index({ filters, years = [], offices = [], summaryRows = [], summaryTotals = {}, entries, payrollImport, canImport, canEditMaster = false, canClearHistory = false, payrollHistoryCount = 0, scopeLabel, scopeLocked, flash = {} }) {
    const [selectedOffice, setSelectedOffice] = useState(String(filters.office || ''));
    const [selectedYear, setSelectedYear] = useState(String(filters.tahun));
    const [selectedMonth, setSelectedMonth] = useState(String(filters.bulan));
    const [clearHistoryOpen, setClearHistoryOpen] = useState(false);
    const clearHistoryDialog = useRef(null);
    const clearHistoryForm = useForm({ confirmation: '' });
    useEffect(() => {
        setSelectedOffice(String(filters.office || ''));
        setSelectedYear(String(filters.tahun));
        setSelectedMonth(String(filters.bulan));
    }, [filters.office, filters.tahun, filters.bulan]);
    useEffect(() => {
        const dialog = clearHistoryDialog.current;
        if (!dialog) return;
        if (clearHistoryOpen && !dialog.open) dialog.showModal();
        if (!clearHistoryOpen && dialog.open) dialog.close();
    }, [clearHistoryOpen]);
    const yearOptions = [...new Set([...years, Number(selectedYear)])].sort((a, b) => b - a);
    const officeOptions = [
        ...(!scopeLocked ? [{ value: '', label: 'Semua kantor dalam akses' }] : []),
        ...offices.map((office) => ({ value: office.value, label: `${office.label} (${office.code})` })),
    ];
    const exportParams = new URLSearchParams({ tahun: String(filters.tahun), bulan: String(filters.bulan) });
    if (filters.office) exportParams.set('office', filters.office);

    function applyFilters(event) {
        event.preventDefault();
        const data = new FormData(event.currentTarget);
        router.get('/payroll', Object.fromEntries(data.entries()), { preserveState: true, preserveScroll: true, replace: true });
    }

    function setView(view) {
        router.get('/payroll', { ...filters, view }, { preserveState: true, preserveScroll: true, replace: true });
    }

    function submitClearHistory(event) {
        event.preventDefault();
        clearHistoryForm.delete('/payroll/history', {
            preserveScroll: true,
            onSuccess: () => {
                setClearHistoryOpen(false);
                clearHistoryForm.reset();
            },
        });
    }

    function closeClearHistory() {
        setClearHistoryOpen(false);
        clearHistoryForm.reset();
        clearHistoryForm.clearErrors();
    }

    return (
        <LegacyPageShell title="Payroll">
            <Head title="Payroll" />
            <div className="mb-6 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p className="simpeg-muted mb-2 text-sm font-semibold uppercase tracking-[0.18em]">Penggajian</p>
                    <h1 className="simpeg-text text-3xl font-extrabold tracking-tight">Payroll</h1>
                    <p className="simpeg-muted mt-2">Baca rekap kantor dan rincian komponen payroll per periode.</p>
                </div>
                <div className="flex flex-col items-stretch gap-2 sm:items-end">
                    <div className="flex flex-wrap gap-2">
                        {canClearHistory && payrollHistoryCount > 0 && <button type="button" onClick={() => { clearHistoryForm.reset(); clearHistoryForm.clearErrors(); setClearHistoryOpen(true); }} className="simpeg-utility inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border px-4 text-sm font-bold focus:outline-none focus:ring-4 focus:ring-[var(--simpeg-focus-ring)]">
                            <Trash2 aria-hidden="true" className="h-4 w-4" /> Bersihkan riwayat
                        </button>}
                        {payrollImport ? <a href={`/payroll/export?${exportParams.toString()}`} className="simpeg-utility inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border px-4 text-sm font-bold">
                            <FileSpreadsheet aria-hidden="true" className="h-4 w-4" /> Export XLSX
                        </a> : <button type="button" disabled title="Belum ada data payroll pada periode yang dipilih." className="simpeg-utility inline-flex min-h-11 cursor-not-allowed items-center justify-center gap-2 rounded-xl border px-4 text-sm font-bold opacity-60">
                            <FileSpreadsheet aria-hidden="true" className="h-4 w-4" /> Export XLSX
                        </button>}
                        {canImport && <Link href="/payroll/import" className="simpeg-primary inline-flex min-h-11 items-center justify-center gap-2 rounded-xl px-4 text-sm font-bold">
                            <Upload aria-hidden="true" className="h-4 w-4" /> Import XLSX
                        </Link>}
                        {canEditMaster && <Link href="/payroll/edit/create" className="simpeg-utility inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border px-4 text-sm font-bold">
                            <Plus aria-hidden="true" className="h-4 w-4" /> Tambah manual
                        </Link>}
                    </div>
                    {!payrollImport && <p className="simpeg-muted text-xs sm:text-right">Export tersedia setelah payroll untuk periode ini diimpor.</p>}
                </div>
            </div>

            {canImport && <section className="simpeg-card-soft mb-6 rounded-2xl border p-4 sm:p-5" aria-labelledby="payroll-template-heading">
                <h2 id="payroll-template-heading" className="simpeg-text font-bold">Format file</h2>
                <p className="simpeg-muted mt-2 text-sm leading-6">Unduh template resmi, isi kolom payroll tanpa mengubah susunannya, lalu unggah file untuk memeriksa preview. Nama sheet bebas. Hasil ekspor mengikuti format sheet hasil ketika export pada workbook.</p>
                <a href="/payroll/template" download className="simpeg-primary mt-4 inline-flex min-h-11 items-center justify-center gap-2 rounded-xl px-4 text-sm font-bold focus:outline-none focus:ring-4 focus:ring-[var(--simpeg-focus-ring)]">
                    <Download aria-hidden="true" className="h-4 w-4" /> Unduh template XLSX
                </a>
            </section>}

            {flash.success && <div role="status" className="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{flash.success}</div>}
            {flash.error && <div role="alert" className="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-900">{flash.error}</div>}

            <section className="simpeg-card mb-6 rounded-2xl border p-4 shadow-sm sm:p-5" aria-label="Filter payroll">
                <form onSubmit={applyFilters} className="simpeg-filter-toolbar simpeg-filter-toolbar--mobile-panel grid min-w-0 gap-4 sm:grid-cols-2 xl:grid-cols-[minmax(9rem,0.55fr)_minmax(10rem,0.55fr)_minmax(16rem,1.2fr)_auto] xl:items-end">
                    <MobileFilterGroup id="payroll-mobile-filters" activeCount={Number(Boolean(selectedOffice))}>
                    <div>
                        <label htmlFor="payroll-year" className="simpeg-muted mb-2 block text-xs font-bold uppercase tracking-wide">Tahun</label>
                        <SelectCombobox
                            id="payroll-year"
                            name="tahun"
                            value={selectedYear}
                            onChange={setSelectedYear}
                            options={yearOptions.map((year) => ({ value: String(year), label: String(year) }))}
                            placeholder="Pilih tahun"
                        />
                    </div>
                    <div>
                        <label htmlFor="payroll-month" className="simpeg-muted mb-2 block text-xs font-bold uppercase tracking-wide">Bulan</label>
                        <SelectCombobox
                            id="payroll-month"
                            name="bulan"
                            value={selectedMonth}
                            onChange={setSelectedMonth}
                            options={monthNames.map((month, index) => ({ value: String(index + 1), label: month }))}
                            placeholder="Pilih bulan"
                        />
                    </div>
                    <SearchableCombobox
                        id="payroll-office"
                        name="office"
                        label="Cabang / kantor"
                        value={selectedOffice}
                        onChange={setSelectedOffice}
                        options={officeOptions}
                        placeholder={scopeLabel || 'Semua kantor dalam akses'}
                        emptyMessage="Kantor tidak ditemukan."
                        disabled={scopeLocked || officeOptions.length === 0}
                    />
                        <button type="submit" className="simpeg-primary simpeg-mobile-filter-apply">Terapkan</button>
                    </MobileFilterGroup>
                    <input type="hidden" name="view" value={filters.view} />
                    <button type="submit" className="simpeg-primary simpeg-desktop-filter-apply inline-flex h-11 items-center justify-center rounded-xl px-5 text-sm font-bold focus:outline-none focus:ring-4 focus:ring-[var(--simpeg-focus-ring)] sm:col-span-2 xl:col-span-1">Terapkan</button>
                </form>
                {scopeLabel && <p className="simpeg-muted mt-3 flex items-center gap-2 text-xs"><LockKeyhole aria-hidden="true" className="h-3.5 w-3.5" />{scopeLabel}</p>}
            </section>

            <section className="simpeg-card overflow-hidden rounded-2xl border shadow-sm">
                <div className="flex flex-wrap items-center justify-between gap-3 border-b px-4 py-4 sm:px-5">
                    <div className="flex items-center gap-3">
                        <span className="simpeg-card-soft inline-flex h-10 w-10 items-center justify-center rounded-xl border"><Banknote aria-hidden="true" className="h-5 w-5" /></span>
                        <div>
                            <h2 className="simpeg-text font-semibold">{filters.view === 'rincian' ? 'Rincian pegawai' : 'Rekap payroll per kantor'}</h2>
                            <p className="simpeg-muted text-sm">{monthNames[Number(filters.bulan) - 1]} {filters.tahun}{payrollImport ? ` · ${payrollImport.visible_employee_count.toLocaleString('id-ID')} pegawai dalam cakupan ini` : ''}</p>
                        </div>
                    </div>
                    {payrollImport && <div className="flex flex-wrap items-center gap-x-4 gap-y-1">
                        <span className="simpeg-muted inline-flex items-center gap-2 text-xs"><FileSpreadsheet aria-hidden="true" className="h-4 w-4" />{payrollImport.source_filename}</span>
                        <span className="simpeg-muted text-xs">Diimpor {payrollImport.imported_at} oleh {payrollImport.imported_by_name}</span>
                    </div>}
                </div>

                <div className="flex flex-wrap gap-2 border-b px-4 py-3 sm:px-5" role="tablist" aria-label="Tampilan payroll">
                    <button type="button" role="tab" aria-selected={filters.view === 'rekap'} onClick={() => setView('rekap')} className={`rounded-lg px-3 py-2 text-sm font-semibold transition ${filters.view === 'rekap' ? 'simpeg-primary' : 'simpeg-utility border'}`}>Rekap kantor</button>
                    <button type="button" role="tab" aria-selected={filters.view === 'rincian'} onClick={() => setView('rincian')} className={`rounded-lg px-3 py-2 text-sm font-semibold transition ${filters.view === 'rincian' ? 'simpeg-primary' : 'simpeg-utility border'}`}>Rincian pegawai</button>
                </div>

                {!payrollImport ? <div className="px-5 py-14 text-center">
                    <FileSpreadsheet aria-hidden="true" className="simpeg-subtle mx-auto mb-3 h-8 w-8" />
                    <p className="simpeg-text font-semibold">Belum ada payroll untuk periode ini.</p>
                    <p className="simpeg-muted mt-1 text-sm">Pilih periode lain atau unggah workbook dengan kolom payroll yang sesuai template.</p>
                </div> : filters.view === 'rekap' ? <div className="overflow-x-auto">
                    <table className="w-full min-w-[900px] text-left text-sm">
                        <thead className="simpeg-muted border-b text-xs uppercase tracking-wide"><tr>
                            <th className="px-5 py-4">Kantor</th><th className="px-4 py-4 text-right">Pegawai</th>
                            {summaryFields.map(([, label]) => <th key={label} className="px-4 py-4 text-right">{label}</th>)}
                        </tr></thead>
                        <tbody className="divide-y">
                            {summaryRows.length === 0 ? <tr><td colSpan="4" className="simpeg-muted px-5 py-12 text-center">Tidak ada data kantor pada periode ini.</td></tr> : summaryRows.map((row) => <tr key={row.office_code} className="transition hover:bg-slate-50/70 dark:hover:bg-slate-800/50">
                                <td className="px-5 py-4"><span className="simpeg-text block font-semibold">{row.office_name}</span><span className="simpeg-muted mt-1 block font-mono text-xs">{row.office_code}{row.branch_code ? ` · Cabang ${row.branch_code}` : ''}</span></td>
                                <td className="simpeg-text px-4 py-4 text-right tabular-nums">{row.employee_count.toLocaleString('id-ID')}</td>
                                {summaryFields.map(([field]) => <td key={field} className="simpeg-text px-4 py-4 text-right tabular-nums">{formatRupiah(row[field])}</td>)}
                            </tr>)}
                        </tbody>
                        {summaryRows.length > 0 && <tfoot className="simpeg-muted border-t bg-slate-50/70 font-semibold dark:bg-slate-900/40"><tr>
                            <td className="px-5 py-4">Total dalam tampilan</td><td className="px-4 py-4 text-right tabular-nums">{Number(summaryTotals.employee_count || 0).toLocaleString('id-ID')}</td>
                            {summaryFields.map(([field]) => <td key={field} className="px-4 py-4 text-right tabular-nums">{formatRupiah(summaryTotals[field])}</td>)}
                        </tr></tfoot>}
                    </table>
                </div> : <>
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[2600px] text-left text-sm">
                            <thead className="simpeg-muted border-b text-xs uppercase tracking-wide"><tr>
                                <th className="sticky left-0 z-10 simpeg-card px-4 py-4">Pegawai</th><th className="px-4 py-4">Kantor payroll</th>
                                <th className="px-4 py-4">Kode kantor rekening</th><th className="px-4 py-4">No rekening</th>
                                <th className="px-4 py-4">Nama nasabah</th><th className="px-4 py-4">Tanggal gajian</th>
                                {amountFields.map(([, label]) => <th key={label} className="px-4 py-4 text-right">{label}</th>)}
                            </tr></thead>
                            <tbody className="divide-y">
                                {entries?.data?.length ? entries.data.map((entry) => <tr key={entry.id} className="transition hover:bg-slate-50/70 dark:hover:bg-slate-800/50">
                                    <td className="sticky left-0 z-10 simpeg-card px-4 py-3"><span className="simpeg-text block max-w-56 truncate font-semibold">{entry.employee_name}</span><span className="simpeg-muted font-mono text-xs">{entry.employee_id}</span></td>
                                    <td className="simpeg-muted px-4 py-3 font-mono text-xs">{entry.office_code}</td>
                                    <td className="simpeg-muted px-4 py-3 font-mono text-xs">{entry.bank_office_code || '—'}</td>
                                    <td className="simpeg-muted px-4 py-3 font-mono text-xs">{entry.no_rekening || '—'}</td>
                                    <td className="simpeg-text px-4 py-3">{entry.nama_nasabah || '—'}</td>
                                    <td className="simpeg-muted px-4 py-3">{entry.tgl_gajian || '—'}</td>
                                    {amountFields.map(([field]) => <td key={field} className="simpeg-text px-4 py-3 text-right tabular-nums">{formatRupiah(entry[field])}</td>)}
                                </tr>) : <tr><td colSpan={amountFields.length + 6} className="simpeg-muted px-5 py-12 text-center">Tidak ada rincian pegawai untuk kantor dan periode ini.</td></tr>}
                            </tbody>
                        </table>
                    </div>
                    {entries?.data?.length > 0 && <div className="flex flex-wrap items-center justify-between gap-3 border-t p-4 text-sm">
                        <span className="simpeg-muted">Menampilkan {entries.from}–{entries.to} dari {entries.total.toLocaleString('id-ID')} pegawai</span>
                        <div className="flex flex-wrap gap-2">{entries.links.filter((link) => link.url).map((link) => <Link key={link.label} href={link.url} preserveState className={`rounded-lg border px-3 py-2 ${link.active ? 'simpeg-primary' : 'simpeg-utility'}`} dangerouslySetInnerHTML={{ __html: link.label }} />)}</div>
                    </div>}
                </>}
            </section>

            <p className="simpeg-muted mt-4 text-xs">Data payroll bersifat terbatas. Rincian hanya menampilkan akun sesuai hak akses dan cakupan kantornya.</p>

            <dialog
                ref={clearHistoryDialog}
                onClose={() => setClearHistoryOpen(false)}
                aria-labelledby="clear-payroll-history-title"
                aria-describedby="clear-payroll-history-description"
                className="simpeg-card m-auto w-[min(32rem,calc(100%-2rem))] max-w-none rounded-2xl border p-0 shadow-2xl backdrop:bg-slate-950/50 backdrop:backdrop-blur-sm"
            >
                <form onSubmit={submitClearHistory} className="p-5 sm:p-6">
                    <div className="mb-4 flex items-start gap-3">
                        <span className="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-red-100 text-red-700 dark:bg-red-950/50 dark:text-red-300">
                            <Trash2 aria-hidden="true" className="h-5 w-5" />
                        </span>
                        <div>
                            <h2 id="clear-payroll-history-title" className="simpeg-text text-lg font-bold">Bersihkan riwayat Payroll?</h2>
                            <p id="clear-payroll-history-description" className="simpeg-muted mt-1 text-sm leading-6">
                                Ini akan menghapus permanen {Number(payrollHistoryCount).toLocaleString('id-ID')} batch import Payroll beserta rincian dan rekapnya. Data pegawai dan master data tidak terpengaruh.
                            </p>
                        </div>
                    </div>
                    <label htmlFor="clear-payroll-confirmation" className="simpeg-text mb-2 block text-sm font-semibold">Ketik HAPUS untuk melanjutkan</label>
                    <input
                        id="clear-payroll-confirmation"
                        autoFocus
                        autoComplete="off"
                        value={clearHistoryForm.data.confirmation}
                        onChange={(event) => clearHistoryForm.setData('confirmation', event.target.value)}
                        aria-invalid={Boolean(clearHistoryForm.errors.confirmation)}
                        aria-describedby={clearHistoryForm.errors.confirmation ? 'clear-payroll-confirmation-error' : undefined}
                        className="simpeg-input h-11 w-full rounded-xl border px-3 text-sm outline-none focus:ring-4 focus:ring-[var(--simpeg-focus-ring)]"
                        placeholder="HAPUS"
                    />
                    {clearHistoryForm.errors.confirmation && <p id="clear-payroll-confirmation-error" role="alert" className="mt-2 text-sm font-medium text-red-700 dark:text-red-300">{clearHistoryForm.errors.confirmation}</p>}
                    <div className="mt-6 flex flex-col-reverse justify-end gap-2 sm:flex-row">
                        <button type="button" onClick={closeClearHistory} className="simpeg-utility inline-flex min-h-11 items-center justify-center rounded-xl border px-4 text-sm font-semibold focus:outline-none focus:ring-4 focus:ring-[var(--simpeg-focus-ring)]">Batal</button>
                        <button type="submit" disabled={clearHistoryForm.processing || clearHistoryForm.data.confirmation !== 'HAPUS'} className="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-red-700 px-4 text-sm font-bold text-white transition hover:bg-red-800 focus:outline-none focus:ring-4 focus:ring-red-700/30 disabled:cursor-not-allowed disabled:opacity-50">
                            <Trash2 aria-hidden="true" className="h-4 w-4" /> {clearHistoryForm.processing ? 'Membersihkan…' : 'Hapus riwayat'}
                        </button>
                    </div>
                </form>
            </dialog>
        </LegacyPageShell>
    );
}
