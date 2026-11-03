import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import LegacyPageShell from '../../Components/LegacyPageShell';
import MobileFilterGroup from '../../Components/MobileFilterGroup';
import SelectCombobox from '../../Components/SelectCombobox';

const currency = new Intl.NumberFormat('id-ID');

const quarterOptions = [
    ['1', 'Januari sampai Maret'],
    ['2', 'April sampai Juni'],
    ['3', 'Juli sampai September'],
    ['4', 'Oktober sampai Desember'],
];

function formatRupiah(value) {
    return `Rp ${currency.format(Number(value || 0))}`;
}

export default function BiayaPendidikanIndex({ items, years, filters, canManage }) {
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

    function submitFilters(event) {
        event.preventDefault();
        router.get('/biaya-pendidikan', Object.fromEntries(new FormData(event.currentTarget).entries()), { preserveState: true, replace: true });
    }

    async function loadParticipants(record, page = 1) {
        setDetail({ record, data: [], pagination: { current_page: page, total_pages: 1, total_data: 0 }, summary: { total_orang: 0, total_biaya: 0 } });
        setDetailLoading(true);
        setDetailError('');

        try {
            const response = await fetch(`/biaya-pendidikan/${record.biaya_id}/participants?page=${page}`, { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('Permintaan detail peserta gagal.');
            const payload = await response.json();
            setDetail({ record, ...payload });
        } catch (error) {
            setDetailError(error.message || 'Detail peserta tidak dapat dimuat.');
        } finally {
            setDetailLoading(false);
        }
    }

    function submitDelete(event) {
        event.preventDefault();
        deleteForm.delete(`/biaya-pendidikan/${deleteTarget.biaya_id}`, {
            preserveScroll: true,
            onSuccess: () => {
                setDeleteTarget(null);
                deleteForm.reset();
            },
        });
    }

    return (
        <LegacyPageShell title="Daftar Kegiatan Diklat">
            <Head title="Daftar Kegiatan Diklat" />
            <div className="mb-6 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p className="mb-2 text-sm font-semibold uppercase tracking-[0.16em] text-blue-600">Pengembangan Pegawai</p>
                    <h1 className="text-3xl font-extrabold tracking-tight text-slate-900">Daftar kegiatan diklat</h1>
                    <p className="mt-2 max-w-2xl text-slate-500">Kelola kegiatan pengembangan SDM dan pantau anggaran yang tercatat pada tabel sistem.</p>
                </div>
                {canManage && (
                    <div className="flex flex-wrap gap-2">
                        <Link href="/biaya-pendidikan/import" className="inline-flex min-h-11 items-center rounded-xl border border-blue-200 px-4 py-2.5 text-sm font-bold text-blue-700 hover:bg-blue-50 focus:outline-none focus:ring-4 focus:ring-blue-600/20">Import data</Link>
                        <Link href="/biaya-pendidikan/create" className="inline-flex min-h-11 items-center rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-600/20">Tambah kegiatan</Link>
                    </div>
                )}
            </div>

            {flash?.success && <div className="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800" role="status">{flash.success}</div>}

            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <form onSubmit={submitFilters} className="simpeg-filter-toolbar simpeg-filter-toolbar--mobile-panel grid gap-4 border-b border-slate-100 p-4 lg:grid-cols-[150px_190px_1fr_auto] lg:items-end">
                    <MobileFilterGroup id="training-cost-mobile-filters" activeCount={Number(Boolean(filters.tahun && filters.tahun !== 'Semua')) + Number(Boolean(filters.kuartal && filters.kuartal !== 'Semua'))} toggleClassName="simpeg-mobile-filter-toggle--after-search">
                        <SelectCombobox id="biaya-year" name="tahun" label="Tahun" value={filters.tahun} options={years} emptyLabel="Semua tahun" />
                        <SelectCombobox id="biaya-quarter" name="kuartal" label="Kuartal" value={filters.kuartal} options={quarterOptions.map(([value, label]) => ({ value, label }))} emptyLabel="Semua kuartal" />
                        <button type="submit" className="simpeg-primary simpeg-mobile-filter-apply">Terapkan</button>
                    </MobileFilterGroup>
                    <div className="simpeg-mobile-filter-search"><label htmlFor="biaya-search" className="mb-2 block text-xs font-bold uppercase tracking-wide text-slate-500">Pencarian</label><input id="biaya-search" name="search" defaultValue={filters.search} placeholder="Nama kegiatan, pelaksana, atau kategori" className="h-11 w-full rounded-xl border border-slate-200 px-4 text-sm outline-none focus:border-blue-600 focus:ring-4 focus:ring-blue-600/10" /></div>
                    <button type="submit" className="simpeg-desktop-filter-apply h-11 rounded-xl bg-blue-600 px-5 text-sm font-bold text-white hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-600/20">Terapkan</button>
                </form>

                <div className="overflow-x-auto">
                    <table className="min-w-[920px] w-full text-left">
                        <thead className="border-b border-slate-100 text-xs uppercase tracking-wide text-slate-500">
                            <tr><th className="px-5 py-4">Detail kegiatan</th><th className="px-5 py-4">Pihak pelaksana</th><th className="px-5 py-4">Waktu</th><th className="px-5 py-4 text-right">Biaya dan SDM</th><th className="px-5 py-4 text-right">Aksi</th></tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {items.data.length === 0 ? (
                                <tr><td colSpan="5" className="px-5 py-12 text-center text-sm text-slate-500">Belum ada kegiatan yang sesuai filter. Ubah filter atau pilih Tambah kegiatan.</td></tr>
                            ) : items.data.map((item) => (
                                <tr key={item.biaya_id} className="align-top transition hover:bg-slate-50">
                                    <td className="px-5 py-4"><span className="block font-bold text-slate-800">{item.pengembangan_sdm}</span><span className="mt-1 block text-xs text-slate-500">{item.kategori}</span></td>
                                    <td className="px-5 py-4 text-sm text-slate-600"><span className="block">{item.pihak_pelaksana}</span><span className="mt-1 block text-xs text-slate-400">{item.jenis_pihak}</span></td>
                                    <td className="px-5 py-4 text-sm text-slate-600"><span className="block">{item.tanggal}</span><span className="mt-1 block text-xs text-slate-400">Durasi: {item.waktu_pelaksanaan}</span></td>
                                    <td className="px-5 py-4 text-right text-sm text-slate-600"><span className="block font-bold text-emerald-700">{formatRupiah(item.total_biaya)}</span><span className="mt-1 block text-xs text-slate-400">Target: {item.jumlah_sdm} orang</span></td>
                                    <td className="whitespace-nowrap px-5 py-4 text-right">
                                        <button type="button" onClick={() => loadParticipants(item)} className="mr-2 rounded-lg border border-slate-200 px-3 py-2 text-sm font-bold text-slate-700 hover:border-blue-300 hover:text-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-600/20">Peserta</button>
                                        {canManage && <><Link href={`/biaya-pendidikan/${item.biaya_id}/edit`} className="mr-2 rounded-lg border border-blue-200 px-3 py-2 text-sm font-bold text-blue-600 hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-600/20">Edit</Link><button type="button" onClick={() => { setDeleteTarget(item); deleteForm.setData('reason', ''); }} className="rounded-lg border border-red-200 px-3 py-2 text-sm font-bold text-red-600 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600/20">Hapus</button></>}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 p-4 text-sm text-slate-500"><span>Menampilkan {items.from || 0} sampai {items.to || 0} dari {items.total} data</span><div className="flex flex-wrap gap-2">{items.links.filter((link) => link.url).map((link) => <Link key={link.label} href={link.url} preserveState className={`rounded-lg border px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-600/20 ${link.active ? 'border-blue-600 bg-blue-600 text-white' : 'border-slate-200 hover:border-blue-600 hover:text-blue-600'}`} dangerouslySetInnerHTML={{ __html: link.label }} />)}</div></div>
            </section>

            {detail && <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" role="presentation"><div className="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-2xl bg-white shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="participant-detail-title"><div className="flex items-start justify-between gap-4 border-b border-slate-200 p-5"><div><h2 id="participant-detail-title" className="text-lg font-extrabold text-slate-900">Rincian peserta dan biaya</h2><p className="mt-1 text-sm text-slate-500">{detail.record.pengembangan_sdm}</p></div><button type="button" onClick={() => setDetail(null)} className="min-h-11 rounded-lg border border-slate-200 px-3 text-sm font-bold text-slate-600 hover:border-blue-300 hover:text-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-600/20" aria-label="Tutup rincian peserta">Tutup</button></div><div className="grid gap-4 border-b border-slate-100 bg-slate-50 p-5 sm:grid-cols-3"><div><span className="block text-xs font-bold uppercase tracking-wide text-slate-500">Penyelenggara</span><span className="mt-1 block text-sm font-semibold text-slate-800">{detail.record.pihak_pelaksana}</span></div><div><span className="block text-xs font-bold uppercase tracking-wide text-slate-500">Tanggal</span><span className="mt-1 block text-sm font-semibold text-slate-800">{detail.record.tanggal}</span></div><div><span className="block text-xs font-bold uppercase tracking-wide text-slate-500">Anggaran kegiatan</span><span className="mt-1 block text-sm font-semibold text-emerald-700">{formatRupiah(detail.record.total_biaya)}</span></div></div>{detailLoading ? <p className="p-8 text-center text-sm text-slate-500" role="status">Memuat rincian peserta...</p> : detailError ? <div className="p-8 text-center text-sm text-red-700" role="alert"><p>{detailError}</p><button type="button" onClick={() => loadParticipants(detail.record, detail.pagination.current_page)} className="mt-3 rounded-lg border border-red-200 px-3 py-2 font-bold hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600/20">Coba lagi</button></div> : <><div className="overflow-x-auto"><table className="min-w-[560px] w-full text-left"><thead className="border-b border-slate-100 text-xs uppercase tracking-wide text-slate-500"><tr><th className="px-5 py-4">No</th><th className="px-5 py-4">Pegawai</th><th className="px-5 py-4 text-right">Biaya satuan</th></tr></thead><tbody className="divide-y divide-slate-100">{detail.data.length === 0 ? <tr><td colSpan="3" className="px-5 py-10 text-center text-sm text-slate-500">Belum ada data peserta pada kegiatan ini.</td></tr> : detail.data.map((participant) => <tr key={`${participant.id_peg}-${participant.no}`}><td className="px-5 py-4 text-sm text-slate-500">{participant.no}</td><td className="px-5 py-4"><span className="block font-semibold text-slate-800">{participant.nama}</span><span className="mt-1 block font-mono text-xs text-slate-500">{participant.id_peg}</span></td><td className="px-5 py-4 text-right text-sm font-semibold text-slate-700">{formatRupiah(participant.biaya)}</td></tr>)}</tbody></table></div><div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 p-4 text-sm text-slate-500"><span>Total {detail.summary.total_orang} peserta, {formatRupiah(detail.summary.total_biaya)}</span><div className="flex gap-2"><button type="button" disabled={detail.pagination.current_page <= 1} onClick={() => loadParticipants(detail.record, detail.pagination.current_page - 1)} className="rounded-lg border border-slate-200 px-3 py-2 font-bold disabled:cursor-not-allowed disabled:opacity-40 focus:outline-none focus:ring-2 focus:ring-blue-600/20">Sebelumnya</button><button type="button" disabled={detail.pagination.current_page >= detail.pagination.total_pages} onClick={() => loadParticipants(detail.record, detail.pagination.current_page + 1)} className="rounded-lg border border-slate-200 px-3 py-2 font-bold disabled:cursor-not-allowed disabled:opacity-40 focus:outline-none focus:ring-2 focus:ring-blue-600/20">Berikutnya</button></div></div></>}</div></div>}

            {deleteTarget && <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" role="presentation"><div className="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="delete-biaya-title"><h2 id="delete-biaya-title" className="text-lg font-extrabold text-slate-900">Hapus kegiatan diklat?</h2><p className="mt-2 text-sm text-slate-600">Data kegiatan akan disimpan ke Recycle Bin sebelum dihapus.</p><p className="mt-3 rounded-lg bg-slate-50 p-3 text-sm font-semibold text-slate-800">{deleteTarget.pengembangan_sdm}</p><form onSubmit={submitDelete} className="mt-5"><label htmlFor="delete-biaya-reason" className="mb-2 block text-sm font-semibold text-slate-800">Alasan penghapusan</label><textarea id="delete-biaya-reason" value={deleteForm.data.reason} onChange={(event) => deleteForm.setData('reason', event.target.value)} rows="3" required className="w-full rounded-xl border border-slate-200 p-3 text-sm outline-none focus:border-red-500 focus:ring-4 focus:ring-red-500/10" placeholder="Contoh: data duplikat" />{deleteForm.errors.reason && <p className="mt-2 text-sm text-red-600" role="alert">{deleteForm.errors.reason}</p>}<div className="mt-5 flex justify-end gap-3"><button type="button" onClick={() => setDeleteTarget(null)} className="min-h-11 rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-600/20">Batal</button><button type="submit" disabled={deleteForm.processing} className="min-h-11 rounded-lg bg-red-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-red-700 disabled:opacity-60 focus:outline-none focus:ring-2 focus:ring-red-600/20">{deleteForm.processing ? 'Menghapus...' : 'Ya, hapus'}</button></div></form></div></div>}
        </LegacyPageShell>
    );
}
