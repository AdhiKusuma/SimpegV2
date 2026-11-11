import { Head, Link, router, usePage } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useState } from 'react';
import LegacyPageShell from '../../Components/LegacyPageShell';

function notificationStatusClass(status) {
    return status === 'unread'
        ? 'border-amber-200 bg-amber-50 text-amber-800'
        : 'border-slate-200 bg-slate-50 text-slate-600';
}

export default function NotificationIndex({ notifications }) {
    const { flash = {} } = usePage().props;
    const [clearing, setClearing] = useState(false);

    function clearReadNotifications() {
        if (!window.confirm('Hapus semua notifikasi yang sudah dibaca? Notifikasi belum dibaca akan tetap disimpan.')) {
            return;
        }

        setClearing(true);
        router.delete('/notifikasi/read', {
            preserveScroll: true,
            onFinish: () => setClearing(false),
        });
    }

    return (
        <LegacyPageShell title="Notifikasi">
            <Head title="Notifikasi" />
            <div className="mb-6 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p className="mb-2 text-sm font-semibold uppercase tracking-[0.18em] text-blue-600">Riwayat sistem</p>
                    <h1 className="text-3xl font-extrabold tracking-tight text-slate-900">Notifikasi</h1>
                    <p className="mt-2 text-slate-500">Riwayat status permintaan dan pemberitahuan yang terkait dengan akun Anda.</p>
                </div>
                {notifications.total > 0 && <button type="button" onClick={clearReadNotifications} disabled={clearing} className="inline-flex min-h-11 items-center gap-2 rounded-xl border border-rose-200 px-4 py-2.5 text-sm font-bold text-rose-700 transition hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-60">
                    <Trash2 className="h-4 w-4" strokeWidth={1.8} aria-hidden="true" />
                    {clearing ? 'Menghapus...' : 'Hapus yang sudah dibaca'}
                </button>}
            </div>

            {flash.success && <div className="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800" role="status">{flash.success}</div>}

            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-label="Daftar notifikasi">
                {notifications.data.length === 0 ? <p className="px-5 py-12 text-center text-sm text-slate-500">Belum ada notifikasi.</p> : <div className="divide-y divide-slate-100">{notifications.data.map((notification) => <article key={notification.id_notif} className="grid gap-4 px-5 py-5 sm:grid-cols-[1fr_auto] sm:items-start"><div><div className="flex flex-wrap items-center gap-2"><h2 className="font-bold text-slate-900">{notification.judul || 'Pemberitahuan'}</h2><span className={notificationStatusClass(notification.status_baca)}>{notification.status_baca === 'unread' ? 'Belum dibaca' : 'Dibaca'}</span></div><p className="mt-2 text-sm leading-6 text-slate-600">{notification.pesan || '-'}</p><p className="mt-2 text-xs text-slate-500">{notification.waktu || '-'}</p></div>{notification.action_url && <Link href={notification.read_url || notification.action_url} method={notification.read_url ? "post" : "get"} as="a" className="inline-flex min-h-11 items-center justify-center rounded-lg border border-blue-200 px-3 py-2 text-sm font-bold text-blue-700 transition hover:bg-blue-50 focus:outline-none focus:ring-4 focus:ring-blue-600/20">Lihat detail</Link>}</article>)}</div>}
                <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 p-4 text-sm text-slate-500"><span>Menampilkan {notifications.from || 0}-{notifications.to || 0} dari {notifications.total} notifikasi</span><div className="flex flex-wrap gap-2" aria-label="Paginasi notifikasi">{notifications.links.filter((link) => link.url).map((link) => <Link key={link.label} href={link.url} preserveState className={`inline-flex min-h-11 items-center rounded-lg border px-3 py-2 ${link.active ? 'border-blue-600 bg-blue-600 text-white' : 'border-slate-200 hover:border-blue-600 hover:text-blue-600'}`} dangerouslySetInnerHTML={{ __html: link.label }} />)}</div></div>
            </section>
        </LegacyPageShell>
    );
}
