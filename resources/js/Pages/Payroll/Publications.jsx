import { Head, Link, router, useForm } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, CalendarClock, CheckCircle2, Clock3, Send, Trash2, X, XCircle } from 'lucide-react';
import { useState } from 'react';
import LegacyPageShell from '../../Components/LegacyPageShell';

const statusStyles = {
    sent: 'bg-emerald-50 text-emerald-700',
    scheduled: 'bg-amber-50 text-amber-800',
    processing: 'bg-blue-50 text-blue-700',
    failed: 'bg-red-50 text-red-700',
    cancelled: 'bg-slate-100 text-slate-600',
    legacy: 'bg-slate-100 text-slate-600',
};

function ScheduleForm({ publication }) {
    const [scheduledAt, setScheduledAt] = useState(publication.scheduled_at_input || '');

    function submit(event) {
        event.preventDefault();
        router.post(`/payroll/publications/${publication.id}/schedule`, { scheduled_at: scheduledAt }, { preserveScroll: true });
    }

    return (
        <form onSubmit={submit} className="grid w-full grid-cols-[minmax(0,1fr)_auto] items-center gap-2">
            <label className="sr-only" htmlFor={`publication-date-${publication.id}`}>Jadwal pengiriman</label>
            <input
                id={`publication-date-${publication.id}`}
                type="datetime-local"
                value={scheduledAt}
                onChange={(event) => setScheduledAt(event.target.value)}
                className="simpeg-input h-10 w-full rounded-lg border px-3 text-xs"
                required
            />
            <button type="submit" className="simpeg-primary inline-flex h-10 items-center justify-center gap-1.5 whitespace-nowrap rounded-lg px-3 text-xs font-bold">
                <CalendarClock aria-hidden="true" className="h-4 w-4" /> Jadwalkan
            </button>
            <p className="simpeg-muted col-span-2 text-left text-[11px] leading-4">Jika waktu yang dipilih sudah lewat, pengiriman akan diproses segera.</p>
        </form>
    );
}

function RecipientIndicator({ publication, onViewMissing }) {
    const isDeliveryStatus = publication.is_legacy || ['sent', 'failed'].includes(publication.status);
    const total = Number(publication.payroll_entry_count ?? 0);
    const covered = isDeliveryStatus
        ? Number(publication.notified_count ?? 0)
        : Number(publication.account_count ?? 0);
    const missing = Number(publication.missing_recipient_count ?? 0);
    const missingLabel = isDeliveryStatus ? 'belum menerima' : 'belum terhubung';

    return (
        <div className="min-w-[150px]">
            <p className="simpeg-text font-semibold">{covered} / {total} pegawai</p>
            <p className="simpeg-muted mt-0.5 text-xs">{isDeliveryStatus ? 'notifikasi tercatat' : 'akun terhubung'}</p>
            {missing > 0 ? (
                <button type="button" onClick={() => onViewMissing(publication)} className="mt-1 inline-flex items-center gap-1 text-left text-xs font-bold text-amber-700 underline decoration-amber-300 underline-offset-2 hover:text-amber-800 dark:text-amber-300 dark:decoration-amber-700 dark:hover:text-amber-200"><AlertTriangle aria-hidden="true" className="h-3.5 w-3.5" /> {missing} {missingLabel}</button>
            ) : <p className="mt-1 text-xs font-semibold text-emerald-700 dark:text-emerald-300">Semua penerima terdata</p>}
        </div>
    );
}

