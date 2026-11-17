import { Head, Link, router } from '@inertiajs/react';
import LegacyPageShell from '../../Components/LegacyPageShell';
import MobileFilterGroup from '../../Components/MobileFilterGroup';

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
                <form onSubmit={submit} className="simpeg-filter-toolbar simpeg-filter-toolbar--mobile-panel grid gap-3 border-b border-slate-100 p-4">
                    <MobileFilterGroup id="office-mobile-filters" activeCount={Number(Boolean(filters.search))}>
                        <div className="min-w-0">
                            <label htmlFor="office-search" className="mb-2 block text-xs font-bold uppercase tracking-wide text-slate-500">Cari kantor</label>
                            <input id="office-search" name="search" defaultValue={filters.search} placeholder="Cari kode, nama, alamat, atau status kantor" className="h-11 w-full rounded-xl border border-slate-200 px-4 text-sm outline-none focus:border-blue-600 focus:ring-4 focus:ring-blue-600/10" />
                        </div>
                        <button type="submit" className="simpeg-primary simpeg-mobile-filter-apply">Terapkan</button>
                    </MobileFilterGroup>
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
