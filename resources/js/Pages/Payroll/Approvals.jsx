import { Head, Link } from '@inertiajs/react';
import { Banknote, ChevronRight, Clock3 } from 'lucide-react';
import LegacyPageShell from '../../Components/LegacyPageShell';

function pageLabel(label) {
    return label === '&laquo; Previous' ? 'Sebelumnya' : label === 'Next &raquo;' ? 'Berikutnya' : label;
}

function approvalTitle(type) {
    if (type === 'import') return 'Import payroll';
    if (type === 'manual_create') return 'Tambah Master Gaji';
    if (type === 'manual_delete') return 'Hapus Master Gaji';
    return 'Edit Master Gaji';
}

export default function Approvals({ approvals, scopeLabel, flash = {} }) {
    return (
        <LegacyPageShell title="Approval Payroll">
            <Head title="Approval Payroll" />
            <div className="mb-6">
                <p className="simpeg-muted mb-2 text-sm font-semibold uppercase tracking-[0.18em]">Master Gaji</p>
                <h1 className="simpeg-text text-3xl font-extrabold tracking-tight">Approval Payroll</h1>
                <p className="simpeg-muted mt-2">Tinjau pengajuan payroll dari kantor yang berada dalam cakupan Anda.</p>
                {scopeLabel && <p className="simpeg-muted mt-2 text-sm">Cakupan: {scopeLabel}</p>}
            </div>
            {flash.success && <div role="status" className="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{flash.success}</div>}
            {flash.error && <div role="alert" className="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-900">{flash.error}</div>}
            <section className="simpeg-card overflow-hidden rounded-2xl border shadow-sm">
                <div className="flex items-center gap-3 border-b p-4 sm:p-5"><span className="simpeg-card-soft inline-flex h-10 w-10 items-center justify-center rounded-xl border"><Banknote aria-hidden="true" className="h-5 w-5" /></span><div><h2 className="simpeg-text font-semibold">Menunggu keputusan</h2><p className="simpeg-muted text-sm">{Number(approvals.total || 0).toLocaleString('id-ID')} pengajuan</p></div></div>
                {approvals.data.length ? <div className="divide-y simpeg-divider">
                    {approvals.data.map((item) => <article key={item.id} className="flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5">
                        <div className="min-w-0">
                            <div className="flex flex-wrap items-center gap-2"><h3 className="simpeg-text font-semibold">{approvalTitle(item.type)}</h3>{item.period && <span className="simpeg-card-soft rounded-full border px-2.5 py-1 text-xs font-semibold">Periode {item.period}</span>}</div>
                            <p className="simpeg-muted mt-1 text-sm">Diajukan oleh {item.requester}{item.employee_count ? ` · ${Number(item.employee_count).toLocaleString('id-ID')} pegawai` : ''}</p>
                            {item.office_names?.length > 0 && <p className="simpeg-muted mt-1 truncate text-sm">Kantor: {item.office_names.map((office) => `${office.name} (${office.code})`).join(', ')}</p>}
                            {item.employee_sample?.length > 0 && <p className="simpeg-muted mt-1 truncate text-xs">{item.employee_sample.join(', ')}{item.employee_count > item.employee_sample.length ? ', dan lainnya' : ''}</p>}
                        </div>
                        <div className="flex shrink-0 items-center justify-between gap-3 sm:justify-end"><span className="simpeg-muted inline-flex items-center gap-1.5 text-xs"><Clock3 aria-hidden="true" className="h-3.5 w-3.5" />{item.submitted_at || '—'}</span><Link href={`/payroll/approvals/${item.id}`} className="simpeg-primary inline-flex min-h-10 items-center gap-2 rounded-xl px-4 text-sm font-semibold">Tinjau <ChevronRight aria-hidden="true" className="h-4 w-4" /></Link></div>
                    </article>)}
                </div> : <div className="px-5 py-14 text-center"><p className="simpeg-text font-semibold">Tidak ada pengajuan yang menunggu.</p><p className="simpeg-muted mt-1 text-sm">Pengajuan hanya ditampilkan untuk kantor dalam cakupan Anda.</p></div>}
                {approvals.links?.length > 3 && <nav aria-label="Halaman approval" className="flex flex-wrap justify-end gap-2 border-t p-4">{approvals.links.map((link, index) => link.url ? <Link key={`${index}-${link.label}`} href={link.url} preserveScroll className={`simpeg-utility inline-flex min-h-9 items-center rounded-lg border px-3 text-sm ${link.active ? 'font-bold' : ''}`}>{pageLabel(link.label)}</Link> : <span key={`${index}-${link.label}`} className="simpeg-muted inline-flex min-h-9 items-center rounded-lg border px-3 text-sm opacity-60">{pageLabel(link.label)}</span>)}</nav>}
            </section>
        </LegacyPageShell>
    );
}
