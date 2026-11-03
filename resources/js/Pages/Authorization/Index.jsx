import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowUpRight } from 'lucide-react';
import { useState } from 'react';
import LegacyPageShell from '../../Components/LegacyPageShell';
import MobileFilterGroup from '../../Components/MobileFilterGroup';
import SelectCombobox from '../../Components/SelectCombobox';

const statusStyles = {
    Menunggu: 'border-amber-200 bg-amber-50 text-amber-800',
    Disetujui: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    Ditolak: 'border-red-200 bg-red-50 text-red-800',
};

export default function AuthorizationIndex({ pending, filters }) {
    const { flash } = usePage().props;
    const [loading, setLoading] = useState(false);
    const activeFilterCount = Number(Boolean(filters.status && filters.status !== 'Menunggu'))
        + Number(Boolean(filters.date_by && filters.date_by !== 'pengajuan'))
        + Number(Boolean(filters.tgl_awal))
        + Number(Boolean(filters.tgl_akhir));

    function submit(event) {
        event.preventDefault();
        const form = new FormData(event.currentTarget);
        router.get('/otorisasi', Object.fromEntries(form.entries()), {
            preserveState: true,
            replace: true,
            onStart: () => setLoading(true),
            onFinish: () => setLoading(false),
        });
    }

    return (
        <LegacyPageShell title="Otorisasi Edit Data">
            <Head title="Otorisasi Edit Data" />
            <div className="mb-6 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p className="mb-2 text-sm font-semibold uppercase tracking-[0.18em] text-blue-600">Kontrol perubahan data</p>
                    <h1 className="text-3xl font-extrabold tracking-tight text-slate-900">Otorisasi edit data</h1>
                    <p className="mt-2 max-w-2xl text-slate-500">Periksa perubahan yang diajukan pegawai di kantor Anda sebelum menyetujui atau menolaknya.</p>
                </div>
                {loading && <p className="rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-sm font-semibold text-blue-800" role="status">Memuat data...</p>}
            </div>

            {flash?.success && <div className="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800" role="status">{flash.success}</div>}
            {flash?.error && <div className="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800" role="alert">{flash.error}</div>}

            <section className="mb-6 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <form onSubmit={submit} className="simpeg-filter-toolbar simpeg-filter-toolbar--mobile-panel grid gap-4 lg:grid-cols-[1fr_180px_150px_150px_auto] lg:items-end">
                    <MobileFilterGroup id="authorization-mobile-filters" activeCount={activeFilterCount}>
                        <SelectCombobox id="approval-status" name="status" label="Status otorisasi" value={filters.status} options={[{ value: 'Menunggu', label: 'Menunggu' }, { value: 'Semua', label: 'Semua' }, { value: 'Disetujui', label: 'Disetujui' }, { value: 'Ditolak', label: 'Ditolak' }]} />
                        <SelectCombobox id="approval-date-by" name="date_by" label="Tanggal berdasar" value={filters.date_by} options={[{ value: 'pengajuan', label: 'Pengajuan' }, { value: 'otorisasi', label: 'Otorisasi' }]} />
                        <div>
                            <label htmlFor="approval-start" className="mb-2 block text-xs font-bold uppercase tracking-wide text-slate-500">Dari</label>
                            <input id="approval-start" name="tgl_awal" type="date" defaultValue={filters.tgl_awal || ''} className="h-11 w-full rounded-xl border border-slate-200 px-3 text-sm outline-none focus:border-blue-600 focus:ring-4 focus:ring-blue-600/10" />
                        </div>
                        <div>
                            <label htmlFor="approval-end" className="mb-2 block text-xs font-bold uppercase tracking-wide text-slate-500">Sampai</label>
                            <input id="approval-end" name="tgl_akhir" type="date" defaultValue={filters.tgl_akhir || ''} className="h-11 w-full rounded-xl border border-slate-200 px-3 text-sm outline-none focus:border-blue-600 focus:ring-4 focus:ring-blue-600/10" />
                        </div>
                        <button type="submit" className="simpeg-primary simpeg-mobile-filter-apply">Terapkan</button>
                    </MobileFilterGroup>
                    <div className="simpeg-mobile-filter-actions--top flex gap-2">
                        <button type="submit" className="simpeg-desktop-filter-apply inline-flex min-h-11 flex-1 items-center justify-center rounded-xl bg-blue-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-600/20">Terapkan</button>
                        <Link href="/otorisasi" className="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 px-4 py-2 text-sm font-bold text-slate-700 transition hover:border-blue-600 hover:text-blue-600 focus:outline-none focus:ring-4 focus:ring-blue-600/20">Reset</Link>
                    </div>
                </form>
            </section>

            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-label="Daftar pengajuan otorisasi">
                <div className="overflow-x-auto">
                    <table className="min-w-[900px] w-full text-left">
                        <thead className="border-b border-slate-100 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th className="px-5 py-4">ID pegawai</th>
                                <th className="px-5 py-4">Nama pegawai</th>
                                <th className="px-5 py-4">Perubahan</th>
                                <th className="px-5 py-4">Status</th>
                                <th className="px-5 py-4">Diajukan oleh</th>
                                <th className="px-5 py-4">Kantor</th>
                                <th className="px-5 py-4">Tanggal</th>
                                <th className="px-5 py-4 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {pending.data.length === 0 ? (
                                <tr><td colSpan="8" className="px-5 py-12 text-center text-sm text-slate-500">Tidak ada pengajuan yang sesuai filter.</td></tr>
                            ) : pending.data.map((item) => (
                                <tr key={item.id_edit} className="align-top transition hover:bg-slate-50">
                                    <td className="px-5 py-4 font-mono text-xs text-slate-600">{item.id_peg || '-'}</td>
                                    <td className="px-5 py-4 font-bold text-slate-800">{item.nama_pegawai || 'Pegawai tidak ditemukan'}</td>
                                    <td className="px-5 py-4 text-sm text-slate-700">{item.jenis_data}</td>
                                    <td className="px-5 py-4"><span className={`inline-flex rounded-lg border px-2.5 py-1 text-xs font-bold ${statusStyles[item.status_otorisasi] || 'border-slate-200 bg-slate-50 text-slate-700'}`}>{item.status_otorisasi}</span></td>
                                    <td className="px-5 py-4 text-sm text-slate-700">{item.nama_user || '-'}</td>
                                    <td className="px-5 py-4 text-sm text-slate-700">{item.kantor_pemohon || '-'}</td>
                                    <td className="whitespace-nowrap px-5 py-4 text-sm text-slate-600">{item.waktu || '-'}</td>
                                    <td className="px-5 py-4 text-right"><Link href={`/otorisasi/${item.id_edit}/detail`} className="simpeg-utility inline-flex min-h-10 min-w-[8.5rem] items-center justify-center gap-2 whitespace-nowrap rounded-xl border px-3.5 py-2 text-sm font-bold transition hover:border-blue-600 hover:text-blue-600 focus:outline-none focus:ring-4 focus:ring-blue-600/20"><span>Lihat detail</span><ArrowUpRight aria-hidden="true" className="h-4 w-4" /></Link></td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 p-4 text-sm text-slate-500">
                    <span>Menampilkan {pending.from || 0}-{pending.to || 0} dari {pending.total} pengajuan</span>
                    <div className="flex flex-wrap gap-2" aria-label="Paginasi pengajuan">
                        {pending.links.filter((link) => link.url).map((link) => <Link key={link.label} href={link.url} preserveState className={`inline-flex min-h-11 items-center rounded-lg border px-3 py-2 ${link.active ? 'border-blue-600 bg-blue-600 text-white' : 'border-slate-200 hover:border-blue-600 hover:text-blue-600'}`} dangerouslySetInnerHTML={{ __html: link.label }} />)}
                    </div>
                </div>
            </section>
        </LegacyPageShell>
    );
}
