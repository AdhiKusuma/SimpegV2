import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import LegacyPageShell from '../../Components/LegacyPageShell';

const statusStyles = {
    Menunggu: 'border-amber-200 bg-amber-50 text-amber-800',
    Disetujui: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    Ditolak: 'border-red-200 bg-red-50 text-red-800',
};

export default function AuthorizationDetail({ pending, canProcess }) {
    const { flash } = usePage().props;
    const form = useForm({ action: '', comment: '' });
    const [processingAction, setProcessingAction] = useState(null);
    const [requestError, setRequestError] = useState('');

    function process(action) {
        setProcessingAction(action);
        setRequestError('');
        form.clearErrors();
        form.transform((data) => ({ ...data, action }));
        form.post(`/otorisasi/${pending.id_edit}/process`, {
            preserveScroll: true,
            onError: (errors) => {
                if (Object.keys(errors).length === 0) {
                    setRequestError('Pengajuan belum dapat diproses. Muat ulang halaman lalu coba lagi.');
                }
            },
            onFinish: () => setProcessingAction(null),
        });
    }

    return (
        <LegacyPageShell title="Detail Otorisasi">
            <Head title="Detail Otorisasi" />
            <div className="mb-6 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p className="mb-2 text-sm font-semibold uppercase tracking-[0.18em] text-blue-600">Otorisasi edit data</p>
                    <h1 className="text-3xl font-extrabold tracking-tight text-slate-900">Detail pengajuan</h1>
                    <p className="mt-2 text-slate-500">Bandingkan nilai lama dan nilai yang diajukan sebelum mengambil keputusan.</p>
                </div>
                <Link href="/otorisasi" className="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 px-4 py-2 text-sm font-bold text-slate-700 transition hover:border-blue-600 hover:text-blue-600 focus:outline-none focus:ring-4 focus:ring-blue-600/20">Kembali ke daftar</Link>
            </div>

            {flash?.success && <div className="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800" role="status">{flash.success}</div>}
            {flash?.error && <div className="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800" role="alert">{flash.error}</div>}
            {requestError && <div className="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800" role="alert">{requestError}</div>}
            {form.errors.action && <div className="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800" role="alert">{form.errors.action}</div>}

            <section className="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    <div><p className="text-xs font-bold uppercase tracking-wide text-slate-500">ID pengajuan</p><p className="mt-1 font-mono text-sm text-slate-800">{pending.id_edit}</p></div>
                    <div><p className="text-xs font-bold uppercase tracking-wide text-slate-500">Pegawai</p><p className="mt-1 font-bold text-slate-800">{pending.nama_pegawai || '-'}</p><p className="font-mono text-xs text-slate-500">{pending.id_peg || '-'}</p></div>
                    <div><p className="text-xs font-bold uppercase tracking-wide text-slate-500">Jenis perubahan</p><p className="mt-1 text-sm text-slate-800">{pending.jenis_data}</p></div>
                    <div><p className="text-xs font-bold uppercase tracking-wide text-slate-500">Status</p><span className={`mt-1 inline-flex rounded-lg border px-2.5 py-1 text-xs font-bold ${statusStyles[pending.status_otorisasi] || 'border-slate-200 bg-slate-50 text-slate-700'}`}>{pending.status_otorisasi}</span></div>
                    <div><p className="text-xs font-bold uppercase tracking-wide text-slate-500">Diajukan oleh</p><p className="mt-1 text-sm text-slate-800">{pending.nama_user || '-'}</p></div>
                    <div><p className="text-xs font-bold uppercase tracking-wide text-slate-500">Kantor pemohon</p><p className="mt-1 text-sm text-slate-800">{pending.kantor_pemohon || '-'}</p></div>
                    <div><p className="text-xs font-bold uppercase tracking-wide text-slate-500">Tanggal pengajuan</p><p className="mt-1 text-sm text-slate-800">{pending.tanggal_pengajuan || '-'}</p></div>
                    <div><p className="text-xs font-bold uppercase tracking-wide text-slate-500">Tanggal otorisasi</p><p className="mt-1 text-sm text-slate-800">{pending.tanggal_otorisasi || '-'}</p></div>
                </div>
            </section>

            {pending.komentar_pemohon && <section className="mb-6 rounded-2xl border border-amber-200 bg-amber-50 p-5 shadow-sm"><h2 className="font-extrabold text-slate-900">Catatan pemohon</h2><p className="mt-2 whitespace-pre-wrap text-sm text-slate-700">{pending.komentar_pemohon}</p></section>}

            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div className="border-b border-slate-100 px-5 py-4"><h2 className="font-extrabold text-slate-900">Perubahan yang diajukan</h2></div>
                {pending.changes.length === 0 ? <p className="px-5 py-10 text-center text-sm text-slate-500">Tidak ada rincian perubahan yang dapat ditampilkan.</p> : <div className="divide-y divide-slate-100">{pending.changes.map((change) => <div key={change.field} className="grid gap-2 px-5 py-4 sm:grid-cols-[minmax(10rem,0.7fr)_1fr_1fr] sm:gap-5"><div className="text-sm font-bold text-slate-700">{change.field}</div><div><p className="text-xs font-bold uppercase tracking-wide text-slate-500">Data lama</p><p className="mt-1 break-words text-sm text-slate-600">{change.old}</p></div><div><p className="text-xs font-bold uppercase tracking-wide text-slate-500">Nilai baru</p><p className="mt-1 break-words text-sm font-semibold text-slate-900">{change.new}</p></div></div>)}</div>}
            </section>

            {pending.komentar_otorisasi && <section className="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h2 className="font-extrabold text-slate-900">Komentar otorisasi</h2><p className="mt-2 whitespace-pre-wrap text-sm text-slate-600">{pending.komentar_otorisasi}</p></section>}

            {canProcess && <section className="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h2 className="font-extrabold text-slate-900">Keputusan</h2><label htmlFor="authorization-comment" className="mt-4 block text-sm font-semibold text-slate-800">Komentar (opsional)</label><textarea id="authorization-comment" rows="4" value={form.data.comment} onChange={(event) => form.setData('comment', event.target.value)} className="mt-2 w-full rounded-xl border border-slate-200 p-3 text-sm outline-none focus:border-blue-600 focus:ring-4 focus:ring-blue-600/10" placeholder="Tambahkan catatan untuk pemohon" />{form.errors.comment && <p className="mt-2 text-sm text-red-600" role="alert">{form.errors.comment}</p>}<div className="mt-5 flex flex-wrap gap-3"><button type="button" onClick={() => process('approve')} disabled={form.processing} className="inline-flex min-h-11 items-center justify-center rounded-xl bg-emerald-700 px-4 py-2 text-sm font-bold text-white transition hover:bg-emerald-800 focus:outline-none focus:ring-4 focus:ring-emerald-700/20 disabled:cursor-not-allowed disabled:opacity-60">{form.processing && processingAction === 'approve' ? 'Menyetujui...' : 'Setujui perubahan'}</button><button type="button" onClick={() => process('reject')} disabled={form.processing} className="inline-flex min-h-11 items-center justify-center rounded-xl border border-red-300 px-4 py-2 text-sm font-bold text-red-700 transition hover:bg-red-50 focus:outline-none focus:ring-4 focus:ring-red-600/20 disabled:cursor-not-allowed disabled:opacity-60">{form.processing && processingAction === 'reject' ? 'Menolak...' : 'Tolak perubahan'}</button></div></section>}
        </LegacyPageShell>
    );
}
