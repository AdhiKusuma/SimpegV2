import { Head, Link, useForm, usePage } from '@inertiajs/react';
import PageJumpControls from '../../Components/PageJumpControls';
import { useRef, useState } from 'react';
import ExportDropdown from '../../Components/ExportDropdown';
import LegacyPageShell from '../../Components/LegacyPageShell';
import MobileFilterGroup from '../../Components/MobileFilterGroup';
import SearchableCombobox from '../../Components/SearchableCombobox';
import SelectCombobox from '../../Components/SelectCombobox';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '../../Components/ui/dropdown-menu';
import { Eye, FileText, MoreHorizontal, Pencil, Trash2 } from 'lucide-react';

function MutationActions({ item, canManage, onDelete }) {
    const [open, setOpen] = useState(false);

    return (
        <div className="relative flex justify-end">
        <DropdownMenu open={open} onOpenChange={setOpen}>
            <DropdownMenuTrigger asChild>
                <button type="button" className="simpeg-utility inline-flex h-9 w-9 items-center justify-center rounded-lg border transition hover:bg-blue-50 focus:outline-none focus:ring-4 focus:ring-blue-600/20" aria-label={`Aksi untuk ${item.nama_pegawai}`} title="Aksi">
                    <MoreHorizontal aria-hidden="true" className="h-4 w-4" />
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent className="simpeg-action-menu absolute right-0 top-[calc(100%+0.5rem)] w-64">
                <DropdownMenuItem asChild>
                    <Link href={`/pegawai/${encodeURIComponent(item.id_peg)}`}><Eye aria-hidden="true" className="h-4 w-4" />Lihat detail pegawai</Link>
                </DropdownMenuItem>
                {item.document_url && <DropdownMenuItem asChild>
                    <a href={item.document_url} target="_blank" rel="noreferrer"><FileText aria-hidden="true" className="h-4 w-4" />Lihat dokumen SK</a>
                </DropdownMenuItem>}
                {canManage && <>
                    <DropdownMenuItem asChild>
                        <Link href={`/pegawai/${encodeURIComponent(item.id_peg)}/edit`}><Pencil aria-hidden="true" className="h-4 w-4" />Edit data pegawai</Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem asChild>
                        <Link href={`/mutasi/${item.id_mutasi}/edit`}><Pencil aria-hidden="true" className="h-4 w-4" />Edit data mutasi</Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem onClick={() => onDelete(item)} className="text-red-700 hover:bg-red-50 hover:text-red-700"><Trash2 aria-hidden="true" className="h-4 w-4" />Hapus data mutasi</DropdownMenuItem>
                </>}
            </DropdownMenuContent>
        </DropdownMenu>
        </div>
    );
}

