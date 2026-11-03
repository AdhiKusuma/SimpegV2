import { Head, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import LegacyPageShell from '../../Components/LegacyPageShell';
import SelectCombobox from '../../Components/SelectCombobox';

const currency = new Intl.NumberFormat('id-ID');

export default function TrainingCosts({ rows, years, filters, totals }) {
    const [selectedRow, setSelectedRow] = useState(null);
    const [detail, setDetail] = useState(null);
    const [detailLoading, setDetailLoading] = useState(false);
    const [detailError, setDetailError] = useState('');

    useEffect(() => {
        if (!selectedRow) {
            return undefined;
        }

        let cancelled = false;

        async function loadDetail() {
            setDetailLoading(true);
            setDetailError('');

            const query = new URLSearchParams({
                diklat: selectedRow.diklat,
                tahun: selectedRow.tahun,
                penyelenggara: selectedRow.penyelenggara === '-' ? '' : selectedRow.penyelenggara,
                page: '1',
            });

            try {
                const response = await fetch(`/laporan/rekap-diklat/detail?${query.toString()}`, {
                    headers: { Accept: 'application/json' },
                });

                if (!response.ok) {
                    throw new Error('Rincian peserta tidak dapat dimuat.');
                }

                const payload = await response.json();
                if (!cancelled) {
                    setDetail(payload);
                }
            } catch (error) {
                if (!cancelled) {
                    setDetailError(error.message);
                }
            } finally {
                if (!cancelled) {
                    setDetailLoading(false);
                }
            }
        }

        loadDetail();

        return () => {
            cancelled = true;
        };
    }, [selectedRow]);

    useEffect(() => {
        if (!selectedRow) {
            return undefined;
        }

        function closeOnEscape(event) {
            if (event.key === 'Escape') {
                setSelectedRow(null);
            }
        }

        document.addEventListener('keydown', closeOnEscape);

        return () => document.removeEventListener('keydown', closeOnEscape);
    }, [selectedRow]);

    function submit(event) {
        event.preventDefault();
        router.get('/laporan/rekap-diklat', Object.fromEntries(new FormData(event.currentTarget).entries()), { preserveState: true, replace: true });
    }

    async function loadPage(page) {
        if (!selectedRow) {
            return;
        }

        setDetailLoading(true);
        setDetailError('');
        const query = new URLSearchParams({
            diklat: selectedRow.diklat,
            tahun: selectedRow.tahun,
            penyelenggara: selectedRow.penyelenggara === '-' ? '' : selectedRow.penyelenggara,
            page: String(page),
        });

        try {
            const response = await fetch(`/laporan/rekap-diklat/detail?${query.toString()}`, {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                throw new Error('Rincian peserta tidak dapat dimuat.');
            }

            setDetail(await response.json());
        } catch (error) {
            setDetailError(error.message);
        } finally {
            setDetailLoading(false);
        }
    }

    const exportQuery = new URLSearchParams(Object.entries(filters).filter(([, value]) => value));

    return (
        <LegacyPageShell title="Rekapitulasi Biaya Diklat">
            <Head title="Rekapitulasi Biaya Diklat" />
            <div className="mb-6">
                <p className="mb-2 text-sm font-semibold uppercase tracking-[0.18em] text-blue-600">Laporan Pengembangan Pegawai</p>
                <h1 className="text-3xl font-extrabold tracking-tight text-slate-900">Rekapitulasi biaya diklat</h1>
                <p className="mt-2 text-slate-500">Ringkasan kegiatan, jumlah peserta, dan realisasi biaya berdasarkan periode sistem.</p>
            </div>

            <section className="mb-6 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <form onSubmit={submit} className="simpeg-filter-toolbar grid gap-4 lg:grid-cols-[1fr_auto] lg:items-end">
                    <SelectCombobox id="training-cost-year" name="tahun" label="Periode" value={filters.tahun} options={years} emptyLabel="Semua tahun" emptyValue="Semua" />
                    <div className="flex flex-wrap gap-2">
                        <button type="submit" className="h-11 rounded-xl bg-blue-600 px-5 text-sm font-bold text-white transition hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-600/20">Terapkan</button>
                        <a href={`/laporan/rekap-diklat/export?${exportQuery.toString()}`} className="inline-flex min-h-11 items-center rounded-xl bg-emerald-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-emerald-700 focus:outline-none focus:ring-4 focus:ring-emerald-600/20">Unduh XLSX</a>
                    </div>
                </form>
            </section>

            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div className="overflow-x-auto">
                    <table className="min-w-[820px] w-full text-left">
                        <thead className="border-b border-slate-100 text-xs uppercase tracking-wide text-slate-500">
                            <tr><th className="px-5 py-4 text-center font-semibold">No</th><th className="px-5 py-4 font-semibold">Nama kegiatan / diklat</th><th className="px-5 py-4 font-semibold">Penyelenggara</th><th className="px-5 py-4 text-center font-semibold">Tahun</th><th className="px-5 py-4 text-center font-semibold">Peserta</th><th className="px-5 py-4 text-right font-semibold">Realisasi biaya</th></tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {rows.length === 0 ? <tr><td colSpan="6" className="px-5 py-12 text-center text-sm text-slate-500">Data rekap tidak ditemukan.</td></tr> : rows.map((row, index) => <tr key={`${row.diklat}-${row.penyelenggara}-${row.tahun}`} className="align-top transition hover:bg-slate-50">
                                <td className="px-5 py-4 text-center text-sm text-slate-500">{index + 1}.</td>
                                <td className="px-5 py-4 font-semibold text-slate-800">{row.diklat}</td>
                                <td className="px-5 py-4 text-sm font-semibold uppercase text-slate-600">{row.penyelenggara}</td>
                                <td className="px-5 py-4 text-center"><span className="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700">{row.tahun}</span></td>
                                <td className="px-5 py-4 text-center"><button type="button" onClick={() => { setDetail(null); setSelectedRow(row); }} className="inline-flex min-h-11 items-center justify-center rounded-lg bg-blue-50 px-4 py-2 text-sm font-bold text-blue-700 transition hover:bg-blue-600 hover:text-white focus:outline-none focus:ring-4 focus:ring-blue-600/20">{row.jumlah_peserta}</button></td>
                                <td className="px-5 py-4 text-right font-mono text-sm font-bold text-slate-800">Rp {currency.format(row.total_biaya_kegiatan)}</td>
                            </tr>)}
                        </tbody>
                        <tfoot className="border-t border-slate-100 bg-slate-50 font-bold text-slate-700">
                            <tr><td colSpan="4" className="px-5 py-4 text-right uppercase">Grand total</td><td className="px-5 py-4 text-center">{currency.format(totals.peserta)}</td><td className="px-5 py-4 text-right font-mono">Rp {currency.format(totals.biaya)}</td></tr>
                        </tfoot>
                    </table>
                </div>
            </section>

            {selectedRow && <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4" role="presentation">
                <div className="flex max-h-[calc(100dvh-2rem)] w-full max-w-4xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="training-detail-title">
                    <div className="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 p-5">
                        <div><h2 id="training-detail-title" className="text-lg font-extrabold text-slate-900">Rincian peserta</h2><p className="mt-1 text-sm text-slate-500">{selectedRow.diklat} · {selectedRow.tahun}</p><p className="text-xs font-semibold uppercase text-slate-400">{selectedRow.penyelenggara}</p></div>
                        <button type="button" onClick={() => setSelectedRow(null)} className="inline-flex min-h-11 items-center rounded-lg border border-slate-300 px-3 py-2 text-sm font-bold text-slate-600 hover:border-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-600/30">Tutup</button>
                    </div>
                    <div className="min-h-0 overflow-y-auto p-5">
                        {detailLoading && <p className="py-8 text-center text-sm text-slate-500" role="status">Memuat rincian peserta...</p>}
                        {detailError && <p className="rounded-xl bg-red-50 px-4 py-3 text-sm font-semibold text-red-700" role="alert">{detailError}</p>}
                        {!detailLoading && !detailError && detail && <>
                            <div className="mb-4 flex flex-wrap items-center justify-between gap-2 text-sm text-slate-500"><span>Total {detail.meta.total} peserta</span><span>Halaman {detail.meta.current_page} dari {detail.meta.last_page}</span></div>
                            <div className="overflow-x-auto"><table className="min-w-[640px] w-full text-left"><thead className="border-b border-slate-100 text-xs uppercase tracking-wide text-slate-500"><tr><th className="px-5 py-4 font-semibold">No</th><th className="px-5 py-4 font-semibold">Nama pegawai</th><th className="px-5 py-4 font-semibold">Unit kerja</th><th className="px-5 py-4 text-right font-semibold">Biaya</th></tr></thead><tbody className="divide-y divide-slate-100">{detail.data.map((item, index) => <tr key={`${item.id_peg}-${index}`} className="align-top transition hover:bg-slate-50"><td className="px-5 py-4 text-sm text-slate-500">{(detail.meta.current_page - 1) * detail.meta.per_page + index + 1}</td><td className="px-5 py-4"><span className="block font-semibold text-slate-800">{item.nama}</span><span className="font-mono text-xs text-slate-500">{item.id_peg}</span></td><td className="px-5 py-4 text-sm text-slate-600">{item.nama_kantor || '-'}</td><td className="px-5 py-4 text-right font-mono text-sm font-bold text-slate-800">Rp {currency.format(item.biaya)}</td></tr>)}</tbody></table></div>
                            {detail.meta.last_page > 1 && <div className="mt-4 flex flex-wrap items-center justify-center gap-2"><button type="button" disabled={detail.meta.current_page <= 1} onClick={() => loadPage(detail.meta.current_page - 1)} className="min-h-11 rounded-lg border border-slate-300 px-3 py-2 text-sm font-bold text-slate-600 disabled:cursor-not-allowed disabled:opacity-40">Sebelumnya</button><span className="px-2 text-sm font-semibold text-slate-500">{detail.meta.current_page} / {detail.meta.last_page}</span><button type="button" disabled={detail.meta.current_page >= detail.meta.last_page} onClick={() => loadPage(detail.meta.current_page + 1)} className="min-h-11 rounded-lg border border-slate-300 px-3 py-2 text-sm font-bold text-slate-600 disabled:cursor-not-allowed disabled:opacity-40">Berikutnya</button></div>}
                        </>}
                    </div>
                </div>
            </div>}
        </LegacyPageShell>
    );
}
