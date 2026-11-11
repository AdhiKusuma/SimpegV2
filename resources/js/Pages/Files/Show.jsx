import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Download, FileText } from 'lucide-react';
import LegacyPageShell from '../../Components/LegacyPageShell';

function formatRupiah(value) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value || 0));
}

function maskAccount(value) {
    const account = String(value || '');
    return account.length > 4 ? `${'•'.repeat(Math.max(0, account.length - 4))}${account.slice(-4)}` : account || '—';
}

function AmountTable({ title, rows }) {
    return <section className="simpeg-card overflow-hidden rounded-2xl border shadow-sm"><div className="border-b px-4 py-4 sm:px-5"><h2 className="simpeg-text font-semibold">{title}</h2></div><div className="divide-y">{rows.map((row) => <div key={row.field} className="flex items-center justify-between gap-4 px-4 py-3 text-sm sm:px-5"><span className="simpeg-muted">{row.label}</span><span className="simpeg-text text-right tabular-nums">{formatRupiah(row.amount)}</span></div>)}</div></section>;
}

export default function FilesShow({ slip, templateAvailable = false, backHref = '/files', backLabel = 'Kembali ke FILES', sectionLabel = 'FILES · SLIP GAJI' }) {
    return (
        <LegacyPageShell title={`Slip Gaji ${slip.period}`}>
            <Head title={`Slip Gaji ${slip.period}`} />
            <div className="mb-6 flex flex-wrap items-start justify-between gap-4">
                <div><Link href={backHref} className="simpeg-muted mb-3 inline-flex items-center gap-2 text-sm font-semibold"><ArrowLeft className="h-4 w-4" />{backLabel}</Link><p className="simpeg-muted mb-2 text-sm font-semibold uppercase tracking-[0.18em]">{sectionLabel}</p><h1 className="simpeg-text text-3xl font-extrabold tracking-tight">Periode {slip.period}</h1><p className="simpeg-muted mt-2">{slip.employee_name} · {slip.employee_id}</p></div>
                {templateAvailable ? <a href={`/files/${slip.payroll_import_id}/slip/${encodeURIComponent(slip.employee_id)}/download`} className="simpeg-primary inline-flex min-h-11 items-center gap-2 rounded-xl px-4 text-sm font-bold"><Download className="h-4 w-4" />Export PDF</a> : <span className="simpeg-utility inline-flex min-h-11 items-center gap-2 rounded-xl border px-4 text-sm font-semibold" title="Template PDF belum tersedia"><Download className="h-4 w-4" />PDF belum tersedia</span>}
            </div>

            {!templateAvailable && <div role="status" className="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-900">Data payroll sudah tersedia untuk preview. Referensi template PDF slip gaji perlu disediakan kembali agar preview final dan export mengikuti tata letak yang disetujui.</div>}

            <section className="simpeg-card mb-5 rounded-2xl border p-4 shadow-sm sm:p-5"><div className="flex items-start gap-3"><span className="simpeg-card-soft inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border"><FileText className="h-5 w-5" /></span><div className="grid flex-1 gap-4 text-sm sm:grid-cols-2"><div><p className="simpeg-muted">Nama pegawai</p><p className="simpeg-text mt-1 font-semibold">{slip.employee_name}</p></div><div><p className="simpeg-muted">Status</p><p className="simpeg-text mt-1 font-semibold">{slip.employment_status || '—'}</p></div><div><p className="simpeg-muted">Kantor</p><p className="simpeg-text mt-1 font-semibold">{slip.office_name} <span className="simpeg-muted font-mono text-xs">({slip.office_code})</span></p></div><div><p className="simpeg-muted">Tanggal gajian</p><p className="simpeg-text mt-1 font-semibold">{slip.payment_date || '—'}</p></div><div><p className="simpeg-muted">Nama nasabah</p><p className="simpeg-text mt-1 font-semibold">{slip.nama_nasabah || '—'}</p></div><div><p className="simpeg-muted">Rekening</p><p className="simpeg-text mt-1 font-mono font-semibold">{maskAccount(slip.no_rekening)}</p></div></div></div></section>

            <div className="grid gap-5 lg:grid-cols-2"><AmountTable title="Pendapatan" rows={slip.earnings} /><AmountTable title="Potongan" rows={slip.deductions} /></div>
            <section className="simpeg-card mt-5 rounded-2xl border p-5 shadow-sm"><div className="grid gap-4 sm:grid-cols-3"><div><p className="simpeg-muted text-sm">Jumlah gaji</p><p className="simpeg-text mt-1 text-lg font-bold tabular-nums">{formatRupiah(slip.gross_salary)}</p></div><div><p className="simpeg-muted text-sm">Jumlah potongan</p><p className="simpeg-text mt-1 text-lg font-bold tabular-nums">{formatRupiah(slip.total_deductions)}</p></div><div><p className="simpeg-muted text-sm">Gaji bersih</p><p className="text-xl font-extrabold text-emerald-700 dark:text-emerald-300 tabular-nums">{formatRupiah(slip.net_salary)}</p></div></div></section>
        </LegacyPageShell>
    );
}
