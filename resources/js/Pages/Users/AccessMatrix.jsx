import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import LegacyPageShell from '../../Components/LegacyPageShell';
import SearchableCombobox from '../../Components/SearchableCombobox';
import SelectCombobox from '../../Components/SelectCombobox';
import '../../../css/access-matrix.css';

const subjectLabels = {
    role: 'Role',
    position: 'Jabatan',
    employee: 'ID pegawai',
};

const subjectTypeOptions = Object.entries(subjectLabels).map(([value, label]) => ({ value, label }));

function groupedPermissions(permissions) {
    return permissions.reduce((groups, permission) => {
        groups[permission.group] ||= [];
        groups[permission.group].push(permission);

        return groups;
    }, {});
}

export default function AccessMatrix({ available, metrics, subjects, selected, matrix }) {
    const { flash } = usePage().props;
    const form = useForm({
        subject_type: selected.type,
        subject_value: selected.value,
        permissions: matrix.permissions.filter((permission) => permission.enabled).map((permission) => permission.code),
    });

    useEffect(() => {
        form.setData({
            subject_type: selected.type,
            subject_value: selected.value,
            permissions: matrix.permissions.filter((permission) => permission.enabled).map((permission) => permission.code),
        });
    }, [selected.type, selected.value, matrix.permissions]);

    const groups = useMemo(() => groupedPermissions(matrix.permissions), [matrix.permissions]);
    const [activeGroup, setActiveGroup] = useState('');
    const [permissionSearch, setPermissionSearch] = useState('');
    const activeGroupName = groups[activeGroup] ? activeGroup : Object.keys(groups)[0] || '';
    const searchTerm = permissionSearch.trim().toLocaleLowerCase('id');
    const visibleGroups = searchTerm
        ? Object.entries(groups)
            .map(([group, permissions]) => [
                group,
                permissions.filter((permission) => (
                    group.toLocaleLowerCase('id').includes(searchTerm)
                    || permission.label.toLocaleLowerCase('id').includes(searchTerm)
                )),
            ])
            .filter(([, permissions]) => permissions.length > 0)
        : activeGroupName
            ? [[activeGroupName, groups[activeGroupName]]]
            : [];
    const selectedSubject = (subjects[selected.type] || []).find((subject) => subject.value === selected.value);

    function changeSubjectType(type) {
        router.get('/users/access', { subject_type: type }, { preserveState: false, replace: true });
    }

    function changeSubject(value) {
        router.get('/users/access', { subject_type: selected.type, subject_value: value }, { preserveState: false, replace: true });
    }

    function togglePermission(code, checked) {
        const permissions = new Set(form.data.permissions);
        if (checked) {
            permissions.add(code);
        } else {
            permissions.delete(code);
        }
        form.setData('permissions', [...permissions]);
    }

    function submit(event) {
        event.preventDefault();
        form.put('/users/access', { preserveScroll: true });
    }

    return (
        <LegacyPageShell title="Matriks akses">
            <Head title="Matriks akses" />
            <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div className="min-w-0">
                    <p className="mb-2 text-sm font-semibold text-blue-600">Administrasi akses</p>
                    <h1 className="text-2xl font-medium tracking-tight text-slate-900 sm:text-3xl">Matriks akses</h1>
                    <p className="mt-2 max-w-2xl text-sm leading-6 text-slate-500 sm:text-base">Atur menu yang dapat dibuka berdasarkan role, jabatan, atau ID pegawai. Aturan yang lebih spesifik mengalahkan aturan role.</p>
                </div>
                <Link href="/users" className="inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-blue-600 hover:text-blue-600 sm:w-auto">Kembali ke user</Link>
            </div>

            {flash?.success && <div className="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">{flash.success}</div>}

            <section className="mb-5 grid grid-cols-2 gap-3 sm:mb-6 sm:gap-4 xl:grid-cols-4" aria-label="Ringkasan akses">
                <div className="min-w-0 rounded-2xl border border-slate-200 bg-white p-3 sm:p-5"><p className="break-words text-xs leading-5 text-slate-500 sm:text-sm">Total user</p><p className="mt-1 text-xl font-medium text-slate-900 sm:mt-2 sm:text-2xl">{metrics.users}</p></div>
                <div className="min-w-0 rounded-2xl border border-slate-200 bg-white p-3 sm:p-5"><p className="break-words text-xs leading-5 text-slate-500 sm:text-sm">Role terdaftar</p><p className="mt-1 text-xl font-medium text-slate-900 sm:mt-2 sm:text-2xl">{metrics.roles.length}</p></div>
                <div className="min-w-0 rounded-2xl border border-slate-200 bg-white p-3 sm:p-5"><p className="break-words text-xs leading-5 text-slate-500 sm:text-sm">Jabatan terpetakan</p><p className="mt-1 text-xl font-medium text-slate-900 sm:mt-2 sm:text-2xl">{metrics.positions}</p></div>
                <div className="min-w-0 rounded-2xl border border-slate-200 bg-white p-3 sm:p-5"><p className="break-words text-xs leading-5 text-slate-500 sm:text-sm">Aturan khusus</p><p className="mt-1 text-xl font-medium text-slate-900 sm:mt-2 sm:text-2xl">{metrics.directRules}</p></div>
            </section>

            {!available ? (
                <section className="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 sm:p-5" role="alert">
                    <h2 className="font-medium">Matriks belum diaktifkan</h2>
                    <p className="mt-1">Pengaturan akses belum siap digunakan. Silakan hubungi administrator sistem.</p>
                </section>
            ) : (
                <form onSubmit={submit} className="min-w-0 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="grid gap-4 border-b border-slate-100 p-4 sm:p-5 md:grid-cols-[220px_minmax(0,1fr)_auto] md:items-end">
                        <div>
                            <label htmlFor="access-subject-type" className="mb-2 block text-xs font-medium text-slate-600">Dasar akses</label>
                            <SelectCombobox id="access-subject-type" label="Dasar akses" value={selected.type} options={subjectTypeOptions} onChange={changeSubjectType} />
                        </div>
                        {selected.type === 'role' ? (
                            <div>
                                <label htmlFor="access-subject" className="mb-2 block text-xs font-medium text-slate-600">Pilih role</label>
                                <SelectCombobox id="access-subject" label="Pilih role" value={selected.value} options={subjects.role || []} onChange={changeSubject} />
                            </div>
                        ) : (
                            <SearchableCombobox
                                id="access-subject"
                                label={`Pilih ${subjectLabels[selected.type].toLowerCase()}`}
                                value={selected.value}
                                options={subjects[selected.type] || []}
                                placeholder={`Cari ${subjectLabels[selected.type].toLowerCase()}`}
                                emptyMessage={`${subjectLabels[selected.type]} tidak ditemukan.`}
                                onChange={changeSubject}
                            />
                        )}
                        <div className="min-w-0 truncate text-sm text-slate-500 md:text-right" title={selectedSubject?.label || 'Belum ada pilihan'}>{selectedSubject?.label || 'Belum ada pilihan'}</div>
                    </div>

                    <div className="simpeg-access-matrix">
                        <div className="simpeg-access-matrix__heading">
                            <div className="min-w-0">
                                <h2 className="simpeg-access-matrix__title">Menu yang diizinkan</h2>
                                <p className="simpeg-access-matrix__description">Centang akses yang ingin diberikan pada tingkat ini.</p>
                            </div>
                            <p className="simpeg-access-matrix__summary" aria-live="polite">
                                <strong>{form.data.permissions.length}</strong>
                                <span>dari {matrix.permissions.length} menu dipilih</span>
                            </p>
                        </div>
                        {Object.keys(groups).length > 0 ? (
                            <div className={`simpeg-access-matrix__layout${searchTerm ? ' is-searching' : ''}`}>
                                {!searchTerm && (
                                    <aside className="simpeg-access-matrix__sidebar" aria-label="Kategori izin">
                                        <p className="simpeg-access-matrix__sidebar-title">Kategori menu</p>
                                        <div className="simpeg-access-matrix__categories" role="group" aria-label="Kategori menu">
                                            {Object.entries(groups).map(([group, permissions]) => {
                                                const selectedCount = permissions.filter((permission) => form.data.permissions.includes(permission.code)).length;

                                                return (
                                                    <button
                                                        key={group}
                                                        type="button"
                                                        className={`simpeg-access-matrix__category${group === activeGroupName ? ' is-active' : ''}`}
                                                        aria-pressed={group === activeGroupName}
                                                        onClick={() => setActiveGroup(group)}
                                                    >
                                                        <span>{group}</span>
                                                        <span className="simpeg-access-matrix__category-count">{selectedCount}/{permissions.length}</span>
                                                    </button>
                                                );
                                            })}
                                        </div>
                                    </aside>
                                )}

                                <div className="simpeg-access-matrix__content">
                                    <div className="simpeg-access-matrix__tools">
                                        <label className="simpeg-access-matrix__search-field">
                                            <span>Cari menu</span>
                                            <span className="simpeg-access-matrix__search-control">
                                                <Search className="simpeg-access-matrix__search-icon" aria-hidden="true" />
                                                <input
                                                    type="search"
                                                    value={permissionSearch}
                                                    onChange={(event) => setPermissionSearch(event.target.value)}
                                                    placeholder="Nama menu atau kategori"
                                                    aria-label="Cari menu akses"
                                                />
                                            </span>
                                        </label>
                                        {searchTerm && (
                                            <p className="simpeg-access-matrix__result-count" role="status">
                                                {visibleGroups.reduce((count, [, permissions]) => count + permissions.length, 0)} menu ditemukan
                                            </p>
                                        )}
                                    </div>

                                    {visibleGroups.length > 0 ? (
                                        <div className="simpeg-access-matrix__table-scroll" role="region" aria-label="Daftar izin menu" tabIndex="0">
                                            <table className="simpeg-access-matrix__table">
                                                <thead>
                                                    <tr>
                                                        <th scope="col">Menu</th>
                                                        <th scope="col">Akses</th>
                                                    </tr>
                                                </thead>
                                                {visibleGroups.map(([group, permissions]) => {
                                                    const selectedCount = permissions.filter((permission) => form.data.permissions.includes(permission.code)).length;

                                                    return (
                                                        <tbody key={group}>
                                                            <tr className="simpeg-access-matrix__table-group">
                                                                <th scope="rowgroup" colSpan="2">
                                                                    <span>{group}</span>
                                                                    <span>{selectedCount}/{permissions.length} diizinkan</span>
                                                                </th>
                                                            </tr>
                                                            {permissions.map((permission) => {
                                                                const checked = form.data.permissions.includes(permission.code);

                                                                return (
                                                                    <tr key={permission.code} className={checked ? 'is-checked' : ''}>
                                                                        <th scope="row">{permission.label}</th>
                                                                        <td>
                                                                            <label className="simpeg-access-matrix__check-target">
                                                                                <span className="sr-only">{checked ? 'Cabut' : 'Berikan'} akses {permission.label}</span>
                                                                                <input
                                                                                    type="checkbox"
                                                                                    checked={checked}
                                                                                    onChange={(event) => togglePermission(permission.code, event.target.checked)}
                                                                                    className="simpeg-access-matrix__checkbox"
                                                                                />
                                                                            </label>
                                                                        </td>
                                                                    </tr>
                                                                );
                                                            })}
                                                        </tbody>
                                                    );
                                                })}
                                            </table>
                                        </div>
                                    ) : (
                                        <p className="simpeg-access-matrix__empty">Tidak ada menu yang cocok dengan pencarian.</p>
                                    )}
                                </div>
                            </div>
                        ) : (
                            <p className="simpeg-access-matrix__empty">Tidak ada menu akses yang tersedia.</p>
                        )}
                    </div>

                    <div className="flex flex-col gap-3 border-t border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-end sm:p-5">
                        {form.errors.permissions && <p className="text-sm text-red-600 sm:mr-auto" role="alert">{form.errors.permissions}</p>}
                        <button type="submit" disabled={form.processing} className="inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-blue-600 px-5 py-2 text-sm font-medium text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto">{form.processing ? 'Menyimpan...' : 'Simpan matriks akses'}</button>
                    </div>
                </form>
            )}
        </LegacyPageShell>
    );
}