export default function MutationIndex({ items, filters, offices, divisions, positions, years, employmentStatuses, canManage }) {
    const { flash } = usePage().props;
    const [deleteTarget, setDeleteTarget] = useState(null);
    const [officeValue, setOfficeValue] = useState(String(filters.kantor || ''));
    const [divisionValue, setDivisionValue] = useState(String(filters.divisi || ''));
    const [positionValue, setPositionValue] = useState(String(filters.jabatan || ''));
    const [divisionOptions, setDivisionOptions] = useState(divisions || []);
    const [positionOptions, setPositionOptions] = useState(positions || []);
    const [loadingOptions, setLoadingOptions] = useState(false);
    const optionRequest = useRef(null);
    const deleteForm = useForm({ reason: '' });
    const exportParams = new URLSearchParams();
    if (filters.search) exportParams.set('search', filters.search);
    if (filters.kantor) exportParams.set('kantor', filters.kantor);
    if (filters.divisi) exportParams.set('divisi', filters.divisi);
    if (filters.jabatan) exportParams.set('jabatan', filters.jabatan);
    if (filters.year) exportParams.set('year', filters.year);
    if (filters.kategori) exportParams.set('kategori', filters.kategori);
    if (filters.status_kepeg) exportParams.set('status_kepeg', filters.status_kepeg);
    const exportQuery = exportParams.toString();
    const exportHref = `/mutasi/export${exportQuery ? `?${exportQuery}` : ''}`;

    async function changeOffice(nextOffice) {
        setOfficeValue(nextOffice);
        setDivisionValue('');
        setPositionValue('');
        setDivisionOptions([]);
        setPositionOptions([]);
        optionRequest.current?.abort();

        if (!nextOffice) {
            setLoadingOptions(false);
            return;
        }

        const controller = new AbortController();
        optionRequest.current = controller;
        setLoadingOptions(true);

        try {
            const params = new URLSearchParams({ kantor: nextOffice });
            const response = await fetch(`/mutasi/filter-options?${params}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                signal: controller.signal,
            });

            if (!response.ok) throw new Error('Filter options request failed');
            const options = await response.json();
            setDivisionOptions(options.divisions || []);
            setPositionOptions([]);
        } catch (error) {
            if (error.name !== 'AbortError') {
                setDivisionOptions([]);
                setPositionOptions([]);
            }
        } finally {
            if (!controller.signal.aborted) setLoadingOptions(false);
        }
    }

    async function changeDivision(nextDivision) {
        setDivisionValue(nextDivision);
        setPositionValue('');
        setPositionOptions([]);
        optionRequest.current?.abort();

        if (!officeValue || !nextDivision) {
            setLoadingOptions(false);
            return;
        }

        const controller = new AbortController();
        optionRequest.current = controller;
        setLoadingOptions(true);

        try {
            const params = new URLSearchParams({ kantor: officeValue, divisi: nextDivision });
            const response = await fetch(`/mutasi/filter-options?${params}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                signal: controller.signal,
            });

            if (!response.ok) throw new Error('Position options request failed');
            const options = await response.json();
            setPositionOptions(options.positions || []);
        } catch (error) {
            if (error.name !== 'AbortError') setPositionOptions([]);
        } finally {
            if (!controller.signal.aborted) setLoadingOptions(false);
        }
    }

    function submitDelete(event) {
        event.preventDefault();
        deleteForm.delete(`/mutasi/${deleteTarget.id_mutasi}`, {
            preserveScroll: true,
            onSuccess: () => {
                setDeleteTarget(null);
                deleteForm.reset();
            },
        });
    }

    return (
        <LegacyPageShell title="Purna & Penonaktifan Pegawai">
            <Head title="Purna & Penonaktifan Pegawai" />
            <div className="mb-6 flex flex-wrap items-end justify-between gap-4">
                <div><p className="mb-2 text-sm font-semibold uppercase tracking-[0.18em] text-blue-600">Mutasi Kepegawaian</p><h1 className="text-3xl font-extrabold tracking-tight text-slate-900">Purna & penonaktifan pegawai</h1><p className="mt-2 text-slate-500">Daftar seluruh pegawai yang memiliki riwayat purna atau penonaktifan.</p></div>
                <div className="flex flex-wrap items-center gap-3">
                    <ExportDropdown items={[{ href: exportHref, label: 'Export XLSX' }]} />
                    {canManage && <><Link href="/mutasi/import" className="inline-flex min-h-11 items-center rounded-xl border border-red-200 bg-white px-4 py-2.5 text-sm font-bold text-red-700 shadow-sm transition hover:bg-red-50 focus:outline-none focus:ring-4 focus:ring-red-600/20">Import XLSX</Link><Link href="/mutasi/create" className="simpeg-action-primary rounded-xl px-4 py-2.5 text-sm font-bold shadow-sm transition focus:outline-none focus:ring-4 focus:ring-blue-600/20">+ Tambah Data</Link></>}
                </div>
            </div>

            {flash?.success && <div className="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800" role="status">{flash.success}</div>}
            {flash?.error && <div className="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800" role="alert">{flash.error}</div>}

            <section className="simpeg-filter-list-shell">
                <form method="get" action="/mutasi" className="simpeg-filter-toolbar simpeg-filter-toolbar--mobile-panel mutation-index-filter-box">
                    <MobileFilterGroup id="mutation-mobile-filters" fieldsClassName="mutation-index-filter-panel" activeCount={Number(Boolean(filters.search)) + Number(Boolean(officeValue)) + Number(Boolean(divisionValue)) + Number(Boolean(positionValue)) + Number(Boolean(filters.year)) + Number(Boolean(filters.kategori)) + Number(Boolean(filters.status_kepeg))}>
                        <div className="mutation-index-filter-content">
                            <div className="simpeg-mobile-filter-search mutation-index-filter-search"><label htmlFor="mutation-search" className="mb-2 block text-xs font-bold uppercase tracking-wide text-slate-500">Cari nama / ID pegawai</label><input id="mutation-search" name="search" defaultValue={filters.search} placeholder="Cari nama atau ID pegawai" className="h-11 min-w-0 w-full rounded-xl border border-slate-200 px-4 text-sm outline-none focus:border-red-600 focus:ring-4 focus:ring-red-600/10" /></div>
                            <div className="mutation-index-filter-grid">
                                <SelectCombobox id="mutation-category" name="kategori" label="Kategori data" value={filters.kategori} options={[{ value: 'purna', label: 'Purna' }, { value: 'penonaktifan', label: 'Penonaktifan lainnya' }]} emptyLabel="Semua data" />
                                <SearchableCombobox id="mutation-office" name="kantor" label="Kantor / Area" value={officeValue} onChange={changeOffice} options={offices || []} optionValue={(office) => office.kode_kantor_detail} optionLabel={(office) => office.nama_kantor || office.kode_kantor_detail} optionDescription={(office) => office.level || (/wilayah/i.test(office.nama_kantor || '') ? 'KANWIL' : '')} placeholder="Cari kantor atau area" maxOptions={(offices || []).length} />
                                <SearchableCombobox id="mutation-division" name="divisi" label="Divisi / Unit Kerja" value={divisionValue} onChange={changeDivision} options={divisionOptions} optionDescription={(option) => option.description || ''} placeholder={!officeValue ? 'Pilih kantor terlebih dahulu' : loadingOptions && divisionOptions.length === 0 ? 'Memuat unit kerja…' : 'Pilih unit/divisi'} disabled={!officeValue || (loadingOptions && divisionOptions.length === 0)} description={officeValue && divisionOptions.length === 0 && !loadingOptions ? 'Belum ada unit/divisi yang terdata untuk kantor ini.' : undefined} />
                                <SearchableCombobox id="mutation-position" name="jabatan" label="Jabatan" value={positionValue} onChange={setPositionValue} options={positionOptions} optionDescription={(option) => option.description || ''} placeholder={!officeValue ? 'Pilih kantor terlebih dahulu' : !divisionValue ? 'Pilih unit dulu' : loadingOptions ? 'Memuat jabatan…' : 'Cari jabatan'} disabled={!officeValue || !divisionValue || loadingOptions} />
                                <SelectCombobox id="mutation-start-year" name="year" label="Tahun mulai kerja" value={filters.year} options={years} emptyLabel="Semua tahun" />
                                <SelectCombobox id="mutation-employment-status" name="status_kepeg" label="Status kepegawaian" value={filters.status_kepeg} options={(employmentStatuses || []).map((status) => ({ value: status, label: status }))} emptyLabel="Semua status" />
                                <button type="submit" className="simpeg-primary simpeg-mobile-filter-apply mutation-index-filter-apply">Terapkan</button>
                            </div>
                        </div>
                    </MobileFilterGroup>
                    <button type="submit" className="simpeg-primary simpeg-desktop-filter-apply h-11 rounded-xl px-5 text-sm font-bold transition focus:outline-none focus:ring-4 focus:ring-blue-600/20">Terapkan</button>
                </form>
                <div className="overflow-x-auto"><table className="min-w-full text-left"><thead className="border-b border-slate-100 text-xs uppercase tracking-wide text-slate-500"><tr><th className="px-5 py-4">Pegawai</th><th className="px-5 py-4">Jabatan terakhir</th><th className="px-5 py-4">Kategori</th><th className="px-5 py-4">Jenis mutasi</th><th className="px-5 py-4">Tanggal</th><th className="px-5 py-4">No. SK</th><th className="px-5 py-4 text-right">Aksi</th></tr></thead><tbody className="divide-y divide-slate-100">{items.data.length === 0 ? <tr><td colSpan="7" className="px-5 py-12 text-center text-sm text-slate-500">Data purna dan penonaktifan tidak ditemukan.</td></tr> : items.data.map((item) => <tr key={item.id_mutasi} className="align-top transition hover:bg-slate-50"><td className="px-5 py-4"><Link href={`/pegawai/${encodeURIComponent(item.id_peg)}`} className="block font-bold text-blue-700 hover:underline">{item.nama_pegawai}</Link><span className="mt-1 block font-mono text-xs text-slate-500">{item.id_peg || '-'}</span><span className="mt-1 block text-xs text-slate-500">{item.status_kepeg || '-'}</span></td><td className="px-5 py-4 text-sm text-slate-600">{item.jabatan || '-'}</td><td className="px-5 py-4"><span className={`inline-flex rounded-full px-3 py-1 text-xs font-bold ${item.kategori === 'Purna' ? 'bg-blue-50 text-blue-700' : 'bg-amber-50 text-amber-700'}`}>{item.kategori}</span></td><td className="px-5 py-4 text-sm font-semibold text-slate-700">{item.jns_mutasi}</td><td className="whitespace-nowrap px-5 py-4 text-sm text-slate-600"><span className="block">SK: {item.tgl_mutasi || '-'}</span><span className="mt-1 block text-xs text-slate-500">TMT: {item.tmt || '-'}</span></td><td className="px-5 py-4 text-sm font-semibold text-slate-700">{item.no_mutasi || '-'}</td><td className="whitespace-nowrap px-5 py-4 text-right"><MutationActions item={item} canManage={canManage} onDelete={(target) => { setDeleteTarget(target); deleteForm.setData('reason', ''); }} /></td></tr>)}</tbody></table></div>
                <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 p-4 text-sm text-slate-500"><span>Menampilkan {items.from || 0}–{items.to || 0} dari {items.total} data</span><div className="flex gap-2"><PageJumpControls paginator={items} /></div></div>
            </section>

            {deleteTarget && <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" role="presentation"><div className="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="delete-mutation-title"><h2 id="delete-mutation-title" className="text-lg font-extrabold text-slate-900">Hapus data mutasi?</h2><p className="mt-2 text-sm text-slate-600">Data <strong>{deleteTarget.no_mutasi}</strong> akan disimpan ke Recycle Bin. Status pegawai dan jabatan terakhir akan dipulihkan sesuai alur sistem.</p><form onSubmit={submitDelete} className="mt-5"><label htmlFor="delete-mutation-reason" className="mb-2 block text-sm font-semibold text-slate-800">Alasan penghapusan</label><textarea id="delete-mutation-reason" value={deleteForm.data.reason} onChange={(event) => deleteForm.setData('reason', event.target.value)} rows="3" required className="w-full rounded-xl border border-slate-200 p-3 text-sm outline-none focus:border-red-500 focus:ring-4 focus:ring-red-500/10" placeholder="Contoh: salah input nomor SK" />{deleteForm.errors.reason && <p className="mt-2 text-sm text-red-600" role="alert">{deleteForm.errors.reason}</p>}<div className="mt-5 flex justify-end gap-3"><button type="button" onClick={() => setDeleteTarget(null)} className="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-600">Batal</button><button type="submit" disabled={deleteForm.processing} className="rounded-lg bg-red-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-red-700 disabled:opacity-60">{deleteForm.processing ? 'Menghapus...' : 'Ya, Hapus'}</button></div></form></div></div>}
        </LegacyPageShell>
    );
}
