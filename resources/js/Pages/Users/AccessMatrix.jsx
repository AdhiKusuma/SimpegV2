import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Edit3, Plus, Search, SlidersHorizontal, X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
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

function formatCount(value) {
    return Number(value || 0).toLocaleString('id-ID');
}

function PermissionSelectAll({ codes, selectedCodes, onChange, label = 'Pilih semua', className = '' }) {
    const checkboxRef = useRef(null);
    const selectedCount = codes.filter((code) => selectedCodes.includes(code)).length;
    const allSelected = codes.length > 0 && selectedCount === codes.length;
    const partiallySelected = selectedCount > 0 && !allSelected;

    useEffect(() => {
        if (checkboxRef.current) {
            checkboxRef.current.indeterminate = partiallySelected;
        }
    }, [partiallySelected]);

    if (codes.length === 0) {
        return null;
    }

    return (
        <label className={'simpeg-permission-select-all ' + className}>
            <input
                ref={checkboxRef}
                type="checkbox"
                checked={allSelected}
                onChange={(event) => onChange(codes, event.target.checked)}
            />
            <span>
                <strong>{label}</strong>
                <small>{selectedCount}/{codes.length}</small>
            </span>
        </label>
    );
}

function RoleModal({ editingRole, permissionCatalog, form, permissionSearch, setPermissionSearch, close }) {
    const groups = useMemo(() => groupedPermissions(permissionCatalog), [permissionCatalog]);
    const term = permissionSearch.trim().toLocaleLowerCase('id');
    const visibleGroups = Object.entries(groups)
        .map(([group, permissions]) => [
            group,
            permissions.filter((permission) => !term
                || group.toLocaleLowerCase('id').includes(term)
                || permission.label.toLocaleLowerCase('id').includes(term)
                || permission.code.toLocaleLowerCase('id').includes(term)),
        ])
        .filter(([, permissions]) => permissions.length > 0);
    const visiblePermissionCodes = visibleGroups.flatMap(([, permissions]) => permissions.map((permission) => permission.code));

    function togglePermission(code, checked) {
        const permissions = new Set(form.data.permissions);
        checked ? permissions.add(code) : permissions.delete(code);
        form.setData('permissions', [...permissions]);
    }

    function togglePermissions(codes, checked) {
        const permissions = new Set(form.data.permissions);
        codes.forEach((code) => (checked ? permissions.add(code) : permissions.delete(code)));
        form.setData('permissions', [...permissions]);
    }

    function submit(event) {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: close };
        if (editingRole) {
            form.put('/users/access/roles/' + encodeURIComponent(editingRole.code), options);
        } else {
            form.post('/users/access/roles', options);
        }
    }

    return (
        <div className="simpeg-role-modal-backdrop">
            <div className="simpeg-role-modal" role="dialog" aria-modal="true" aria-labelledby="role-modal-title">
                <div className="simpeg-role-modal__header">
                    <div>
                        <h2 id="role-modal-title">{editingRole ? 'Edit role' : 'Tambah role'}</h2>
                        <p>Atur identitas role dan permission yang dapat digunakan.</p>
                    </div>
                    <button type="button" className="simpeg-role-modal__close" onClick={close} aria-label="Tutup modal">
                        <X className="h-5 w-5" aria-hidden="true" />
                    </button>
                </div>

                <form onSubmit={submit}>
                    <div className="simpeg-role-modal__fields">
                        <label>
                            <span>Kode</span>
                            <input
                                value={form.data.code}
                                onChange={(event) => form.setData('code', event.target.value.toLowerCase().replace(/[^a-z0-9._-]/g, ''))}
                                placeholder="contoh: supervisor"
                                readOnly={Boolean(editingRole)}
                                required
                            />
                            {editingRole && <small>Kode role sistem tidak dapat diubah.</small>}
                            {form.errors.code && <em>{form.errors.code}</em>}
                        </label>
                        <label>
                            <span>Nama role</span>
                            <input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} required />
                            {form.errors.name && <em>{form.errors.name}</em>}
                        </label>
                        <label>
                            <span>Deskripsi</span>
                            <textarea value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} rows="2" />
                            {form.errors.description && <em>{form.errors.description}</em>}
                        </label>
                    </div>

                    <div className="simpeg-role-modal__permission-heading">
                        <div>
                            <h3>Permission</h3>
                            <p>{form.data.permissions.length} permission dipilih</p>
                        </div>
                        <div className="simpeg-role-modal__permission-tools">
                            <PermissionSelectAll
                                codes={visiblePermissionCodes}
                                selectedCodes={form.data.permissions}
                                onChange={togglePermissions}
                                label={term ? 'Pilih hasil' : 'Pilih semua'}
                            />
                            <label className="simpeg-role-modal__search">
                                <Search className="h-4 w-4" aria-hidden="true" />
                                <input value={permissionSearch} onChange={(event) => setPermissionSearch(event.target.value)} placeholder="Cari permission" />
                            </label>
                        </div>
                    </div>

                    <div className="simpeg-role-modal__permissions">
                        {visibleGroups.map(([group, permissions]) => (
                            <section key={group} className="simpeg-role-modal__permission-group">
                                <div className="simpeg-role-modal__permission-group-heading">
                                    <h4>{group}</h4>
                                    <PermissionSelectAll
                                        codes={permissions.map((permission) => permission.code)}
                                        selectedCodes={form.data.permissions}
                                        onChange={togglePermissions}
                                        label="Pilih kategori"
                                    />
                                </div>
                                <div className="simpeg-role-modal__permission-options">
                                    {permissions.map((permission) => {
                                        const checked = form.data.permissions.includes(permission.code);

                                        return (
                                            <label key={permission.code} className={checked ? 'is-checked' : ''}>
                                                <input type="checkbox" checked={checked} onChange={(event) => togglePermission(permission.code, event.target.checked)} />
                                                <span>
                                                    <strong>{permission.label}</strong>
                                                    <small>{permission.code}</small>
                                                </span>
                                            </label>
                                        );
                                    })}
                                </div>
                            </section>
                        ))}
                    </div>

                    <div className="simpeg-role-modal__footer">
                        <button type="button" className="simpeg-role-button simpeg-role-button--secondary" onClick={close}>Batal</button>
                        <button type="submit" className="simpeg-role-button simpeg-role-button--primary" disabled={form.processing}>
                            {form.processing ? 'Menyimpan...' : editingRole ? 'Simpan perubahan' : 'Tambah role'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

function RoleManagement({ roles, permissionCatalog }) {
    const { flash = {} } = usePage().props;
    const [search, setSearch] = useState('');
    const [editingRole, setEditingRole] = useState(null);
    const [permissionSearch, setPermissionSearch] = useState('');
    const form = useForm({ code: '', name: '', description: '', permissions: [] });
    const term = search.trim().toLocaleLowerCase('id');
    const visibleRoles = roles.filter((role) => !term
        || role.name.toLocaleLowerCase('id').includes(term)
        || role.code.toLocaleLowerCase('id').includes(term)
        || String(role.description || '').toLocaleLowerCase('id').includes(term));

    function closeModal() {
        setEditingRole(null);
        setPermissionSearch('');
        form.reset();
        form.clearErrors();
    }

    function openCreate() {
        form.setData({ code: '', name: '', description: '', permissions: [] });
        form.clearErrors();
        setPermissionSearch('');
        setEditingRole(false);
    }

    function openEdit(role) {
        form.setData({
            code: role.code,
            name: role.name,
            description: role.description || '',
            permissions: role.permissions.map((permission) => permission.code),
        });
        form.clearErrors();
        setPermissionSearch('');
        setEditingRole(role);
    }

    return (
        <LegacyPageShell title="Manajemen Role">
            <Head title="Manajemen Role" />
            <div className="simpeg-role-page">
                <div className="simpeg-role-page__heading">
                    <div>
                        <p className="simpeg-role-page__kicker">Administrasi akses</p>
                        <h1>Manajemen Role</h1>
                        <p>Tentukan kelompok akses berdasarkan kebutuhan kerja.</p>
                    </div>
                    <div className="simpeg-role-page__actions">
                        <Link href="/users/access?mode=special" className="simpeg-role-button simpeg-role-button--secondary">
                            <SlidersHorizontal className="h-4 w-4" aria-hidden="true" />
                            Aturan khusus
                        </Link>
                        <button type="button" className="simpeg-role-button simpeg-role-button--primary" onClick={openCreate}>
                            <Plus className="h-4 w-4" aria-hidden="true" />
                            Tambah role
                        </button>
                    </div>
                </div>

                {flash.success && <div className="simpeg-role-alert simpeg-role-alert--success" role="status">{flash.success}</div>}
                {flash.error && <div className="simpeg-role-alert simpeg-role-alert--error" role="alert">{flash.error}</div>}

                <section className="simpeg-role-table-card">
                    <div className="simpeg-role-toolbar">
                        <label className="simpeg-role-search">
                            <Search className="h-4 w-4" aria-hidden="true" />
                            <input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Cari role atau deskripsi" aria-label="Cari role" />
                        </label>
                        <span>{formatCount(roles.length)} role terdaftar</span>
                    </div>
                    <div className="simpeg-role-table-scroll">
                        <table className="simpeg-role-table">
                            <thead>
                                <tr>
                                    <th>Role</th>
                                    <th>Deskripsi</th>
                                    <th>Permission</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                {visibleRoles.length === 0 ? (
                                    <tr><td colSpan="4" className="simpeg-role-empty">Role tidak ditemukan.</td></tr>
                                ) : visibleRoles.map((role) => (
                                    <tr key={role.code}>
                                        <td>
                                            <strong>{role.name}</strong>
                                            <small>{role.code}</small>
                                            <em>{formatCount(role.user_count)} pengguna</em>
                                        </td>
                                        <td className="simpeg-role-description">{role.description || '—'}</td>
                                        <td>
                                            <div className="simpeg-role-permission-list">
                                                {role.permissions.length === 0 ? <span className="simpeg-role-no-permission">Belum ada permission</span> : role.permissions.map((permission) => (
                                                    <span key={permission.code} title={permission.label}>{permission.code}</span>
                                                ))}
                                            </div>
                                        </td>
                                        <td>
                                            <button type="button" className="simpeg-role-edit-button" onClick={() => openEdit(role)}>
                                                <Edit3 className="h-4 w-4" aria-hidden="true" />
                                                Edit
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            {editingRole !== null && (
                <RoleModal
                    editingRole={editingRole || null}
                    permissionCatalog={permissionCatalog}
                    form={form}
                    permissionSearch={permissionSearch}
                    setPermissionSearch={setPermissionSearch}
                    close={closeModal}
                />
            )}
        </LegacyPageShell>
    );
}

function SpecialAccessMatrix({ available, subjects, selected, matrix }) {
    const { flash } = usePage().props;
    const form = useForm({
        subject_type: selected.type,
        subject_value: selected.value,
        permissions: matrix.permissions.filter((permission) => permission.enabled).map((permission) => permission.code),
    });
    const [activeGroup, setActiveGroup] = useState('');
    const [permissionSearch, setPermissionSearch] = useState('');

    useEffect(() => {
        form.setData({
            subject_type: selected.type,
            subject_value: selected.value,
            permissions: matrix.permissions.filter((permission) => permission.enabled).map((permission) => permission.code),
        });
    }, [selected.type, selected.value, matrix.permissions]);

    const groups = useMemo(() => groupedPermissions(matrix.permissions), [matrix.permissions]);
    const activeGroupName = groups[activeGroup] ? activeGroup : Object.keys(groups)[0] || '';
    const searchTerm = permissionSearch.trim().toLocaleLowerCase('id');
    const visibleGroups = searchTerm
        ? Object.entries(groups).map(([group, permissions]) => [
            group,
            permissions.filter((permission) => group.toLocaleLowerCase('id').includes(searchTerm) || permission.label.toLocaleLowerCase('id').includes(searchTerm)),
        ]).filter(([, permissions]) => permissions.length > 0)
        : activeGroupName ? [[activeGroupName, groups[activeGroupName]]] : [];
    const visiblePermissionCodes = visibleGroups.flatMap(([, permissions]) => permissions.map((permission) => permission.code));
    const selectedSubject = (subjects[selected.type] || []).find((subject) => subject.value === selected.value);

    function changeSubjectType(type) {
        router.get('/users/access', { mode: 'special', subject_type: type }, { preserveState: false, replace: true });
    }

    function changeSubject(value) {
        router.get('/users/access', { mode: 'special', subject_type: selected.type, subject_value: value }, { preserveState: false, replace: true });
    }

    function togglePermission(code, checked) {
        const permissions = new Set(form.data.permissions);
        checked ? permissions.add(code) : permissions.delete(code);
        form.setData('permissions', [...permissions]);
    }

    function togglePermissions(codes, checked) {
        const permissions = new Set(form.data.permissions);
        codes.forEach((code) => (checked ? permissions.add(code) : permissions.delete(code)));
        form.setData('permissions', [...permissions]);
    }

    function submit(event) {
        event.preventDefault();
        form.put('/users/access', { preserveScroll: true });
    }

    return (
        <LegacyPageShell title="Aturan Khusus Akses">
            <Head title="Aturan Khusus Akses" />
            <div className="simpeg-role-page">
                <div className="simpeg-role-page__heading">
                    <div>
                        <p className="simpeg-role-page__kicker">Administrasi akses</p>
                        <h1>Aturan khusus akses</h1>
                        <p>Aturan berdasarkan jabatan atau ID pegawai akan mengalahkan permission pada role.</p>
                    </div>
                    <Link href="/users/access" className="simpeg-role-button simpeg-role-button--secondary">
                        <ArrowLeft className="h-4 w-4" aria-hidden="true" />
                        Kembali ke role
                    </Link>
                </div>

                {flash?.success && <div className="simpeg-role-alert simpeg-role-alert--success" role="status">{flash.success}</div>}
                {!available ? (
                    <section className="simpeg-role-empty-card">Matriks akses belum tersedia. Jalankan migration RBAC terlebih dahulu.</section>
                ) : (
                    <form onSubmit={submit} className="simpeg-special-card">
                        <div className="simpeg-special-toolbar">
                            <div>
                                <label htmlFor="access-subject-type">Dasar akses</label>
                                <SelectCombobox id="access-subject-type" label="Dasar akses" value={selected.type} options={subjectTypeOptions} onChange={changeSubjectType} />
                            </div>
                            {selected.type === 'role' ? (
                                <div>
                                    <label htmlFor="access-subject">Pilih role</label>
                                    <SelectCombobox id="access-subject" label="Pilih role" value={selected.value} options={subjects.role || []} onChange={changeSubject} />
                                </div>
                            ) : (
                                <SearchableCombobox id="access-subject" label={'Pilih ' + subjectLabels[selected.type].toLowerCase()} value={selected.value} options={subjects[selected.type] || []} placeholder={'Cari ' + subjectLabels[selected.type].toLowerCase()} emptyMessage={subjectLabels[selected.type] + ' tidak ditemukan.'} onChange={changeSubject} />
                            )}
                            <p>{selectedSubject?.label || 'Belum ada pilihan'}</p>
                        </div>
                        <div className="simpeg-access-matrix">
                            <div className="simpeg-access-matrix__heading">
                                <div>
                                    <h2 className="simpeg-access-matrix__title">Permission khusus</h2>
                                    <p className="simpeg-access-matrix__description">Centang akses yang ingin diberikan pada tingkat ini.</p>
                                </div>
                                <div className="simpeg-access-matrix__heading-actions">
                                    <PermissionSelectAll
                                        codes={visiblePermissionCodes}
                                        selectedCodes={form.data.permissions}
                                        onChange={togglePermissions}
                                        label={searchTerm ? 'Pilih hasil' : 'Pilih kategori'}
                                    />
                                    <p className="simpeg-access-matrix__summary"><strong>{form.data.permissions.length}</strong><span>dari {matrix.permissions.length} dipilih</span></p>
                                </div>
                            </div>
                            <div className={'simpeg-access-matrix__layout' + (searchTerm ? ' is-searching' : '')}>
                                {!searchTerm && <aside className="simpeg-access-matrix__sidebar"><p className="simpeg-access-matrix__sidebar-title">Kategori menu</p><div className="simpeg-access-matrix__categories">{Object.entries(groups).map(([group, permissions]) => <button key={group} type="button" className={'simpeg-access-matrix__category' + (group === activeGroupName ? ' is-active' : '')} onClick={() => setActiveGroup(group)}><span>{group}</span><span className="simpeg-access-matrix__category-count">{permissions.filter((permission) => form.data.permissions.includes(permission.code)).length}/{permissions.length}</span></button>)}</div></aside>}
                                <div className="simpeg-access-matrix__content">
                                    <div className="simpeg-access-matrix__tools"><label className="simpeg-access-matrix__search-field"><span>Cari menu</span><span className="simpeg-access-matrix__search-control"><Search className="simpeg-access-matrix__search-icon" aria-hidden="true" /><input type="search" value={permissionSearch} onChange={(event) => setPermissionSearch(event.target.value)} placeholder="Nama menu atau kategori" /></span></label></div>
                                    <div className="simpeg-access-matrix__table-scroll"><table className="simpeg-access-matrix__table"><thead><tr><th>Menu</th><th>Akses</th></tr></thead>{visibleGroups.map(([group, permissions]) => <tbody key={group}><tr className="simpeg-access-matrix__table-group"><th colSpan="2"><span>{group}</span></th></tr>{permissions.map((permission) => { const checked = form.data.permissions.includes(permission.code); return <tr key={permission.code} className={checked ? 'is-checked' : ''}><th>{permission.label}</th><td><input type="checkbox" checked={checked} onChange={(event) => togglePermission(permission.code, event.target.checked)} className="simpeg-access-matrix__checkbox" /></td></tr>; })}</tbody>)}</table></div>
                                </div>
                            </div>
                        </div>
                        <div className="simpeg-special-footer"><button type="submit" disabled={form.processing} className="simpeg-role-button simpeg-role-button--primary">{form.processing ? 'Menyimpan...' : 'Simpan aturan khusus'}</button></div>
                    </form>
                )}
            </div>
        </LegacyPageShell>
    );
}

export default function AccessMatrix(props) {
    return props.mode === 'special'
        ? <SpecialAccessMatrix {...props} />
        : <RoleManagement roles={props.roles || []} permissionCatalog={props.permissionCatalog || []} />;
}
