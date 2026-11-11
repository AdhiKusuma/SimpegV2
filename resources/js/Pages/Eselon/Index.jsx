import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import LegacyPageShell from '../../Components/LegacyPageShell';

export default function EselonIndex({ items, record, canManage }) {
    const { flash } = usePage().props;
    const [deleteTarget, setDeleteTarget] = useState(null);
    const form = useForm({ nama_masteresl: record?.nama_masteresl || '' });
    const deleteForm = useForm({ reason: '' });
    const isEdit = Boolean(record);

    function submit(event) {
        event.preventDefault();

        if (isEdit) {
            form.transform((data) => ({ ...data, _method: 'put' })).post(`/eselon/${record.id_masteresl}`);
            return;
        }

        form.post('/eselon');
    }

    function submitDelete(event) {
        event.preventDefault();
        deleteForm.delete(`/eselon/${deleteTarget.id_masteresl}`, {
            preserveScroll: true,
            onSuccess: () => {
                setDeleteTarget(null);
                deleteForm.reset();
            },
        });
    }

    return (
        <LegacyPageShell title="Master Eselon">
            <Head title="Master Eselon" />
            <div className="mb-6">
                <Link href="/jabatan" className="text-sm font-bold text-blue-600 hover:text-blue-700">← Kembali ke Data Jabatan</Link>
                <p className="mb-2 mt-4 text-sm font-semibold uppercase tracking-[0.18em] text-blue-600">Master Referensi</p>
                <h1 className="text-3xl font-extrabold tracking-tight text-slate-900">Master data eselon</h1>
                <p className="mt-2 max-w-2xl text-slate-500">Kelola referensi eselon pegawai.</p>
            </div>

            {flash?.success && <div className="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800" role="status">{flash.success}</div>}

            <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-100 p-5"><h2 className="text-lg font-extrabold text-slate-900">Daftar eselon</h2></div>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-left">
                            <thead className="border-b border-slate-100 text-xs uppercase tracking-wide text-slate-500"><tr><th className="px-5 py-4">Kode</th><th className="px-5 py-4">Nama eselon</th>{canManage && <th className="px-5 py-4 text-right">Aksi</th>}</tr></thead>
                            <tbody className="divide-y divide-slate-100">
                                {items.length === 0 ? <tr><td colSpan={canManage ? 3 : 2} className="px-5 py-12 text-center text-sm text-slate-500">Belum ada master eselon.</td></tr> : items.map((item) => <tr key={item.id_masteresl} className="transition hover:bg-slate-50">
                                    <td className="px-5 py-4 font-mono text-sm text-slate-500">{item.id_masteresl}</td>
                                    <td className="px-5 py-4 font-bold text-slate-800">{item.nama_masteresl}</td>
                                    {canManage && <td className="whitespace-nowrap px-5 py-4 text-right"><Link href={`/eselon/${item.id_masteresl}/edit`} className="mr-2 inline-flex min-h-10 items-center rounded-lg border border-blue-200 px-3 py-2 text-sm font-bold text-blue-700 hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-600/20">Edit</Link><button type="button" onClick={() => { setDeleteTarget(item); deleteForm.setData('reason', ''); }} className="inline-flex min-h-10 items-center rounded-lg border border-red-200 px-3 py-2 text-sm font-bold text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600/20">Hapus</button></td>}
                                </tr>)}
                            </tbody>
                        </table>
                    </div>
                </section>

                {canManage && <section className="h-fit rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 className="text-lg font-extrabold text-slate-900">{isEdit ? 'Edit eselon' : 'Tambah eselon'}</h2>
                    <p className="mt-2 text-sm text-slate-500">Nama eselon mengikuti batas kolom sistem, maksimal 6 karakter.</p>
                    <form onSubmit={submit} className="mt-5">
                        <label htmlFor="nama_masteresl" className="mb-2 block text-xs font-bold uppercase tracking-wide text-slate-600">Nama eselon <span className="text-red-600">*</span></label>
                        <input id="nama_masteresl" name="nama_masteresl" value={form.data.nama_masteresl} onChange={(event) => form.setData('nama_masteresl', event.target.value)} maxLength="6" required className="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm outline-none focus:border-blue-600 focus:bg-white focus:ring-4 focus:ring-blue-600/10" />
                        {form.errors.nama_masteresl && <p className="mt-2 text-xs text-red-600" role="alert">{form.errors.nama_masteresl}</p>}
                        <div className="mt-5 flex flex-wrap justify-end gap-2">{isEdit && <Link href="/eselon" className="inline-flex min-h-11 items-center rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-600">Batal</Link>}<button type="submit" disabled={form.processing} className="min-h-11 rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-60">{form.processing ? 'Menyimpan...' : isEdit ? 'Update eselon' : 'Simpan eselon'}</button></div>
                    </form>
                </section>}
            </div>

            {deleteTarget && <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" role="presentation"><div className="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="delete-eselon-title"><h2 id="delete-eselon-title" className="text-lg font-extrabold text-slate-900">Hapus master eselon?</h2><p className="mt-2 text-sm text-slate-600">Eselon <strong>{deleteTarget.nama_masteresl}</strong> akan disimpan ke Recycle Bin sebelum dihapus.</p><form onSubmit={submitDelete} className="mt-5"><label htmlFor="delete-eselon-reason" className="mb-2 block text-sm font-semibold text-slate-800">Alasan penghapusan</label><textarea id="delete-eselon-reason" value={deleteForm.data.reason} onChange={(event) => deleteForm.setData('reason', event.target.value)} rows="3" required className="w-full rounded-xl border border-slate-200 p-3 text-sm outline-none focus:border-red-500 focus:ring-4 focus:ring-red-500/10" placeholder="Contoh: entri duplikat" />{deleteForm.errors.reason && <p className="mt-2 text-sm text-red-600" role="alert">{deleteForm.errors.reason}</p>}<div className="mt-5 flex justify-end gap-3"><button type="button" onClick={() => setDeleteTarget(null)} className="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-600">Batal</button><button type="submit" disabled={deleteForm.processing} className="rounded-lg bg-red-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-red-700 disabled:opacity-60">{deleteForm.processing ? 'Menghapus...' : 'Ya, hapus'}</button></div></form></div></div>}
        </LegacyPageShell>
    );
}
