import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowRight, FileText, LockKeyhole } from 'lucide-react';
import SelectCombobox from '../../Components/SelectCombobox';
import LegacyPageShell from '../../Components/LegacyPageShell';

const monthNames = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
];

function formatRupiah(value) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value || 0));
}

function pageLabel(label) {
    return label === '&laquo; Previous' ? 'Sebelumnya' : label === 'Next &raquo;' ? 'Berikutnya' : label;
}

export default function FilesIndex({ slips, periods = [], filters = {}, isAdministrative = false, scopeLabel, templateAvailable = false, pageTitle = 'Slip Gaji', sectionLabel = 'FILES', pageDescription = 'Slip gaji resmi yang tersedia dari payroll yang sudah dipublikasikan.', indexUrl = '/files', detailUrlPrefix = '/files' }) {
    const form = useForm({
        tahun: filters.tahun || '',
        bulan: filters.bulan || '',
        employee_id: isAdministrative ? filters.employee_id || '' : '',
    });
    const years = [...new Set(periods.map((period) => period.tahun))];
    const yearOptions = years.map((year) => ({ value: String(year), label: String(year) }));
    const monthOptions = monthNames.map((month, index) => ({ value: String(index + 1), label: month }));

    function submit(event) {
        event.preventDefault();
        router.get(indexUrl, form.data, { preserveState: true, preserveScroll: true, replace: true });
    }

    return (
        <LegacyPageShell title={pageTitle}>
            <Head title={pageTitle} />
            <div className="mb-6">
                <p className="simpeg-muted mb-2 text-sm font-semibold uppercase tracking-[0.18em]">{sectionLabel}</p>
                <h1 className="simpeg-text text-3xl font-extrabold tracking-tight">{pageTitle}</h1>
                <p className="simpeg-muted mt-2">{pageDescription}</p>
                {scopeLabel && <p className="simpeg-muted mt-2 flex items-center gap-2 text-sm"><LockKeyhole className="h-4 w-4" />{scopeLabel}</p>}
            </div>

            {!templateAvailable && <div role="status" className="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-900">
                Daftar slip tersedia. Referensi template PDF belum tersedia, sehingga preview/PDF belum dapat dipublikasikan.
            </div>}

            <section className="simpeg-card mb-5 rounded-2xl border p-4 shadow-sm sm:p-5">
                <form onSubmit={submit} className="grid gap-3 md:grid-cols-4 md:items-end">
                    <SelectCombobox id="files-year" label="Tahun" value={form.data.tahun} options={yearOptions} emptyLabel="Semua tahun" placeholder="Pilih tahun" onChange={(value) => form.setData('tahun', value)} />
                    <SelectCombobox id="files-month" label="Bulan" value={form.data.bulan} options={monthOptions} emptyLabel="Semua bulan" placeholder="Pilih bulan" onChange={(value) => form.setData('bulan', value)} />
                    {isAdministrative && <label className="block text-sm font-semibold">
                        <span className="simpeg-muted mb-2 block text-xs uppercase tracking-wide">Nama / ID pegawai</span>
                        <input value={form.data.employee_id} onChange={(event) => form.setData('employee_id', event.target.value)} className="simpeg-input h-11 w-full rounded-xl border px-3 text-sm outline-none focus:ring-4 focus:ring-[var(--simpeg-focus-ring)]" placeholder="Cari nama atau ID pegawai" maxLength={150} />
                    </label>}
                    <button type="submit" className="simpeg-primary inline-flex h-11 items-center justify-center rounded-xl px-5 text-sm font-bold focus:outline-none focus:ring-4 focus:ring-[var(--simpeg-focus-ring)]">Terapkan</button>
                </form>
            </section>

            <section className="simpeg-card overflow-hidden rounded-2xl border shadow-sm">
                <div className="flex items-center gap-3 border-b p-4 sm:p-5">
                    <span className="simpeg-card-soft inline-flex h-10 w-10 items-center justify-center rounded-xl border"><FileText className="h-5 w-5" /></span>
                    <div><h2 className="simpeg-text font-semibold">Slip tersedia</h2><p className="simpeg-muted text-sm">{Number(slips.total || 0).toLocaleString('id-ID')} slip</p></div>
                </div>
                {slips.data?.length ? <div className="overflow-x-auto">
                    <table className="w-full min-w-[760px] text-left text-sm">
                        <thead className="simpeg-muted border-b text-xs uppercase tracking-wide"><tr><th className="px-5 py-4">Pegawai</th><th className="px-4 py-4">Periode</th><th className="px-4 py-4">Tanggal gajian</th><th className="px-4 py-4 text-right">Gaji bersih</th><th className="px-5 py-4 text-right">Aksi</th></tr></thead>
                        <tbody className="divide-y">
                            {slips.data.map((slip) => <tr key={`${slip.payroll_import_id}-${slip.employee_id}`} className="transition hover:bg-slate-50/70 dark:hover:bg-slate-800/50">
                                <td className="px-5 py-4"><span className="simpeg-text block font-semibold">{slip.employee_name}</span><span className="simpeg-muted font-mono text-xs">{slip.employee_id} · {slip.office_name}</span></td>
                                <td className="simpeg-text px-4 py-4">{slip.period}</td>
                                <td className="simpeg-muted px-4 py-4">{slip.payment_date || '—'}</td>
                                <td className="simpeg-text px-4 py-4 text-right font-semibold tabular-nums">{formatRupiah(slip.net_salary)}</td>
                                <td className="px-5 py-4 text-right"><Link href={`${detailUrlPrefix}/${slip.payroll_import_id}/slip/${encodeURIComponent(slip.employee_id)}`} className="simpeg-primary inline-flex min-h-10 items-center gap-2 rounded-xl px-3 text-sm font-semibold">Buka <ArrowRight className="h-4 w-4" /></Link></td>
                            </tr>)}
                        </tbody>
                    </table>
                </div> : <div className="px-5 py-14 text-center"><FileText className="simpeg-subtle mx-auto mb-3 h-8 w-8" /><p className="simpeg-text font-semibold">Belum ada slip gaji tersedia.</p><p className="simpeg-muted mt-1 text-sm">Slip muncul setelah payroll periode tersebut resmi dipublikasikan.</p></div>}
                {slips.links?.length > 3 && <nav aria-label="Halaman slip gaji" className="flex flex-wrap justify-end gap-2 border-t p-4">{slips.links.map((link, index) => link.url ? <Link key={`${index}-${link.label}`} href={link.url} preserveScroll className={`simpeg-utility inline-flex min-h-9 items-center rounded-lg border px-3 text-sm ${link.active ? 'font-bold' : ''}`}>{pageLabel(link.label)}</Link> : <span key={`${index}-${link.label}`} className="simpeg-muted inline-flex min-h-9 items-center rounded-lg border px-3 text-sm opacity-60">{pageLabel(link.label)}</span>)}</nav>}
            </section>
        </LegacyPageShell>
    );
}
