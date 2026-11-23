import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import IconAction from '../../Components/IconAction';
import LegacyPageShell from '../../Components/LegacyPageShell';
import MobileFilterGroup from '../../Components/MobileFilterGroup';
import PageJumpControls from '../../Components/PageJumpControls';
import SelectCombobox from '../../Components/SelectCombobox';

const roleLabels = {
    admin: 'Admin',
    superadmin: 'Superadmin',
    kepala: 'Supervisor',
    user: 'User',
    it_admin: 'IT Administrator',
};

function formatRoleName(summary) {
    return summary.name || roleLabels[summary.code] || summary.code;
}

export default function UsersIndex({ users, filters, roleSummaries = [], canManage }) {
    const { flash } = usePage().props;
    const [deleteTarget, setDeleteTarget] = useState(null);
    const [detail, setDetail] = useState(null);
    const [detailLoading, setDetailLoading] = useState(false);
    const [detailError, setDetailError] = useState('');
    const deleteForm = useForm({ reason: '' });

    useEffect(() => {
        if (!detail) return undefined;

        function closeOnEscape(event) {
            if (event.key === 'Escape') setDetail(null);
        }

        document.addEventListener('keydown', closeOnEscape);
        return () => document.removeEventListener('keydown', closeOnEscape);
    }, [detail]);

    function filter(event) {
        event.preventDefault();
        router.get('/users', Object.fromEntries(new FormData(event.currentTarget).entries()), { preserveState: true, replace: true });
    }

    async function loadRoleMembers(summary, page = 1) {
        setDetail({
            role: summary,
            data: [],
            pagination: { current_page: page, last_page: 1, total: 0 },
        });
        setDetailLoading(true);
        setDetailError('');

        try {
            const url = '/users/roles/' + encodeURIComponent(summary.code) + '/members?page=' + page + '&per_page=25';
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('Daftar user berdasarkan role gagal dimuat.');

            const payload = await response.json();
            setDetail({
                role: payload.role || summary,
                data: payload.members?.data || [],
                pagination: {
                    current_page: payload.members?.current_page || 1,
                    last_page: payload.members?.last_page || 1,
                    total: payload.members?.total || 0,
                },
            });
        } catch (error) {
            setDetailError(error.message || 'Detail user tidak dapat dimuat.');
        } finally {
            setDetailLoading(false);
        }
    }

    function destroy(event) {
        event.preventDefault();
        deleteForm.delete('/users/' + deleteTarget.id_user, {
            preserveScroll: true,
            onSuccess: () => {
                setDeleteTarget(null);
                deleteForm.reset();
            },
        });
    }

    return (
        <LegacyPageShell title="Manajemen User">
            <Head title="Manajemen User" />

            <div className="mb-6 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p className="mb-2 text-sm font-semibold uppercase tracking-[0.18em] text-blue-600">Administrasi Akses</p>
                    <h1 className="text-3xl font-extrabold tracking-tight text-slate-900">Manajemen user</h1>
                    <p className="mt-2 text-slate-500">Kelola akun dan hak akses pengguna.</p>
                </div>
                {canManage && <Link href="/users/create" className="simpeg-action-primary rounded-xl px-4 py-2.5 text-sm font-bold transition focus:outline-none focus:ring-4 focus:ring-blue-600/20">+ Tambah User</Link>}
            </div>

            {flash?.success && <div className="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800" role="status">{flash.success}</div>}

            <section className="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5" aria-label="Ringkasan user berdasarkan role">
                {roleSummaries.map((summary) => (
                    <div key={summary.code} className="flex min-h-[128px] flex-col justify-between rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <div className="flex items-start justify-between gap-3">
                            <div className="min-w-0">
                                <p className="truncate text-sm font-extrabold text-slate-900">{formatRoleName(summary)}</p>
                                <p className="mt-0.5 truncate text-[11px] font-semibold uppercase tracking-wide text-slate-400">{summary.code}</p>
                            </div>
                            <IconAction
                                variant="view"
                                onClick={() => loadRoleMembers(summary)}
                                disabled={summary.total === 0}
                                label={'Lihat user role ' + formatRoleName(summary)}
                            />
                        </div>
                        <div className="mt-3 flex items-baseline gap-2">
                            <span className="text-3xl font-extrabold tracking-tight text-blue-700">{summary.total}</span>
                            <span className="text-xs font-semibold text-slate-500">user</span>
                        </div>
                    </div>
                ))}
            </section>

            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <form onSubmit={filter} className="simpeg-filter-toolbar simpeg-filter-toolbar--mobile-panel grid gap-4 border-b border-slate-100 p-4 md:grid-cols-[1fr_200px_auto] md:items-end">
                    <MobileFilterGroup id="users-mobile-filters" activeCount={Number(Boolean(filters.search)) + Number(Boolean(filters.role && filters.role !== 'Semua'))}>
                        <div className="simpeg-mobile-filter-search">
                            <label htmlFor="user-search" className="mb-2 block text-xs font-bold uppercase tracking-wide text-slate-500">Pencarian</label>
                            <input id="user-search" name="search" defaultValue={filters.search} placeholder="Username, nama, ID pegawai" className="h-11 w-full rounded-xl border border-slate-200 px-4 text-sm" />
                        </div>
                        <SelectCombobox id="user-role" name="role" label="Role" value={filters.role} options={roleSummaries.map((summary) => ({ value: summary.code, label: formatRoleName(summary) }))} emptyLabel="Semua role" />
                        <button type="submit" className="simpeg-primary simpeg-mobile-filter-apply">Terapkan</button>
                    </MobileFilterGroup>
                    <button type="submit" className="simpeg-desktop-filter-apply h-11 rounded-xl bg-blue-600 px-5 text-sm font-bold text-white">Terapkan</button>
                </form>

                <div className="overflow-x-auto">
                    <table className="min-w-full text-left">
                        <thead className="border-b border-slate-100 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th className="px-5 py-4">User</th>
                                <th className="px-5 py-4">Pegawai</th>
                                <th className="px-5 py-4">Role</th>
                                <th className="px-5 py-4">Status</th>
                                {canManage && <th className="px-5 py-4 text-right">Aksi</th>}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {users.data.length === 0 ? (
                                <tr><td colSpan={canManage ? 5 : 4} className="px-5 py-12 text-center text-sm text-slate-500">User tidak ditemukan.</td></tr>
                            ) : users.data.map((user) => (
                                <tr key={user.id_user} className="hover:bg-slate-50">
                                    <td className="px-5 py-4"><span className="block font-bold text-slate-800">{user.nama_user}</span><span className="mt-1 font-mono text-xs text-slate-500">{user.id_user}</span></td>
                                    <td className="px-5 py-4 text-sm text-slate-600">{user.id_pegawai || '-'}<span className="mt-1 block text-xs text-slate-400">{user.jabatan || '-'}</span></td>
                                    <td className="px-5 py-4"><span className="rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700">{roleLabels[user.hak_akses] || user.hak_akses}</span></td>
                                    <td className="px-5 py-4"><span className={'rounded-full px-3 py-1 text-xs font-bold ' + (user.status_aktif === 'Y' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500')}>{user.status_aktif === 'Y' ? 'Aktif' : 'Nonaktif'}</span></td>
                                    {canManage && <td className="whitespace-nowrap px-5 py-4 text-right"><IconAction href={'/users/' + encodeURIComponent(user.id_user) + '/edit'} label="Edit" className="mr-2" /><IconAction variant="delete" onClick={() => { setDeleteTarget(user); deleteForm.setData('reason', ''); }} label="Hapus" /></td>}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 p-4 text-sm text-slate-500">
                    <span>Menampilkan {users.from || 0}–{users.to || 0} dari {users.total} data</span>
                    <div className="flex gap-2"><PageJumpControls paginator={users} /></div>
                </div>
            </section>

            {detail && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" role="presentation" onMouseDown={(event) => { if (event.target === event.currentTarget) setDetail(null); }}>
                    <div className="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-2xl bg-white shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="role-members-title">
                        <div className="flex items-start justify-between gap-4 border-b border-slate-200 p-5">
                            <div>
                                <p className="text-xs font-bold uppercase tracking-wide text-blue-600">Daftar role</p>
                                <h2 id="role-members-title" className="mt-1 text-lg font-extrabold text-slate-900">{formatRoleName(detail.role)}</h2>
                                <p className="mt-1 text-sm text-slate-500">Daftar user yang memiliki role ini.</p>
                            </div>
                            <button type="button" onClick={() => setDetail(null)} className="min-h-11 rounded-lg border border-slate-200 px-3 text-sm font-bold text-slate-600 hover:border-blue-300 hover:text-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-600/20" aria-label="Tutup daftar user">Tutup</button>
                        </div>

                        {detailLoading ? <p className="p-8 text-center text-sm text-slate-500" role="status">Memuat daftar user...</p> : detailError ? <div className="p-8 text-center text-sm text-red-700" role="alert"><p>{detailError}</p><button type="button" onClick={() => loadRoleMembers(detail.role, detail.pagination.current_page)} className="mt-3 rounded-lg border border-red-200 px-3 py-2 font-bold hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600/20">Coba lagi</button></div> : (
                            <>
                                <div className="overflow-x-auto">
                                    <table className="min-w-[620px] w-full text-left">
                                        <thead className="border-b border-slate-100 text-xs uppercase tracking-wide text-slate-500">
                                            <tr><th className="px-5 py-4">Nama user</th><th className="px-5 py-4">Pegawai</th><th className="px-5 py-4">Status</th></tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-100">
                                            {detail.data.length === 0 ? <tr><td colSpan="3" className="px-5 py-10 text-center text-sm text-slate-500">Belum ada user pada role ini.</td></tr> : detail.data.map((user) => (
                                                <tr key={user.id_user}>
                                                    <td className="px-5 py-4"><span className="block font-semibold text-slate-800">{user.nama_user}</span><span className="mt-1 block font-mono text-xs text-slate-500">{user.id_user}</span></td>
                                                    <td className="px-5 py-4 text-sm text-slate-600"><span className="block">{user.id_pegawai || '-'}</span><span className="mt-1 block text-xs text-slate-400">{user.jabatan || '-'}</span></td>
                                                    <td className="px-5 py-4"><span className={'rounded-full px-3 py-1 text-xs font-bold ' + (user.status_aktif === 'Y' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500')}>{user.status_aktif === 'Y' ? 'Aktif' : 'Nonaktif'}</span></td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                                <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 p-4 text-sm text-slate-500">
                                    <span>Total {detail.pagination.total} user</span>
                                    <div className="flex gap-2">
                                        <button type="button" disabled={detail.pagination.current_page <= 1} onClick={() => loadRoleMembers(detail.role, detail.pagination.current_page - 1)} className="rounded-lg border border-slate-200 px-3 py-2 font-bold disabled:cursor-not-allowed disabled:opacity-40 focus:outline-none focus:ring-2 focus:ring-blue-600/20">Sebelumnya</button>
                                        <button type="button" disabled={detail.pagination.current_page >= detail.pagination.last_page} onClick={() => loadRoleMembers(detail.role, detail.pagination.current_page + 1)} className="rounded-lg border border-slate-200 px-3 py-2 font-bold disabled:cursor-not-allowed disabled:opacity-40 focus:outline-none focus:ring-2 focus:ring-blue-600/20">Berikutnya</button>
                                    </div>
                                </div>
                            </>
                        )}
                    </div>
                </div>
            )}

            {deleteTarget && <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4"><div className="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="delete-user-title"><h2 id="delete-user-title" className="text-lg font-extrabold text-slate-900">Hapus user?</h2><p className="mt-2 text-sm text-slate-600">Akun <strong>{deleteTarget.id_user}</strong> akan disimpan ke Recycle Bin.</p><form onSubmit={destroy} className="mt-5"><label htmlFor="delete-user-reason" className="mb-2 block text-sm font-semibold text-slate-800">Alasan penghapusan</label><textarea id="delete-user-reason" value={deleteForm.data.reason} onChange={(event) => deleteForm.setData('reason', event.target.value)} rows="3" required className="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm" />{deleteForm.errors.reason && <p className="mt-2 text-sm text-red-600">{deleteForm.errors.reason}</p>}<div className="mt-5 flex justify-end gap-3"><button type="button" onClick={() => setDeleteTarget(null)} className="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-600">Batal</button><button type="submit" disabled={deleteForm.processing} className="rounded-lg bg-red-600 px-4 py-2.5 text-sm font-bold text-white">{deleteForm.processing ? 'Menghapus...' : 'Ya, Hapus'}</button></div></form></div></div>}
        </LegacyPageShell>
    );
}
