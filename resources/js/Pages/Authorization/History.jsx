import { Head, Link, router } from '@inertiajs/react';
import LegacyPageShell from '../../Components/LegacyPageShell';
import SelectCombobox from '../../Components/SelectCombobox';

const statusStyles = {
    Menunggu: 'border-amber-200 bg-amber-50 text-amber-800',
    Disetujui: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    Ditolak: 'border-red-200 bg-red-50 text-red-800',
};

const statusOptions = [
    { value: 'Semua', label: 'Semua status' },
    { value: 'Menunggu', label: 'Menunggu' },
    { value: 'Disetujui', label: 'Disetujui' },
    { value: 'Ditolak', label: 'Ditolak' },
];

export default function AuthorizationHistory({ requests, filters }) {
    function submit(event) {
        event.preventDefault();
        const form = new FormData(event.currentTarget);
        router.get('/pengajuan', Object.fromEntries(form.entries()), { preserveState: true, replace: true });
    }

    return (
        <LegacyPageShell title="Riwayat Pengajuan">
            <Head title="Riwayat Pengajuan" />
            <div className="mb-6">
                <p className="mb-2 text-sm font-semibold uppercase tracking-[0.18em] text-blue-600">Akun</p>
                <h1 className="text-3xl font-extrabold tracking-tight text-slate-900">Riwayat pengajuan</h1>
                <p className="mt-2 max-w-2xl text-slate-500">Pantau status perubahan data yang Anda ajukan dan hasil pemeriksaannya.</p>
            </div>

            <section className="mb-6 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <form onSubmit={submit} className="simpeg-filter-toolbar flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div className="w-full sm:max-w-xs">
                        <SelectCombobox id="submission-status" name="status" label="Status pengajuan" value={filters.status} options={statusOptions} />
                    </div>
                    <button type="submit" className="inline-flex min-h-11 items-center justify-center rounded-xl bg-blue-600 px-5 py-2 text-sm font-bold text-white transition hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-600/20">Terapkan</button>
                    <Link href="/pengajuan" className="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 px-5 py-2 text-sm font-bold text-slate-700 transition hover:border-blue-600 hover:text-blue-600 focus:outline-none focus:ring-4 focus:ring-blue-600/20">Reset</Link>
                </form>
            </section>

            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-label="Riwayat pengajuan saya">
                <div className="overflow-x-auto">
                    <table className="min-w-[900px] w-full text-left">
                        <thead className="border-b border-slate-100 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th className="px-5 py-4">ID</th>
                                <th className="px-5 py-4">Jenis perubahan</th>
                                <th className="px-5 py-4">Data terkait</th>
                                <th className="px-5 py-4">Status</th>
                                <th className="px-5 py-4">Diajukan</th>
                                <th className="px-5 py-4">Diproses</th>
                                <th className="px-5 py-4">Catatan pemeriksa</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {requests.data.length === 0 ? (
                                <tr><td colSpan="7" className="px-5 py-12 text-center text-sm text-slate-500">Belum ada pengajuan yang sesuai.</td></tr>
                            ) : requests.data.map((item) => (
                                <tr key={item.id_edit} className="align-top transition hover:bg-slate-50">
                                    <td className="px-5 py-4 font-mono text-xs text-slate-600">{item.id_edit}</td>
                                    <td className="px-5 py-4 text-sm font-semibold text-slate-800">{item.jenis_data}</td>
                                    <td className="px-5 py-4"><span className="block text-sm font-semibold text-slate-800">{item.nama_pegawai || '-'}</span><span className="mt-1 block font-mono text-xs text-slate-500">{item.id_peg || '-'}</span><span className="mt-1 block text-xs text-slate-500">{item.subject || '-'}</span></td>
                                    <td className="px-5 py-4"><span className={'inline-flex rounded-lg border px-2.5 py-1 text-xs font-bold ' + (statusStyles[item.status_otorisasi] || 'border-slate-200 bg-slate-50 text-slate-700')}>{item.status_otorisasi}</span></td>
                                    <td className="whitespace-nowrap px-5 py-4 text-sm text-slate-600">{item.tanggal_pengajuan || '-'}</td>
                                    <td className="whitespace-nowrap px-5 py-4 text-sm text-slate-600">{item.tanggal_otorisasi || '-'}</td>
                                    <td className="max-w-xs px-5 py-4 text-sm text-slate-600">{item.komentar_otorisasi || '-'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 p-4 text-sm text-slate-500">
                    <span>Menampilkan {requests.from || 0}-{requests.to || 0} dari {requests.total} pengajuan</span>
                    <div className="flex flex-wrap gap-2" aria-label="Paginasi riwayat pengajuan">
                        {requests.links.filter((link) => link.url).map((link) => <Link key={link.label} href={link.url} preserveState className={'inline-flex min-h-11 items-center rounded-lg border px-3 py-2 ' + (link.active ? 'border-blue-600 bg-blue-600 text-white' : 'border-slate-200 hover:border-blue-600 hover:text-blue-600')} dangerouslySetInnerHTML={{ __html: link.label }} />)}
                    </div>
                </div>
            </section>
        </LegacyPageShell>
    );
}
