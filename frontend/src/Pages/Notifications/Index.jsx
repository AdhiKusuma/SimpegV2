import { Head, Link } from '@inertiajs/react';
import LegacyPageShell from '../../Components/LegacyPageShell';

export default function NotificationIndex({ notifications }) {
    return (
        <LegacyPageShell title="Notifikasi">
            <Head title="Notifikasi" />
            <div className="mb-6">
                <p className="mb-2 text-sm font-semibold uppercase tracking-[0.18em] text-blue-600">Riwayat sistem</p>
                <h1 className="text-3xl font-extrabold tracking-tight text-slate-900">Notifikasi</h1>
                <p className="mt-2 text-slate-500">Riwayat status permintaan dan pemberitahuan yang terkait dengan akun Anda.</p>
            </div>

            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-label="Daftar notifikasi">
                {notifications.data.length === 0 ? <p className="px-5 py-12 text-center text-sm text-slate-500">Belum ada notifikasi.</p> : <div className="divide-y divide-slate-100">{notifications.data.map((notification) => <article key={notification.id_notif} className="grid gap-4 px-5 py-5 sm:grid-cols-[1fr_auto] sm:items-start"><div><div className="flex flex-wrap items-center gap-2"><h2 className="font-bold text-slate-900">{notification.judul || 'Pemberitahuan'}</h2><span className={`rounded-lg border px-2 py-1 text-xs font-bold ${notification.status_baca === 'unread' ? 'border-amber-200 bg-amber-50 text-amber-800' : 'border-slate-200 bg-slate-50 text-slate-600'}`}>{notification.status_baca === 'unread' ? 'Belum dibaca' : 'Dibaca'}</span></div><p className="mt-2 text-sm leading-6 text-slate-600">{notification.pesan || '-'}</p><p className="mt-2 text-xs text-slate-500">{notification.waktu || '-'}</p></div>{notification.action_url && <Link href={notification.action_url} className="inline-flex min-h-11 items-center justify-center rounded-lg border border-blue-200 px-3 py-2 text-sm font-bold text-blue-700 transition hover:bg-blue-50 focus:outline-none focus:ring-4 focus:ring-blue-600/20">Lihat detail</Link>}</article>)}</div>}
                <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 p-4 text-sm text-slate-500"><span>Menampilkan {notifications.from || 0}-{notifications.to || 0} dari {notifications.total} notifikasi</span><div className="flex flex-wrap gap-2" aria-label="Paginasi notifikasi">{notifications.links.filter((link) => link.url).map((link) => <Link key={link.label} href={link.url} preserveState className={`inline-flex min-h-11 items-center rounded-lg border px-3 py-2 ${link.active ? 'border-blue-600 bg-blue-600 text-white' : 'border-slate-200 hover:border-blue-600 hover:text-blue-600'}`} dangerouslySetInnerHTML={{ __html: link.label }} />)}</div></div>
            </section>
        </LegacyPageShell>
    );
}
