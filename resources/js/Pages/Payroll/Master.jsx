import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowUpRight, Banknote, CalendarClock, FileSpreadsheet, Search, Trash2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import LegacyPageShell from '../../Components/LegacyPageShell';
import SelectCombobox from '../../Components/SelectCombobox';
import { formatRupiah } from '../../lib/payrollMoney';

function pageLabel(label) {
    return label === '&laquo; Previous' ? 'Sebelumnya' : label === 'Next &raquo;' ? 'Berikutnya' : label;
}

const monthOptions = [
    { value: '1', label: 'Januari' },
    { value: '2', label: 'Februari' },
    { value: '3', label: 'Maret' },
    { value: '4', label: 'April' },
    { value: '5', label: 'Mei' },
    { value: '6', label: 'Juni' },
    { value: '7', label: 'Juli' },
    { value: '8', label: 'Agustus' },
    { value: '9', label: 'September' },
    { value: '10', label: 'Oktober' },
    { value: '11', label: 'November' },
    { value: '12', label: 'Desember' },
];

export default function Master({ masters, filters = {}, canEdit = false, canDelete = false, deleteRequiresApproval = false, canImport = false, canTransfer = false, transferDefaults = {}, scopeLabel, flash = {} }) {
    const exportHref = `/payroll/master/export${filters.search ? `?${new URLSearchParams({ search: filters.search })}` : ''}`;
    const [selectedDelete, setSelectedDelete] = useState(null);
    const [transferOpen, setTransferOpen] = useState(false);
    const deleteDialog = useRef(null);
    const transferDialog = useRef(null);
    const deleteForm = useForm({ confirmation: '' });
    const transferForm = useForm({
        tahun: String(transferDefaults.tahun || new Date().getFullYear()),
        bulan: String(transferDefaults.bulan || new Date().getMonth() + 1),
        tgl_gajian: transferDefaults.tgl_gajian || new Date().toISOString().slice(0, 10),
    });
    useEffect(() => {
        const dialog = deleteDialog.current;
        if (!dialog) return;
        if (selectedDelete && !dialog.open) dialog.showModal();
        if (!selectedDelete && dialog.open) dialog.close();
    }, [selectedDelete]);
    useEffect(() => {
        const dialog = transferDialog.current;
        if (!dialog) return;
        if (transferOpen && !dialog.open) dialog.showModal();
        if (!transferOpen && dialog.open) dialog.close();
    }, [transferOpen]);

    function search(event) {
        event.preventDefault();
        const form = new FormData(event.currentTarget);
        router.get('/payroll/edit', { search: form.get('search') || '' }, { preserveState: true, preserveScroll: true, replace: true });
    }

    function openDelete(row) {
        deleteForm.reset();
        deleteForm.clearErrors();
        setSelectedDelete(row);
    }

    function closeDelete() {
        setSelectedDelete(null);
        deleteForm.reset();
        deleteForm.clearErrors();
    }

    function submitDelete(event) {
        event.preventDefault();
        if (!selectedDelete) return;
        deleteForm.delete(`/payroll/edit/${encodeURIComponent(selectedDelete.employee_id)}`, {
            preserveScroll: true,
            onSuccess: () => {
                setSelectedDelete(null);
                deleteForm.reset();
            },
        });
    }

    function openTransfer() {
        transferForm.clearErrors();
        setTransferOpen(true);
    }

    function closeTransfer() {
        if (transferForm.processing) return;
        setTransferOpen(false);
        transferForm.clearErrors();
    }

    function submitTransfer(event) {
        event.preventDefault();
        transferForm.post('/payroll/edit/transfer', {
            preserveScroll: true,
            onSuccess: () => setTransferOpen(false),
        });
    }

    return (
        <LegacyPageShell title="Master Gaji">
            <Head title="Master Gaji" />
            <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p className="simpeg-muted mb-2 text-sm font-semibold uppercase tracking-[0.18em]">Penggajian</p>
                    <h1 className="simpeg-text text-3xl font-extrabold tracking-tight">Master Gaji</h1>
                    <p className="simpeg-muted mt-2">Data dasar gaji pegawai yang digunakan sebagai acuan import payroll.</p>
                    {scopeLabel && <p className="simpeg-muted mt-2 text-sm">Cakupan: {scopeLabel}</p>}
                </div>
                <div className="flex flex-col gap-2 sm:flex-row">
                    <a href={exportHref} className="simpeg-utility inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border px-4 text-sm font-bold"><FileSpreadsheet aria-hidden="true" className="h-4 w-4" />Export XLSX</a>
                    {canTransfer && <button type="button" onClick={openTransfer} disabled={!Number(masters.total || 0)} className="simpeg-primary inline-flex min-h-11 items-center justify-center gap-2 rounded-xl px-4 text-sm font-bold disabled:cursor-not-allowed disabled:opacity-50"><ArrowUpRight aria-hidden="true" className="h-4 w-4" />Transfer ke Payroll</button>}
                    {canImport && <Link href="/payroll/import" className="simpeg-utility inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border px-4 text-sm font-semibold"><FileSpreadsheet aria-hidden="true" className="h-4 w-4" />Import XLSX</Link>}
                </div>
            </div>
            {flash.success && <div role="status" className="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{flash.success}</div>}
            {flash.error && <div role="alert" className="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-900">{flash.error}</div>}

            <section className="simpeg-card mb-5 rounded-2xl border p-4 shadow-sm sm:p-5">
                <form onSubmit={search} className="flex flex-col gap-3 sm:flex-row">
                    <label className="simpeg-input flex min-h-11 min-w-0 flex-1 items-center gap-2 rounded-xl border px-3">
                        <Search aria-hidden="true" className="simpeg-subtle h-4 w-4 shrink-0" />
                        <span className="sr-only">Cari pegawai</span>
                        <input name="search" defaultValue={filters.search || ''} placeholder="Cari nama atau ID pegawai" className="simpeg-text min-w-0 flex-1 bg-transparent text-sm outline-none" />
                    </label>
                    <button type="submit" className="simpeg-primary inline-flex min-h-11 items-center justify-center gap-2 rounded-xl px-5 text-sm font-bold">Cari</button>
                </form>
            </section>

            <section className="simpeg-card overflow-hidden rounded-2xl border shadow-sm">
                <div className="flex items-center gap-3 border-b p-4 sm:p-5">
                    <span className="simpeg-card-soft inline-flex h-10 w-10 items-center justify-center rounded-xl border"><Banknote aria-hidden="true" className="h-5 w-5" /></span>
                    <div><h2 className="simpeg-text font-semibold">Gaji per pegawai</h2><p className="simpeg-muted text-sm">{Number(masters.total || 0).toLocaleString('id-ID')} data</p></div>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[760px] text-left text-sm">
                        <thead className="simpeg-muted border-b text-xs uppercase tracking-wide"><tr>
                            <th className="px-5 py-4">Pegawai</th><th className="px-4 py-4">Kantor</th><th className="px-4 py-4 text-right">Total gaji</th><th className="px-4 py-4">Terakhir diubah</th><th className="px-5 py-4 text-right">Aksi</th>
                        </tr></thead>
                        <tbody className="divide-y simpeg-divider">
                            {masters.data.length ? masters.data.map((row) => <tr key={row.employee_id}>
                                <td className="px-5 py-4"><p className="simpeg-text font-semibold">{row.employee_name}</p><p className="simpeg-muted mt-1 font-mono text-xs">{row.employee_id}</p></td>
                                <td className="simpeg-muted px-4 py-4">{row.office_name}<span className="mt-1 block font-mono text-xs">{row.office_code}</span></td>
                                <td className="simpeg-text px-4 py-4 text-right font-semibold tabular-nums">{formatRupiah(row.total)}</td>
                                <td className="simpeg-muted px-4 py-4">{row.updated_at || '—'}{row.updated_by && <span className="mt-1 block text-xs">oleh {row.updated_by}</span>}</td>
                                <td className="px-5 py-4">
                                    <div className="flex flex-wrap justify-end gap-2">
                                        {canEdit && <Link href={`/payroll/edit/${encodeURIComponent(row.employee_id)}`} className="simpeg-utility inline-flex min-h-9 items-center justify-center rounded-lg border px-3 text-sm font-semibold">Edit gaji</Link>}
                                        {canDelete && <button type="button" onClick={() => openDelete(row)} aria-label={`Hapus Master Gaji ${row.employee_name} (${row.employee_id})`} className="inline-flex min-h-9 items-center justify-center gap-1.5 rounded-lg border border-red-200 px-3 text-sm font-semibold text-red-700 transition hover:bg-red-50 focus:outline-none focus:ring-4 focus:ring-red-600/20 dark:border-red-900 dark:text-red-300 dark:hover:bg-red-950/40">
                                            <Trash2 aria-hidden="true" className="h-4 w-4" />Hapus
                                        </button>}
                                        {!canEdit && !canDelete && <span className="simpeg-muted text-xs">Lihat saja</span>}
                                    </div>
                                </td>
                            </tr>) : <tr><td colSpan="5" className="simpeg-muted px-5 py-12 text-center">Belum ada Master Gaji. Tambahkan data manual dari menu Payroll atau lakukan import payroll.</td></tr>}
                        </tbody>
                    </table>
                </div>
                {masters.links?.length > 3 && <nav aria-label="Halaman Master Gaji" className="flex flex-wrap justify-end gap-2 border-t p-4">
                    {masters.links.map((link, index) => link.url ? <Link key={`${index}-${link.label}`} href={link.url} preserveScroll className={`simpeg-utility inline-flex min-h-9 items-center rounded-lg border px-3 text-sm ${link.active ? 'font-bold' : ''}`}>{pageLabel(link.label)}</Link> : <span key={`${index}-${link.label}`} className="simpeg-muted inline-flex min-h-9 items-center rounded-lg border px-3 text-sm opacity-60">{pageLabel(link.label)}</span>)}
                </nav>}
            </section>

            <dialog
                ref={transferDialog}
                onClose={() => setTransferOpen(false)}
                aria-labelledby="transfer-payroll-title"
                aria-describedby="transfer-payroll-description"
                className="simpeg-card m-auto w-[min(42rem,calc(100%-2rem))] max-w-none rounded-2xl border p-0 shadow-2xl backdrop:bg-slate-950/50 backdrop:backdrop-blur-sm"
            >
                <form onSubmit={submitTransfer} className="p-5 sm:p-6">
                    <div className="mb-5 flex items-start gap-3">
                        <span className="simpeg-card-soft inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border">
                            <CalendarClock aria-hidden="true" className="h-5 w-5" />
                        </span>
                        <div>
                            <h2 id="transfer-payroll-title" className="simpeg-text text-lg font-bold">Transfer Master Gaji ke Payroll</h2>
                            <p id="transfer-payroll-description" className="simpeg-muted mt-1 text-sm leading-6">Gunakan data Master Gaji pada cakupan akun ini untuk membuat batch payroll dan menjadwalkan pengiriman slip.</p>
                        </div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <label className="block">
                            <span className="simpeg-muted mb-2 block text-xs font-bold uppercase tracking-wide">Tahun</span>
                            <input type="number" min="2000" max="2100" value={transferForm.data.tahun} onChange={(event) => transferForm.setData('tahun', event.target.value)} className="simpeg-input h-11 w-full rounded-xl border px-3 text-sm outline-none focus:ring-4 focus:ring-[var(--simpeg-focus-ring)]" />
                            {transferForm.errors.tahun && <span className="mt-1 block text-xs text-red-600">{transferForm.errors.tahun}</span>}
                        </label>
                        <SelectCombobox
                            id="transfer-bulan"
                            label="Bulan"
                            options={monthOptions}
                            value={String(transferForm.data.bulan)}
                            onChange={(value) => transferForm.setData('bulan', value)}
                            optionValue={(option) => option.value}
                            optionLabel={(option) => option.label}
                            placeholder="Pilih bulan"
                            error={transferForm.errors.bulan}
                        />
                        <label className="block sm:col-span-2">
                            <span className="simpeg-muted mb-2 block text-xs font-bold uppercase tracking-wide">Tanggal gajian</span>
                            <input type="date" required value={transferForm.data.tgl_gajian} onChange={(event) => transferForm.setData('tgl_gajian', event.target.value)} className="simpeg-input h-11 w-full rounded-xl border px-3 text-sm outline-none focus:ring-4 focus:ring-[var(--simpeg-focus-ring)]" />
                            {transferForm.errors.tgl_gajian && <span className="mt-1 block text-xs text-red-600">{transferForm.errors.tgl_gajian}</span>}
                        </label>
                    </div>

                    <div className="simpeg-card-soft mt-5 rounded-xl border px-3 py-3 text-sm">
                        <p className="simpeg-text font-semibold">Yang akan diproses</p>
                        <p className="simpeg-muted mt-1 leading-6">{Number(masters.total || 0).toLocaleString('id-ID')} Master Gaji. Batch sebelumnya tetap tersimpan sebagai riwayat; hanya batch baru yang menjadi payroll aktif.</p>
                    </div>
                    {transferForm.errors.transfer && <p role="alert" className="mt-3 text-sm font-medium text-red-700 dark:text-red-300">{transferForm.errors.transfer}</p>}

                    <div className="mt-6 flex flex-col-reverse justify-end gap-2 sm:flex-row">
                        <button type="button" onClick={closeTransfer} disabled={transferForm.processing} className="simpeg-utility inline-flex min-h-11 items-center justify-center rounded-xl border px-4 text-sm font-semibold focus:outline-none focus:ring-4 focus:ring-[var(--simpeg-focus-ring)] disabled:opacity-60">Batal</button>
                        <button type="submit" disabled={transferForm.processing} className="simpeg-primary inline-flex min-h-11 items-center justify-center gap-2 rounded-xl px-4 text-sm font-bold disabled:cursor-not-allowed disabled:opacity-60">
                            <ArrowUpRight aria-hidden="true" className="h-4 w-4" />{transferForm.processing ? 'Memproses…' : 'Transfer & Jadwalkan'}
                        </button>
                    </div>
                </form>
            </dialog>

            <dialog
                ref={deleteDialog}
                onClose={closeDelete}
                aria-labelledby="delete-payroll-master-title"
                aria-describedby="delete-payroll-master-description"
                className="simpeg-card m-auto w-[min(32rem,calc(100%-2rem))] max-w-none rounded-2xl border p-0 shadow-2xl backdrop:bg-slate-950/50 backdrop:backdrop-blur-sm"
            >
                {selectedDelete && <form onSubmit={submitDelete} className="p-5 sm:p-6">
                    <div className="mb-4 flex items-start gap-3">
                        <span className="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-red-100 text-red-700 dark:bg-red-950/50 dark:text-red-300">
                            <Trash2 aria-hidden="true" className="h-5 w-5" />
                        </span>
                        <div>
                            <h2 id="delete-payroll-master-title" className="simpeg-text text-lg font-bold">Hapus Master Gaji?</h2>
                            <p id="delete-payroll-master-description" className="simpeg-muted mt-1 text-sm leading-6">
                                {deleteRequiresApproval ? 'Penghapusan akan diterapkan setelah disetujui.' : 'Master Gaji pegawai ini akan dihapus.'} Riwayat payroll yang sudah tercatat tetap tersimpan.
                            </p>
                        </div>
                    </div>
                    <div className="simpeg-card-soft mb-4 rounded-xl border px-3 py-2.5">
                        <p className="simpeg-text text-sm font-semibold">{selectedDelete.employee_name}</p>
                        <p className="simpeg-muted mt-1 font-mono text-xs">{selectedDelete.employee_id} · {selectedDelete.office_name}</p>
                    </div>
                    <label htmlFor="delete-payroll-master-confirmation" className="simpeg-text mb-2 block text-sm font-semibold">Ketik HAPUS untuk melanjutkan</label>
                    <input
                        id="delete-payroll-master-confirmation"
                        autoFocus
                        autoComplete="off"
                        value={deleteForm.data.confirmation}
                        onChange={(event) => deleteForm.setData('confirmation', event.target.value)}
                        aria-invalid={Boolean(deleteForm.errors.confirmation)}
                        aria-describedby={deleteForm.errors.confirmation ? 'delete-payroll-master-confirmation-error' : undefined}
                        className="simpeg-input h-11 w-full rounded-xl border px-3 text-sm outline-none focus:ring-4 focus:ring-[var(--simpeg-focus-ring)]"
                        placeholder="HAPUS"
                    />
                    {deleteForm.errors.confirmation && <p id="delete-payroll-master-confirmation-error" role="alert" className="mt-2 text-sm font-medium text-red-700 dark:text-red-300">{deleteForm.errors.confirmation}</p>}
                    <div className="mt-6 flex flex-col-reverse justify-end gap-2 sm:flex-row">
                        <button type="button" onClick={closeDelete} disabled={deleteForm.processing} className="simpeg-utility inline-flex min-h-11 items-center justify-center rounded-xl border px-4 text-sm font-semibold focus:outline-none focus:ring-4 focus:ring-[var(--simpeg-focus-ring)] disabled:opacity-60">Batal</button>
                        <button type="submit" disabled={deleteForm.processing || deleteForm.data.confirmation !== 'HAPUS'} className="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-red-700 px-4 text-sm font-bold text-white transition hover:bg-red-800 focus:outline-none focus:ring-4 focus:ring-red-700/30 disabled:cursor-not-allowed disabled:opacity-50">
                            <Trash2 aria-hidden="true" className="h-4 w-4" />{deleteForm.processing ? 'Memproses…' : deleteRequiresApproval ? 'Ajukan penghapusan' : 'Hapus Master Gaji'}
                        </button>
                    </div>
                </form>}
            </dialog>
        </LegacyPageShell>
    );
}