function PublicationActions({ publication, canManage, onDelete }) {
    if (!canManage) return null;
    if (publication.is_legacy) {
        return (
            <div className="flex w-full items-center justify-end gap-2">
                <span className="simpeg-muted mr-1 text-xs">Publikasi lama</span>
                <button type="button" onClick={() => onDelete(publication)} className="inline-flex h-10 items-center gap-1.5 rounded-lg border border-red-300 px-3 text-xs font-bold text-red-700 transition hover:bg-red-50 focus:outline-none focus:ring-4 focus:ring-red-600/20 dark:border-red-800 dark:text-red-300 dark:hover:bg-red-950/30"><Trash2 aria-hidden="true" className="h-4 w-4" /> Hapus riwayat</button>
            </div>
        );
    }
    const isProcessing = publication.status === 'processing';
    const canSchedule = !isProcessing && publication.status !== 'sent' && ['scheduled', 'failed', 'cancelled'].includes(publication.status);

    return (
        <div className="ml-auto flex w-full max-w-[560px] flex-col items-stretch gap-3">
            {canSchedule && (
                <div className="simpeg-card-soft w-full rounded-xl border p-3">
                    <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <span className="simpeg-text text-[11px] font-bold uppercase tracking-[0.12em]">Atur jadwal pengiriman</span>
                        <span className="simpeg-muted text-[11px]">{publication.scheduled_at ? `Saat ini: ${publication.scheduled_at}` : 'Belum dijadwalkan'}</span>
                    </div>
                    <ScheduleForm publication={publication} />
                </div>
            )}
            <div className="flex w-full flex-wrap items-center justify-end gap-2 border-t border-slate-200/70 pt-3 dark:border-slate-700/70">
                {publication.status === 'sent' && <span className="simpeg-muted mr-1 text-xs">Sudah dikirim</span>}
                {isProcessing && <span className="simpeg-muted mr-1 text-xs">Sedang diproses</span>}
                {!isProcessing && publication.status !== 'sent' && <button type="button" onClick={() => router.post(`/payroll/publications/${publication.id}/send`, {}, { preserveScroll: true })} className="simpeg-primary inline-flex h-10 items-center gap-1.5 rounded-lg px-3 text-xs font-bold"><Send aria-hidden="true" className="h-4 w-4" /> Kirim sekarang</button>}
                {!isProcessing && publication.status === 'scheduled' && <button type="button" onClick={() => router.post(`/payroll/publications/${publication.id}/cancel`, {}, { preserveScroll: true })} className="simpeg-utility inline-flex h-10 items-center gap-1.5 rounded-lg border px-3 text-xs font-bold"><XCircle aria-hidden="true" className="h-4 w-4" /> Batalkan</button>}
            </div>
            {!isProcessing && <div className="flex w-full justify-end border-t border-slate-200/70 pt-3 dark:border-slate-700/70"><button type="button" onClick={() => onDelete(publication)} className="inline-flex h-10 items-center gap-1.5 rounded-lg border border-red-300 px-3 text-xs font-bold text-red-700 transition hover:bg-red-50 focus:outline-none focus:ring-4 focus:ring-red-600/20 dark:border-red-800 dark:text-red-300 dark:hover:bg-red-950/30"><Trash2 aria-hidden="true" className="h-4 w-4" /> Hapus riwayat</button></div>}
        </div>
    );
}

function PublicationCard({ publication, canManage, onDelete, onViewMissing }) {
    const statusClass = statusStyles[publication.status] || statusStyles.legacy;

    return (
        <article className="simpeg-card rounded-2xl border p-4 shadow-sm">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <p className="simpeg-text font-semibold">Periode {publication.period}</p>
                    <p className="simpeg-muted mt-1 truncate text-xs">{publication.source_filename}</p>
                </div>
                <span className={`shrink-0 rounded-full px-2.5 py-1 text-[11px] font-bold ${statusClass}`}>{publication.status_label}</span>
            </div>
            <dl className="simpeg-muted mt-4 grid grid-cols-2 gap-3 text-xs">
                <div><dt>Jadwal</dt><dd className="simpeg-text mt-1 font-semibold">{publication.scheduled_at || '—'}</dd></div>
                <div><dt>Dikirim</dt><dd className="simpeg-text mt-1 font-semibold">{publication.sent_at || '—'}</dd></div>
                <div className="col-span-2"><dt>Penerima</dt><dd className="mt-1"><RecipientIndicator publication={publication} onViewMissing={onViewMissing} /></dd></div>
                <div><dt>Notifikasi baru</dt><dd className="simpeg-text mt-1 font-semibold">{publication.sent_count ?? '—'}</dd></div>
            </dl>
            <div className="mt-4 border-t pt-3"><PublicationActions publication={publication} canManage={canManage} onDelete={onDelete} /></div>
        </article>
    );
}

