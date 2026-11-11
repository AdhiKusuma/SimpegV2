import { Head, Link, router } from '@inertiajs/react';
import LegacyPageShell from '../../Components/LegacyPageShell';

export default function OfficeIndex({ offices, filters }) {
    function submit(event) {
        event.preventDefault();
        const form = new FormData(event.currentTarget);
        router.get('/kantor', Object.fromEntries(form.entries()), { preserveState: true, replace: true });
    }

    return (
        <LegacyPageShell title="Data Kantor">
            <Head title="Data Kantor" />
            <div className="mb-6">
                <p className="mb-2 text-sm font-semibold uppercase tracking-[0.18em] text-blue-600">Referensi Organisasi</p>
                <h1 className="text-3xl font-extrabold tracking-tight text-slate-900">Data kantor</h1>
                <p className="mt-2 text-slate-500">Daftar kantor induk dan jumlah pegawai di dalamnya.</p>
            </div>

            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <form onSubmit={submit} className="simpeg-filter-toolbar simpeg-filter-toolbar--mobile-search flex flex-col gap-3 border-b border-slate-100 p-4 sm:flex-row">
                    <label htmlFor="office-search" className="sr-only">Cari kantor</label>
                    <input id="office-search" name="search" defaultValue={filters.search} placeholder="Cari kode, nama, alamat, atau status kantor" className="h-11 min-w-0 flex-1 rounded-xl border border-slate-200 px-4 text-sm outline-none focus:border-blue-600 focus:ring-4 focus:ring-blue-600/10" />
                    <button type="submit" className="h-11 rounded-xl bg-blue-600 px-5 text-sm font-bold text-white transition hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-600/20">Cari</button>
                </form>

                <div className="overflow-x-auto">
                    <table className="min-w-full text-left">
                        <thead className="border-b border-slate-100 text-xs uppercase tracking-wide text-slate-500">
                            <tr><th className="px-5 py-4">Kode kantor</th><th className="px-5 py-4">Nama kantor</th><th className="px-5 py-4">Alamat</th><th className="px-5 py-4">Pegawai aktif</th><th className="px-5 py-4">Status</th><th className="px-5 py-4 text-right">Aksi</th></tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {offices.data.length === 0 ? <tr><td colSpan="6" className="px-5 py-12 text-center text-sm text-slate-500">Data kantor tidak ditemukan.</td></tr> : offices.data.map((office) => <tr key={office.kode_kantor_detail} className="align-top transition hover:bg-slate-50">
                                <td className="px-5 py-4"><Link href={`/kantor/${office.kode_kantor_detail}`} className="font-mono font-bold text-blue-700 hover:underline">{office.kode_cabang || office.kode_kantor_detail}</Link><span className="mt-1 block text-xs text-slate-400">{office.kode_kantor_detail}</span></td>
                                <td className="px-5 py-4"><span className="block font-bold text-slate-800">{office.nama_kantor || '-'}</span><span className="mt-1 block text-xs uppercase text-slate-400">Level {office.level || '-'}</span></td>
                                <td className="max-w-sm px-5 py-4 text-sm text-slate-600">{office.alamat || '-'}</td>
                                <td className="px-5 py-4 text-sm font-bold text-slate-700">{office.employee_count}</td>
                                <td className="px-5 py-4 text-sm text-slate-600">{office.status_kantor || '-'}</td>
                                <td className="px-5 py-4 text-right"><Link href={`/kantor/${office.kode_kantor_detail}`} className="rounded-lg border border-blue-200 px-3 py-2 text-sm font-bold text-blue-600 hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-600/20">Detail</Link></td>
                            </tr>)}
                        </tbody>
                    </table>
                </div>
                <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 p-4 text-sm text-slate-500"><span>Menampilkan {offices.from || 0}–{offices.to || 0} dari {offices.total} kantor</span><div className="flex gap-2">{offices.links.filter((link) => link.url).map((link) => <Link key={link.label} href={link.url} preserveState className={`rounded-lg border px-3 py-2 ${link.active ? 'border-blue-600 bg-blue-600 text-white' : 'border-slate-200 hover:border-blue-600 hover:text-blue-600'}`} dangerouslySetInnerHTML={{ __html: link.label }} />)}</div></div>
            </section>
        </LegacyPageShell>
    );
}
