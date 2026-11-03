import { Head, Link } from '@inertiajs/react';
import LegacyPageShell from '../../Components/LegacyPageShell';

export default function OfficeShow({ office, employees }) {
    return (
        <LegacyPageShell title={`Kantor ${office.nama_kantor}`}>
            <Head title={`Kantor ${office.nama_kantor}`} />
            <div className="mb-6 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p className="mb-2 text-sm font-semibold uppercase tracking-[0.18em] text-blue-600">Detail Kantor</p>
                    <h1 className="text-3xl font-extrabold tracking-tight text-slate-900">{office.nama_kantor || office.kode_kantor_detail}</h1>
                    <p className="mt-2 font-mono text-sm text-slate-500">{office.kode_kantor_detail}</p>
                </div>
                <Link href="/kantor" className="rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-600 hover:border-blue-300 hover:text-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-600/20">Kembali</Link>
            </div>

            <div className="grid gap-4 lg:grid-cols-3">
                <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:col-span-2">
                    <h2 className="text-sm font-extrabold uppercase tracking-wide text-slate-500">Informasi kantor</h2>
                    <dl className="mt-4 grid gap-4 sm:grid-cols-2">
                        <div><dt className="text-xs font-bold uppercase text-slate-400">Kode cabang</dt><dd className="mt-1 text-sm text-slate-700">{office.kode_cabang || '-'}</dd></div>
                        <div><dt className="text-xs font-bold uppercase text-slate-400">Level</dt><dd className="mt-1 text-sm text-slate-700">{office.level || '-'}</dd></div>
                        <div className="sm:col-span-2"><dt className="text-xs font-bold uppercase text-slate-400">Alamat</dt><dd className="mt-1 text-sm leading-6 text-slate-700">{office.alamat || '-'}</dd></div>
                        <div><dt className="text-xs font-bold uppercase text-slate-400">Status kantor</dt><dd className="mt-1 text-sm text-slate-700">{office.status_kantor || '-'}</dd></div>
                        <div><dt className="text-xs font-bold uppercase text-slate-400">Dokumen</dt><dd className="mt-1 text-sm text-slate-700">{office.bentuk_dokumen || '-'}{office.no_dokumen ? ` — ${office.no_dokumen}` : ''}</dd></div>
                        <div><dt className="text-xs font-bold uppercase text-slate-400">Masa berlaku</dt><dd className="mt-1 text-sm text-slate-700">{office.tgl_mulai || '-'} s.d. {office.tgl_selesai || '-'}</dd></div>
                        <div><dt className="text-xs font-bold uppercase text-slate-400">Jarak / waktu ke KC</dt><dd className="mt-1 text-sm text-slate-700">{office.jarak_kc || '-'} / {office.waktu_kc || '-'}</dd></div>
                        <div className="sm:col-span-2"><dt className="text-xs font-bold uppercase text-slate-400">Keterangan</dt><dd className="mt-1 text-sm leading-6 text-slate-700">{office.keterangan || '-'}</dd></div>
                    </dl>
                </section>
                <section className="rounded-2xl border border-blue-100 bg-blue-50 p-5 shadow-sm">
                    <p className="text-xs font-bold uppercase tracking-wide text-blue-700">Pegawai aktif</p>
                    <p className="mt-3 text-4xl font-extrabold text-blue-900">{office.employee_count}</p>
                    <p className="mt-2 text-sm text-blue-800">pegawai dengan jabatan aktif pada unit ini.</p>
                </section>
            </div>

            <section className="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div className="border-b border-slate-100 p-5"><h2 className="font-extrabold text-slate-900">Daftar pegawai aktif</h2></div>
                <div className="overflow-x-auto">
                    <table className="min-w-full text-left">
                        <thead className="border-b border-slate-100 text-xs uppercase tracking-wide text-slate-500"><tr><th className="px-5 py-4">Pegawai</th><th className="px-5 py-4">Jabatan</th><th className="px-5 py-4">Status</th><th className="px-5 py-4">TMT jabatan</th></tr></thead>
                        <tbody className="divide-y divide-slate-100">
                            {employees.data.length === 0 ? <tr><td colSpan="4" className="px-5 py-12 text-center text-sm text-slate-500">Belum ada pegawai aktif pada kantor ini.</td></tr> : employees.data.map((employee) => <tr key={employee.id_peg} className="transition hover:bg-slate-50">
                                <td className="px-5 py-4"><Link href={`/pegawai/${employee.id_peg}`} className="font-bold text-blue-700 hover:underline">{employee.nama || '-'}</Link><span className="mt-1 block font-mono text-xs text-slate-500">{employee.id_peg}</span></td>
                                <td className="px-5 py-4 text-sm text-slate-700">{employee.jabatan || '-'}</td>
                                <td className="px-5 py-4 text-sm text-slate-600">{employee.status_kepeg || '-'}</td>
                                <td className="px-5 py-4 text-sm text-slate-600">{employee.tmt_jabatan || '-'}</td>
                            </tr>)}
                        </tbody>
                    </table>
                </div>
                <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 p-4 text-sm text-slate-500"><span>Menampilkan {employees.from || 0}–{employees.to || 0} dari {employees.total} pegawai</span><div className="flex gap-2">{employees.links.filter((link) => link.url).map((link) => <Link key={link.label} href={link.url} preserveState className={`rounded-lg border px-3 py-2 ${link.active ? 'border-blue-600 bg-blue-600 text-white' : 'border-slate-200 hover:border-blue-600 hover:text-blue-600'}`} dangerouslySetInnerHTML={{ __html: link.label }} />)}</div></div>
            </section>
        </LegacyPageShell>
    );
}