export default function Publications({ publications = [], canManage = false, scopeLabel = null, flash = {} }) {
    const [deleteTarget, setDeleteTarget] = useState(null);
    const [missingTarget, setMissingTarget] = useState(null);
    const deleteForm = useForm({ reason: '' });

    function openDelete(publication) {
        setDeleteTarget(publication);
        deleteForm.setData('reason', '');
        deleteForm.clearErrors();
    }

    function submitDelete(event) {
        event.preventDefault();
        const deleteUrl = deleteTarget.is_legacy
            ? `/payroll/publications/import/${deleteTarget.import_id}`
            : `/payroll/publications/${deleteTarget.id}`;
        deleteForm.delete(deleteUrl, {
            preserveScroll: true,
            onSuccess: () => setDeleteTarget(null),
        });
    }

    function openMissing(publication) {
        setMissingTarget(publication);
    }

    return (
        <LegacyPageShell title="Publikasi & Pengiriman Slip">
            <Head title="Publikasi & Pengiriman Slip" />
            <div className="mb-6 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <Link href="/payroll" className="simpeg-muted mb-4 inline-flex items-center gap-2 text-sm font-semibold hover:underline"><ArrowLeft aria-hidden="true" className="h-4 w-4" /> Kembali ke payroll</Link>
                    <p className="simpeg-muted mb-2 text-sm font-semibold uppercase tracking-[0.18em]">Payroll</p>
                    <h1 className="simpeg-text text-3xl font-extrabold tracking-tight">Publikasi &amp; Pengiriman Slip</h1>
                    <p className="simpeg-muted mt-2 max-w-3xl">Atur kapan slip gaji dikirim ke akun pegawai. Pengiriman dilakukan bertahap agar proses tetap lancar dan notifikasi tidak terkirim berulang.</p>
                    {scopeLabel && <p className="simpeg-muted mt-2 text-xs font-semibold">{scopeLabel}</p>}
                </div>
                {canManage && <Link href="/payroll/import" className="simpeg-primary inline-flex min-h-11 items-center gap-2 rounded-xl px-4 text-sm font-bold"><CalendarClock aria-hidden="true" className="h-4 w-4" /> Import payroll</Link>}
            </div>

            {flash.success && <div role="status" className="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{flash.success}</div>}
            {flash.error && <div role="alert" className="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800">{flash.error}</div>}

            <section className="simpeg-card overflow-hidden rounded-2xl border shadow-sm">
                <div className="border-b p-4 sm:p-5"><div className="flex items-center gap-3"><span className="simpeg-card-soft inline-flex h-10 w-10 items-center justify-center rounded-xl border"><Send aria-hidden="true" className="h-5 w-5" /></span><div><h2 className="simpeg-text font-semibold">Riwayat publikasi</h2><p className="simpeg-muted mt-1 text-sm">{publications.length} batch tercatat di modul publikasi.</p></div></div></div>
                {publications.length === 0 ? <div className="p-8 text-center"><CheckCircle2 aria-hidden="true" className="simpeg-muted mx-auto h-8 w-8" /><p className="simpeg-text mt-3 font-semibold">Belum ada batch publikasi</p><p className="simpeg-muted mt-1 text-sm">Setelah payroll diimpor, pengaturan pengirimannya akan muncul di sini.</p></div> : <>
                    <div className="hidden overflow-x-auto md:block"><table className="w-full min-w-[1080px] text-left text-sm"><thead className="simpeg-table-head border-b"><tr><th className="px-5 py-4 font-bold">PERIODE</th><th className="px-5 py-4 font-bold">STATUS</th><th className="px-5 py-4 font-bold">JADWAL / DIKIRIM</th><th className="px-5 py-4 font-bold">PENERIMA</th><th className="w-[580px] px-5 py-4 text-right font-bold">AKSI</th></tr></thead><tbody>{publications.map((publication) => <tr key={publication.id || `${publication.period}-${publication.source_filename}`} className="simpeg-table-row border-b last:border-0"><td className="px-5 py-4"><p className="simpeg-text font-semibold">{publication.period}</p><p className="simpeg-muted mt-1 max-w-[260px] truncate text-xs">{publication.source_filename}</p></td><td className="px-5 py-4"><span className={`rounded-full px-2.5 py-1 text-xs font-bold ${statusStyles[publication.status] || statusStyles.legacy}`}>{publication.status_label}</span></td><td className="simpeg-muted px-5 py-4 text-xs">{publication.scheduled_at || '—'}<br />{publication.sent_at ? `Dikirim ${publication.sent_at}` : ''}</td><td className="px-5 py-4"><RecipientIndicator publication={publication} onViewMissing={openMissing} /><p className="simpeg-muted mt-1 text-xs">{publication.sent_count ?? '—'} notifikasi baru</p></td><td className="min-w-[580px] align-top px-5 py-5"><PublicationActions publication={publication} canManage={canManage} onDelete={openDelete} /></td></tr>)}</tbody></table></div>
                    <div className="grid gap-3 p-4 md:hidden">{publications.map((publication) => <PublicationCard key={publication.id || `${publication.period}-${publication.source_filename}`} publication={publication} canManage={canManage} onDelete={openDelete} onViewMissing={openMissing} />)}</div>
                </>}
            </section>
            {missingTarget && <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" role="presentation"><div className="simpeg-card w-full max-w-2xl rounded-2xl border p-6 shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="missing-recipient-title"><div className="flex items-start justify-between gap-4"><div><p className="simpeg-muted text-xs font-bold uppercase tracking-[0.16em]">Periode {missingTarget.period}</p><h2 id="missing-recipient-title" className="simpeg-text mt-1 text-xl font-extrabold">Pegawai yang belum menerima notifikasi</h2><p className="simpeg-muted mt-2 text-sm">{missingTarget.missing_recipient_count} pegawai belum tercatat menerima slip pada batch ini.</p></div><button type="button" onClick={() => setMissingTarget(null)} aria-label="Tutup" className="simpeg-utility inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border"><X aria-hidden="true" className="h-5 w-5" /></button></div><div className="mt-5 max-h-[min(55vh,420px)] overflow-y-auto rounded-xl border"><div className="divide-y">{(missingTarget.missing_recipients || []).map((recipient) => <div key={recipient.employee_id} className="flex flex-wrap items-start justify-between gap-3 px-4 py-3"><div><p className="simpeg-text font-semibold">{recipient.employee_name}</p><p className="simpeg-muted mt-1 text-xs">{recipient.employee_id}</p></div><p className="max-w-[280px] text-right text-xs font-medium text-amber-700 dark:text-amber-300">{recipient.reason}</p></div>)}</div></div><div className="mt-5 flex justify-end"><button type="button" onClick={() => setMissingTarget(null)} className="simpeg-primary inline-flex min-h-11 items-center justify-center rounded-xl px-4 py-2.5 text-sm font-bold">Tutup</button></div></div></div>}
            {deleteTarget && <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" role="presentation"><div className="simpeg-card w-full max-w-lg rounded-2xl border p-6 shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="delete-publication-title"><div className="flex items-start gap-3"><span className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-red-100 text-red-700 dark:bg-red-950/50 dark:text-red-300"><Trash2 aria-hidden="true" className="h-5 w-5" /></span><div><h2 id="delete-publication-title" className="simpeg-text text-lg font-extrabold">Hapus riwayat publikasi?</h2><p className="simpeg-muted mt-2 text-sm leading-6">Riwayat publikasi periode <strong className="simpeg-text">{deleteTarget.period}</strong> dan slip gaji terkait tidak akan tampil lagi kepada pegawai. Master Gaji tidak dihapus.</p></div></div><form onSubmit={submitDelete} className="mt-5"><label htmlFor="delete-publication-reason" className="simpeg-text mb-2 block text-sm font-semibold">Alasan penghapusan</label><textarea id="delete-publication-reason" value={deleteForm.data.reason} onChange={(event) => deleteForm.setData('reason', event.target.value)} rows="3" required minLength="3" maxLength="1000" className="simpeg-input w-full rounded-xl border p-3 text-sm outline-none focus:ring-4 focus:ring-red-600/10" placeholder="Contoh: payroll salah periode atau data perlu diperbaiki" />{deleteForm.errors.reason && <p className="mt-2 text-sm font-medium text-red-600" role="alert">{deleteForm.errors.reason}</p>}<div className="mt-5 flex flex-wrap justify-end gap-3"><button type="button" onClick={() => setDeleteTarget(null)} className="simpeg-utility inline-flex min-h-11 items-center justify-center rounded-xl border px-4 py-2.5 text-sm font-bold">Batal</button><button type="submit" disabled={deleteForm.processing || deleteForm.data.reason.trim().length < 3} className="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-red-700 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-red-800 focus:outline-none focus:ring-4 focus:ring-red-700/30 disabled:cursor-not-allowed disabled:opacity-50"><Trash2 aria-hidden="true" className="h-4 w-4" />{deleteForm.processing ? 'Menghapus...' : 'Ya, hapus riwayat'}</button></div></form></div></div>}
            <div className="simpeg-muted mt-4 flex items-start gap-2 text-xs"><Clock3 aria-hidden="true" className="mt-0.5 h-4 w-4 shrink-0" /> Pengiriman langsung tetap tersedia. Pengiriman terjadwal akan berjalan otomatis sesuai waktu yang ditentukan.</div>
        </LegacyPageShell>
    );
}
