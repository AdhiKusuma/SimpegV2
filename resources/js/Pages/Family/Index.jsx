import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import LegacyPageShell from '../../Components/LegacyPageShell';

const familyTypes = [
    ['children', 'Data Anak'],
    ['spouses', 'Suami/Istri'],
    ['parents', 'Orang Tua'],
];

const familyUrlTypes = { children: 'anak', spouses: 'suami-istri', parents: 'orang-tua' };

const statusStyles = {
    Menunggu: 'border-amber-200 bg-amber-50 text-amber-800',
    Disetujui: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    Ditolak: 'border-red-200 bg-red-50 text-red-800',
};

export default function FamilyIndex({ type, label, items, employee, filters, canManage, canRequest, canDelete, pendingRequests = [] }) {
    const { flash } = usePage().props;
    const [deleteTarget, setDeleteTarget] = useState(null);
    const deleteForm = useForm({ reason: '' });
    const canEdit = canManage || canRequest;
    const familyPath = familyUrlTypes[type] || type;

    const withUid = (path) => filters.uid ? `${path}?uid=${encodeURIComponent(filters.uid)}` : path;
    const withFilters = (path) => {
        const params = new URLSearchParams();
        if (filters.uid) params.set('uid', filters.uid);
        if (filters.search) params.set('search', filters.search);
        const query = params.toString();
        return query ? `${path}?${query}` : path;
    };

    function confirmDelete(event) {
        event.preventDefault();
        deleteForm.delete(`/keluarga/${familyPath}/${deleteTarget.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                setDeleteTarget(null);
                deleteForm.reset();
            },
        });
    }

    return (
        <LegacyPageShell title={label}>
            <Head title={label} />
            <div className="mb-6 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p className="mb-2 text-sm font-semibold uppercase tracking-[0.18em] text-blue-600">Referensi Keluarga</p>
                    <h1 className="text-3xl font-extrabold tracking-tight text-slate-900">{label}</h1>
                    <p className="mt-2 text-slate-500">Kelola data keluarga sesuai pegawai yang dipilih.</p>
                    {employee && <p className="mt-3 inline-flex rounded-lg bg-indigo-50 px-3 py-2 text-sm font-semibold text-indigo-700">Pegawai: {employee.nama} ({employee.id_peg})</p>}
                </div>
                <div className="flex flex-wrap gap-2">
                    <a href={withFilters(`/keluarga/${familyPath}/export`)} className="rounded-xl border border-emerald-200 px-4 py-2.5 text-sm font-bold text-emerald-700 transition hover:bg-emerald-50 focus:outline-none focus:ring-4 focus:ring-emerald-600/20">Export XLSX</a>
                    {canManage && <Link href={withUid(`/keluarga/${familyPath}/import`)} className="rounded-xl border border-blue-200 px-4 py-2.5 text-sm font-bold text-blue-700 transition hover:bg-blue-50 focus:outline-none focus:ring-4 focus:ring-blue-600/20">Import XLSX</Link>}
                    {canEdit && <Link href={withUid(`/keluarga/${familyPath}/create`)} className="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-600/20">{canRequest ? 'Ajukan data' : '+ Tambah Data'}</Link>}
                </div>
            </div>

            <div className="mb-5 flex flex-wrap gap-2" aria-label="Jenis data keluarga">
                {familyTypes.map(([value, text]) => <Link key={value} href={withUid(`/keluarga/${familyUrlTypes[value] || value}`)} className={`rounded-full px-4 py-2 text-sm font-semibold transition focus:outline-none focus:ring-2 focus:ring-blue-600/30 ${type === value ? 'bg-blue-600 text-white' : 'bg-white text-slate-600 shadow-sm ring-1 ring-slate-200 hover:bg-slate-50'}`}>{text}</Link>)}
            </div>

            {flash?.success && <div className="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800" role="status">{flash.success}</div>}

            {canRequest && pendingRequests.length > 0 && <section className="mb-5 rounded-2xl border border-amber-200 bg-amber-50/70 p-4 sm:p-5" aria-labelledby="family-pending-title">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 id="family-pending-title" className="text-base font-extrabold text-slate-900">Pengajuan menunggu persetujuan</h2>
                        <p className="mt-1 text-sm text-slate-600">Perubahan belum masuk ke data aktif sampai disetujui kepala cabang.</p>
                    </div>
                    <span className="rounded-full bg-white px-3 py-1 text-xs font-bold text-amber-800 ring-1 ring-amber-200">{pendingRequests.length} pengajuan</span>
                </div>
                <div className="mt-4 overflow-x-auto rounded-xl bg-white ring-1 ring-amber-100">
                    <table className="min-w-full text-left text-sm">
                        <thead className="border-b border-slate-100 text-xs uppercase tracking-wide text-slate-500">
                            <tr><th className="px-4 py-3">Pengajuan</th><th className="px-4 py-3">Nama</th><th className="px-4 py-3">Diajukan</th><th className="px-4 py-3">Status</th></tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {pendingRequests.map((request) => <tr key={request.id_edit}>
                                <td className="px-4 py-3 font-semibold text-slate-800">{request.action} {label.toLowerCase()}</td>
                                <td className="px-4 py-3 text-slate-600">{request.nama || '-'}</td>
                                <td className="whitespace-nowrap px-4 py-3 text-slate-500">{request.submitted_at || '-'}</td>
                                <td className="px-4 py-3"><span className={'inline-flex rounded-full border px-2.5 py-1 text-xs font-bold ' + (statusStyles[request.status] || 'border-slate-200 bg-slate-50 text-slate-700')}>{request.status}</span></td>
                            </tr>)}
                        </tbody>
                    </table>
                </div>
            </section>}

            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <form method="get" className="simpeg-filter-toolbar simpeg-filter-toolbar--mobile-search flex flex-col gap-3 border-b border-slate-100 p-4 sm:flex-row" action={withUid(`/keluarga/${familyPath}`)}>
                    <label htmlFor="family-search" className="sr-only">Cari data keluarga</label>
                    <input id="family-search" name="search" defaultValue={filters.search} placeholder="Cari nama, NIK, hubungan, atau pegawai" className="h-11 min-w-0 flex-1 rounded-xl border border-slate-200 px-4 text-sm outline-none focus:border-blue-600 focus:ring-4 focus:ring-blue-600/10" />
                    <button type="submit" className="h-11 rounded-xl border border-slate-300 px-5 text-sm font-bold text-slate-700 transition hover:border-blue-600 hover:text-blue-600 focus:outline-none focus:ring-4 focus:ring-blue-600/20">Cari</button>
                </form>
                <div className="overflow-x-auto">
                    <table className="min-w-full text-left">
                        <thead className="border-b border-slate-100 text-xs uppercase tracking-wide text-slate-500"><tr><th className="px-5 py-4">Pegawai</th><th className="px-5 py-4">Nama</th><th className="px-5 py-4">TTL</th><th className="px-5 py-4">Hubungan</th><th className="px-5 py-4">Pendidikan / Pekerjaan</th>{canEdit && <th className="px-5 py-4 text-right">Aksi</th>}</tr></thead>
                        <tbody className="divide-y divide-slate-100">
                            {items.data.length === 0 ? <tr><td colSpan={canEdit ? 6 : 5} className="px-5 py-12 text-center text-sm text-slate-500">Data tidak ditemukan.</td></tr> : items.data.map((item) => <tr key={item.id} className="align-top transition hover:bg-slate-50"><td className="px-5 py-4"><span className="block font-semibold text-slate-800">{item.nama_pegawai}</span><span className="mt-1 font-mono text-xs text-slate-500">{item.id_peg || '-'}</span></td><td className="px-5 py-4"><span className="block font-bold text-slate-800">{item.nama || '-'}</span>{item.nik && <span className="mt-1 block text-xs text-slate-500">NIK: {item.nik}</span>}</td><td className="px-5 py-4 text-sm text-slate-600">{item.ttl || '-'}</td><td className="px-5 py-4 text-sm text-slate-600">{item.status_hub || '-'}{type === 'children' && item.anak_ke && <span className="mt-1 block text-xs font-semibold text-blue-600">Anak ke-{item.anak_ke}</span>}</td><td className="px-5 py-4 text-sm text-slate-600"><span className="block">{item.pendidikan || '-'}</span><span className="mt-1 block text-xs text-slate-400">{item.pekerjaan || '-'}</span>{type === 'spouses' && item.hp && <span className="mt-1 block text-xs text-slate-400">HP: {item.hp}</span>}</td>{canEdit && <td className="whitespace-nowrap px-5 py-4 text-right"><Link href={withUid(`/keluarga/${familyPath}/${item.id}/edit`)} className="mr-2 rounded-lg border border-blue-200 px-3 py-2 text-sm font-bold text-blue-600 hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-600/20">{canRequest ? 'Ajukan ubah' : 'Edit'}</Link>{canDelete && <button type="button" onClick={() => { setDeleteTarget(item); deleteForm.setData('reason', ''); }} className="rounded-lg border border-red-200 px-3 py-2 text-sm font-bold text-red-600 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600/20">{canRequest ? 'Ajukan hapus' : 'Hapus'}</button>}</td>}</tr>)}
                        </tbody>
                    </table>
                </div>
                <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 p-4 text-sm text-slate-500"><span>Menampilkan {items.from || 0}–{items.to || 0} dari {items.total} data</span><div className="flex gap-2">{items.links.filter((link) => link.url).map((link) => <Link key={link.label} href={link.url} preserveState className={`rounded-lg border px-3 py-2 ${link.active ? 'border-blue-600 bg-blue-600 text-white' : 'border-slate-200 hover:border-blue-600 hover:text-blue-600'}`} dangerouslySetInnerHTML={{ __html: link.label }} />)}</div></div>
            </section>

            {deleteTarget && <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" role="presentation"><div className="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="delete-family-title"><h2 id="delete-family-title" className="text-lg font-extrabold text-slate-900">{canRequest ? 'Ajukan penghapusan data?' : 'Hapus data keluarga?'}</h2><p className="mt-2 text-sm text-slate-600">{canRequest ? <>Pengajuan penghapusan <strong>{deleteTarget.nama}</strong> akan dikirim ke kepala cabang untuk diperiksa. Data tetap tampil sampai disetujui.</> : <>Data <strong>{deleteTarget.nama}</strong> akan disimpan ke Recycle Bin sebelum dihapus. Alasan wajib diisi.</>}</p><form onSubmit={confirmDelete} className="mt-5"><label htmlFor="delete-reason" className="mb-2 block text-sm font-semibold text-slate-800">Alasan penghapusan</label><textarea id="delete-reason" value={deleteForm.data.reason} onChange={(event) => deleteForm.setData('reason', event.target.value)} rows="3" required className="w-full rounded-xl border border-slate-200 p-3 text-sm outline-none focus:border-red-500 focus:ring-4 focus:ring-red-500/10" placeholder="Contoh: data sudah tidak sesuai" />{deleteForm.errors.reason && <p className="mt-2 text-sm text-red-600" role="alert">{deleteForm.errors.reason}</p>}<div className="mt-5 flex justify-end gap-3"><button type="button" onClick={() => setDeleteTarget(null)} className="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-600">Batal</button><button type="submit" disabled={deleteForm.processing} className="rounded-lg bg-red-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-red-700 disabled:opacity-60">{deleteForm.processing ? (canRequest ? 'Mengirim...' : 'Menghapus...') : (canRequest ? 'Kirim pengajuan' : 'Ya, Hapus')}</button></div></form></div></div>}
        </LegacyPageShell>
    );
}
