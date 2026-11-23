import { Head, Link, router, usePage } from '@inertiajs/react';
import PageJumpControls from '../../Components/PageJumpControls';
import { useRef, useState } from 'react';
import ExportDropdown from '../../Components/ExportDropdown';
import IconAction from '../../Components/IconAction';
import LegacyPageShell from '../../Components/LegacyPageShell';
import MobileFilterGroup from '../../Components/MobileFilterGroup';
import SearchableCombobox from '../../Components/SearchableCombobox';
import SelectCombobox from '../../Components/SelectCombobox';

const ALL_DIVISIONS = '__all__';

export default function EmployeesIndex({ employees, offices, divisions, positions, years, filters, canManage }) {
    const { flash } = usePage().props;
    const [officeValue, setOfficeValue] = useState(String(filters.kantor || ''));
    const [divisionValue, setDivisionValue] = useState(String(filters.divisi || ''));
    const [positionValue, setPositionValue] = useState(String(filters.jabatan || ''));
    const [divisionOptions, setDivisionOptions] = useState(divisions || []);
    const [positionOptions, setPositionOptions] = useState(positions || []);
    const [loadingOptions, setLoadingOptions] = useState(false);
    const optionRequest = useRef(null);
    const withFilters = (url) => {
        const params = new URLSearchParams();
        Object.entries(filters).forEach(([key, value]) => { if (value) params.set(key, value); });
        const query = params.toString();
        return query ? `${url}?${query}` : url;
    };

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
            const params = new URLSearchParams({ kantor: nextOffice, tab: filters.tab });
            const response = await fetch(`/pegawai/filter-options?${params}`, {
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
            const params = new URLSearchParams({ kantor: officeValue, divisi: nextDivision, tab: filters.tab });
            const response = await fetch(`/pegawai/filter-options?${params}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                signal: controller.signal,
            });

            if (!response.ok) throw new Error('Position options request failed');
            const options = await response.json();
            setPositionOptions(options.positions || []);

            // Selecting a unit/division should immediately narrow the table
            // to that scope. The existing Apply button remains available for
            // the optional position/year filters.
            router.get('/pegawai', {
                tab: filters.tab,
                search: filters.search || '',
                kantor: officeValue,
                divisi: nextDivision,
                jabatan: '',
                year: filters.year || '',
            }, { preserveState: true, preserveScroll: true, replace: true });
        } catch (error) {
            if (error.name !== 'AbortError') setPositionOptions([]);
        } finally {
            if (!controller.signal.aborted) setLoadingOptions(false);
        }
    }

    const divisionChoices = officeValue
        ? [{ value: ALL_DIVISIONS, label: '-- Semua Unit/Divisi --' }, ...divisionOptions]
        : divisionOptions;

    function submit(event) {
        event.preventDefault();
        const form = new FormData(event.currentTarget);
        router.get('/pegawai', Object.fromEntries(form.entries()), { preserveState: false, replace: true });
    }

    return (
        <LegacyPageShell title="Data Pegawai">
            <Head title="Data Pegawai" />
            <div className="employees-index-header mb-6">
                <div className="employees-index-heading">
                    <p className="mb-2 text-sm font-semibold uppercase tracking-[0.18em] text-blue-600">Data Pegawai</p>
                    <h1 className="text-3xl font-extrabold tracking-tight text-slate-900">Kelola data pegawai</h1>
                    <p className="mt-2 text-slate-500">Kelola data pegawai, jabatan, status aktif, dan histori purna.</p>
                </div>
                <div className="employees-index-export-box" aria-label="Aksi data pegawai">
                    <ExportDropdown items={[
                        { href: withFilters('/pegawai/export'), label: 'Export XLSX' },
                        { href: withFilters('/pegawai/export/detail'), label: 'Export detail XLSX' },
                        { href: withFilters('/pegawai/export/pdf'), label: 'Cetak / simpan PDF', target: '_blank' },
                    ]} />
                    {canManage && <div className="employees-index-action-group">
                        <div className="employees-index-action-buttons">
                            <Link href="/pegawai/create" className="employees-index-action simpeg-action-primary inline-flex min-h-11 items-center rounded-xl px-4 py-2 text-sm font-bold transition focus:outline-none focus:ring-4 focus:ring-blue-600/20">Tambah pegawai</Link>
                            <Link href={`/pegawai/import?tab=${encodeURIComponent(filters.tab || 'active')}`} className="employees-index-action employees-index-action--import inline-flex min-h-11 items-center rounded-xl border px-4 py-2 text-sm font-bold transition focus:outline-none focus:ring-4">Import XLSX</Link>
                        </div>
                    </div>}
                </div>
            </div>
            {flash?.success && <div className="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800" role="status">{flash.success}</div>}

            <div className="employees-index-controls">
                    <form onSubmit={submit} className="relative z-20 simpeg-filter-toolbar simpeg-filter-toolbar--mobile-panel employees-index-filter-form employees-index-filter-box simpeg-table-filter-form">
                        <MobileFilterGroup id="employees-mobile-filters" activeCount={Number(Boolean(filters.search)) + Number(Boolean(officeValue)) + Number(Boolean(divisionValue)) + Number(Boolean(positionValue)) + Number(Boolean(filters.year))} toggleClassName="employees-index-filter-toggle" fieldsClassName="employees-index-filter-panel">
                            <div className="employees-index-filter-content">
                                <div className="simpeg-mobile-filter-search employees-index-filter-search"><label htmlFor="search" className="mb-2 block text-xs font-bold uppercase tracking-wide text-slate-500">Cari nama / ID pegawai</label><input id="search" name="search" defaultValue={filters.search} placeholder="Cari nama atau ID pegawai" className="h-11 w-full rounded-xl border px-4 text-sm outline-none focus:border-blue-600 focus:ring-4 focus:ring-blue-600/10" /></div>
                                <div className="employees-index-filter-grid">
                                    <SearchableCombobox id="kantor" name="kantor" label="Kantor / Area" value={officeValue} onChange={changeOffice} options={offices} optionValue={(office) => office.kode_kantor_detail} optionLabel={(office) => office.nama_kantor || office.kode_kantor_detail} optionDescription={(office) => office.level || (/wilayah/i.test(office.nama_kantor || '') ? 'KANWIL' : '')} placeholder="Cari kantor atau area" maxOptions={offices.length} />
                                    <SearchableCombobox id="employee-division" name="divisi" label="Divisi / Unit Kerja" value={divisionValue} onChange={changeDivision} options={divisionChoices} optionDescription={(option) => option.description || ''} placeholder={!officeValue ? 'Pilih kantor terlebih dahulu' : loadingOptions && divisionChoices.length === 0 ? 'Memuat unit kerja…' : 'Pilih unit/divisi'} disabled={!officeValue || (loadingOptions && divisionChoices.length === 0)} description={officeValue && divisionOptions.length === 0 && !loadingOptions ? 'Belum ada unit/divisi yang terdata untuk kantor ini.' : undefined} />
                                    <SearchableCombobox id="employee-position" name="jabatan" label="Jabatan" value={positionValue} onChange={setPositionValue} options={positionOptions} placeholder={!officeValue ? 'Pilih kantor terlebih dahulu' : !divisionValue ? 'Pilih unit dulu' : loadingOptions ? 'Memuat jabatan…' : 'Cari jabatan'} disabled={!officeValue || !divisionValue || loadingOptions} />
                                    <SelectCombobox id="employee-start-year" name="year" label="Tahun mulai kerja" value={filters.year} options={years} emptyLabel="Semua tahun" />
                                    <button type="submit" className="simpeg-primary simpeg-mobile-filter-apply employees-index-filter-apply">Terapkan</button>
                                </div>
                            </div>
                        </MobileFilterGroup>
                        <input type="hidden" name="tab" value={filters.tab} />
                    </form>
            </div>

            <section className="employees-index-page">
                <div className="employees-index-table-card"><div className="employees-index-table-scroll simpeg-table-scroll overflow-x-auto"><table className="min-w-full text-left"><thead className="border-b border-slate-100 text-xs uppercase tracking-wide text-slate-500"><tr><th className="px-5 py-4">Pegawai</th><th className="px-5 py-4">TTL</th><th className="px-5 py-4">Jabatan & Unit</th><th className="px-5 py-4">Mulai</th><th className="px-5 py-4">Kontak</th><th className="px-5 py-4 text-right">Aksi</th></tr></thead><tbody className="divide-y divide-slate-100">{employees.data.length === 0 ? <tr><td colSpan="6" className="px-5 py-12 text-center text-sm text-slate-500">Data tidak ditemukan.</td></tr> : employees.data.map((employee) => <tr key={JSON.stringify([employee.id_peg, employee.jabatan, employee.kantor, employee.divisi])} className="align-top transition hover:bg-slate-50"><td className="px-5 py-4"><span className="block font-bold text-slate-800">{employee.nama || '-'}</span><span className="mt-1 inline-block rounded bg-slate-100 px-2 py-1 font-mono text-xs text-slate-500">{employee.id_peg}</span></td><td className="px-5 py-4 text-sm text-slate-600">{employee.ttl || '-'}</td><td className="px-5 py-4"><span className="block text-sm font-semibold text-slate-800">{employee.jabatan || 'Belum ada jabatan'}</span><span className="mt-1 block text-xs font-semibold text-blue-600">{employee.kantor || '-'}</span>{employee.divisi && <span className="mt-1 block text-xs text-slate-400">{employee.divisi}</span>}</td><td className="whitespace-nowrap px-5 py-4 text-sm text-slate-600">{employee.tmt_kerja || '-'}</td><td className="px-5 py-4 text-sm text-slate-600">{employee.telp || '-'}</td><td className="px-5 py-4"><div className="flex justify-end gap-2"><IconAction variant="view" href={`/pegawai/${encodeURIComponent(employee.id_peg)}?tab=${encodeURIComponent(filters.tab || 'active')}`} label="Detail pegawai" />{canManage && <IconAction href={`/pegawai/${employee.id_peg}/edit`} label="Edit data pegawai" />}</div></td></tr>)}</tbody></table></div></div>
                <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 p-4 text-sm text-slate-500"><span>Menampilkan {employees.from || 0}–{employees.to || 0} dari {employees.total} data</span><div className="flex gap-2"><PageJumpControls paginator={employees} /></div></div>
            </section>
        </LegacyPageShell>
    );
}
