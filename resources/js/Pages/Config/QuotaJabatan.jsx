import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Check, Pencil, Plus, Trash2, X } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import SearchableCombobox from '../../Components/SearchableCombobox';
import SelectCombobox from '../../Components/SelectCombobox';
import LegacyPageShell from '../../Components/LegacyPageShell';

const emptyForm = {
    kode_cabang: '',
    quotas: [],
    jabatan_id: '',
    kuota_pegawai: 0,
};

function errorForBatchQuota(errors, index, field = 'kuota_pegawai') {
    return errors?.['quotas.' + index + '.' + field] || errors?.['quotas.' + index];
}

function QuotaJabatanBatch({ branchOptions = [], branchPositionOptions = [], positionOptions = [], quotas = [] }) {
    const { flash } = usePage().props;
    const [selectedBranchCode, setSelectedBranchCode] = useState('');
    const [showCreateForm, setShowCreateForm] = useState(false);
    const [createPositionName, setCreatePositionName] = useState('');
    const [createQuota, setCreateQuota] = useState('');
    const [editingPositionKey, setEditingPositionKey] = useState(null);
    const [editingPositionName, setEditingPositionName] = useState('');
    const [savingPositionKey, setSavingPositionKey] = useState(null);
    const [deletingPositionId, setDeletingPositionId] = useState(null);
    const form = useForm({ kode_cabang: '', quotas: [] });
    const createForm = useForm({ kode_cabang: '', jabatan_id: '', nama_jabatan: '', kuota_pegawai: '' });

    const selectedBranch = useMemo(
        () => branchOptions.find((branch) => String(branch.kode_cabang) === selectedBranchCode),
        [branchOptions, selectedBranchCode],
    );
    const branchPositionsForOffice = useMemo(
        () => branchPositionOptions.find((branch) => String(branch.kode_cabang) === selectedBranchCode)?.positions || [],
        [branchPositionOptions, selectedBranchCode],
    );
    const effectiveQuotas = useMemo(
        () => quotas.filter((quota) => String(quota.kode_cabang) === selectedBranchCode),
        [quotas, selectedBranchCode],
    );
    const existingQuotaByPosition = useMemo(
        () => new Map(effectiveQuotas
            .filter((quota) => quota.jabatan_id !== null && quota.jabatan_id !== undefined)
            .map((quota) => [String(quota.jabatan_id), quota])),
        [effectiveQuotas],
    );
    const customQuotas = useMemo(
        () => effectiveQuotas.filter((quota) => (quota.jabatan_id === null || quota.jabatan_id === undefined) && quota.nama_jabatan),
        [effectiveQuotas],
    );
    const selectedBranchPositions = useMemo(() => {
        const positions = new Map(
            branchPositionsForOffice.map((position) => {
                const savedQuota = existingQuotaByPosition.get(String(position.kode_jabatan));

                return [String(position.kode_jabatan), savedQuota?.nama_jabatan
                    ? { ...position, nama_jabatan: savedQuota.nama_jabatan }
                    : position];
            }),
        );

        effectiveQuotas.forEach((quota) => {
            const positionId = String(quota.jabatan_id);
            if (positions.has(positionId)) {
                if (quota.nama_jabatan) {
                    positions.set(positionId, { ...positions.get(positionId), nama_jabatan: quota.nama_jabatan });
                }

                return;
            }

            const masterPosition = positionOptions.find((position) => String(position.kode_jabatan) === positionId);
            if (masterPosition) {
                positions.set(positionId, quota.nama_jabatan
                    ? { ...masterPosition, nama_jabatan: quota.nama_jabatan }
                    : masterPosition);
            }
        });

        customQuotas.forEach((quota) => {
            positions.set('custom:'+quota.id, {
                custom_id: quota.id,
                kode_jabatan: null,
                nama_jabatan: quota.nama_jabatan,
                group_jabatan: 'Custom',
                lingkup: '-',
                saved_quota: quota,
            });
        });

        return Array.from(positions.values());
    }, [branchPositionsForOffice, customQuotas, effectiveQuotas, positionOptions]);

    useEffect(() => {
        form.setData('quotas', selectedBranchPositions.map((position) => {
            const savedQuota = position.saved_quota || existingQuotaByPosition.get(String(position.kode_jabatan));

            return {
                id: savedQuota?.id ?? null,
                jabatan_id: position.kode_jabatan === null ? null : String(position.kode_jabatan),
                nama_jabatan: savedQuota?.nama_jabatan || (position.custom_id ? position.nama_jabatan : null),
                kuota_pegawai: savedQuota?.kuota_pegawai ?? 0,
            };
        }));
        form.clearErrors();
    }, [existingQuotaByPosition, selectedBranchPositions]);

    function selectBranch(value) {
        const branchCode = String(value || '');

        setSelectedBranchCode(branchCode);
        form.setData('kode_cabang', branchCode);
        createForm.setData({ kode_cabang: branchCode, jabatan_id: '', nama_jabatan: '', kuota_pegawai: '' });
        setShowCreateForm(false);
        setCreatePositionName('');
        setCreateQuota('');
        setEditingPositionKey(null);
        setEditingPositionName('');
        form.clearErrors();
        createForm.clearErrors();
    }

    function updateQuota(index, value) {
        form.setData('quotas', form.data.quotas.map((quota, quotaIndex) => (
            quotaIndex === index ? { ...quota, kuota_pegawai: value } : quota
        )));
    }

    function submit(event) {
        event.preventDefault();
        form.post('/config/kuota-jabatan', { preserveScroll: true });
    }

    function submitCreate(event) {
        event.preventDefault();
        createForm.post('/config/kuota-jabatan', {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                setShowCreateForm(false);
                setCreatePositionName('');
                setCreateQuota('');
                createForm.setData({ kode_cabang: selectedBranchCode, jabatan_id: '', nama_jabatan: '', kuota_pegawai: '' });
            },
        });
    }

    function startEditPosition(position) {
        const positionKey = position.custom_id ? 'custom:'+position.custom_id : String(position.kode_jabatan);

        setEditingPositionKey(positionKey);
        setEditingPositionName(position.nama_jabatan);
    }

    function cancelEditPosition() {
        setEditingPositionKey(null);
        setEditingPositionName('');
    }

    function savePositionName(position, index) {
        const nextName = editingPositionName.trim();
        if (!nextName) return;

        const savedQuota = position.saved_quota || existingQuotaByPosition.get(String(position.kode_jabatan));
        const positionKey = position.custom_id ? 'custom:'+position.custom_id : String(position.kode_jabatan);
        const payload = {
            kode_cabang: selectedBranchCode,
            jabatan_id: position.custom_id ? null : position.kode_jabatan,
            nama_jabatan: nextName,
            kuota_pegawai: form.data.quotas[index]?.kuota_pegawai ?? savedQuota?.kuota_pegawai ?? 0,
        };
        const options = {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => setSavingPositionKey(null),
            onSuccess: () => {
                setEditingPositionKey(null);
                setEditingPositionName('');
            },
        };

        setSavingPositionKey(positionKey);
        if (savedQuota?.id) {
            router.put('/config/kuota-jabatan/' + savedQuota.id, payload, options);
            return;
        }

        router.post('/config/kuota-jabatan', payload, options);
    }

    function deleteQuota(position) {
        const savedQuota = position.saved_quota || existingQuotaByPosition.get(String(position.kode_jabatan));
        const positionKey = position.custom_id ? 'custom:'+position.custom_id : String(position.kode_jabatan);
        const confirmed = window.confirm(
            'Hapus kuota ' + position.nama_jabatan + ' dari ' + (selectedBranch?.label || selectedBranchCode) + '? Nilainya akan disetel menjadi 0.',
        );

        if (!confirmed) return;

        setDeletingPositionId(positionKey);
        const options = {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => setDeletingPositionId(null),
        };

        if (savedQuota?.id) {
            router.delete('/config/kuota-jabatan/' + savedQuota.id, options);
            return;
        }

        router.post('/config/kuota-jabatan', {
            kode_cabang: selectedBranchCode,
            jabatan_id: position.kode_jabatan,
            kuota_pegawai: 0,
        }, options);
    }

    return (
        <LegacyPageShell title="Kuota Jabatan">
            <Head title="Kuota Jabatan" />
            <div className="simpeg-quota-page">
                <div className="simpeg-quota-intro">
                    <div>
                        <p className="simpeg-quota-eyebrow">Administrasi jabatan</p>
                        <h1 className="simpeg-quota-title">Kuota jabatan</h1>
                        <p className="simpeg-quota-description">
                            Pilih kantor untuk menampilkan seluruh jabatan aktif yang tersedia, lalu atur kuotanya sekaligus.
                        </p>
                    </div>
                </div>

                {flash?.success && <div className="simpeg-quota-flash" role="status">{flash.success}</div>}

                <section className="simpeg-quota-card simpeg-quota-form-card" aria-labelledby="quota-form-title">
                    <div className="simpeg-quota-card-heading">
                        <div>
                            <p className="simpeg-quota-section-kicker">Pengaturan per kantor</p>
                            <h2 id="quota-form-title" className="simpeg-quota-section-title">Tambah kuota jabatan</h2>
                        </div>
                    </div>

                    <form onSubmit={submit} className="simpeg-quota-batch-form">
                        <div className="simpeg-quota-branch-picker">
                            <SelectCombobox
                                id="quota-branch"
                                name="kode_cabang"
                                label="KCU / kantor cabang"
                                value={selectedBranchCode}
                                options={branchOptions}
                                optionValue={(option) => option.kode_cabang}
                                optionLabel={(option) => option.label}
                                onChange={selectBranch}
                                placeholder="Pilih KCU atau kantor cabang"
                                emptyLabel="Pilih KCU atau kantor cabang"
                                required
                                error={form.errors.kode_cabang}
                            />
                            <p className="simpeg-quota-position-note">
                                {selectedBranch
                                    ? 'Jabatan aktif yang terdaftar pada ' + selectedBranch.label + ' akan ditampilkan di bawah.'
                                    : 'Pilih KCU atau kantor cabang terlebih dahulu untuk memuat daftar jabatannya.'}
                            </p>
                        </div>

                        {form.errors.quotas && (
                            <p className="simpeg-quota-error simpeg-quota-batch-error" role="alert">{form.errors.quotas}</p>
                        )}

                        {selectedBranchCode && (
                            <div className="simpeg-quota-position-panel">
                                <div className="simpeg-quota-position-panel-heading">
                                    <div>
                                        <p className="simpeg-quota-section-kicker">Jabatan pada kantor terpilih</p>
                                        <h3 className="simpeg-quota-position-title">
                                            {selectedBranch?.nama_cabang || selectedBranchCode}
                                        </h3>
                                    </div>
                                    <div className="simpeg-quota-position-panel-tools">
                                        <span className="simpeg-quota-count">{selectedBranchPositions.length} jabatan</span>
                                        <button
                                            type="button"
                                            className="simpeg-quota-add-toggle"
                                            onClick={() => setShowCreateForm((visible) => !visible)}
                                            disabled={!selectedBranchCode}
                                        >
                                            <Plus aria-hidden="true" />
                                            {showCreateForm ? 'Tutup tambah' : 'Tambah kuota'}
                                        </button>
                                    </div>
                                </div>

                                {showCreateForm && (
                                    <div className="simpeg-quota-create-box">
                                        <div>
                                            <p className="simpeg-quota-section-kicker">Create</p>
                                            <h4 className="simpeg-quota-create-title">Tambah kuota baru</h4>
                                            <p className="simpeg-quota-position-note">
                                                Masukkan nama jabatan bebas, tanpa mengubah data master jabatan.
                                            </p>
                                        </div>
                                        <form onSubmit={submitCreate} className="simpeg-quota-create-form">
                                            <div>
                                                <label htmlFor="quota-create-name" className="simpeg-quota-label">Nama jabatan</label>
                                                <input
                                                    id="quota-create-name"
                                                    type="text"
                                                    maxLength="100"
                                                    value={createPositionName}
                                                    onChange={(event) => {
                                                        setCreatePositionName(event.target.value);
                                                        createForm.setData('nama_jabatan', event.target.value);
                                                    }}
                                                    placeholder="Contoh: Jabatan A"
                                                    className="simpeg-input simpeg-quota-number"
                                                    required
                                                />
                                                {createForm.errors.nama_jabatan && <p className="simpeg-quota-error" role="alert">{createForm.errors.nama_jabatan}</p>}
                                            </div>
                                            <div>
                                                <label htmlFor="quota-create-count" className="simpeg-quota-label">Kuota pegawai</label>
                                                <input
                                                    id="quota-create-count"
                                                    type="number"
                                                    min="0"
                                                    max="100000"
                                                    value={createQuota}
                                                    onChange={(event) => {
                                                        setCreateQuota(event.target.value);
                                                        createForm.setData('kuota_pegawai', event.target.value);
                                                    }}
                                                    className="simpeg-input simpeg-quota-number"
                                                    required
                                                />
                                                {createForm.errors.kuota_pegawai && <p className="simpeg-quota-error" role="alert">{createForm.errors.kuota_pegawai}</p>}
                                            </div>
                                            <button type="submit" className="simpeg-primary simpeg-quota-create-button" disabled={createForm.processing}>
                                                <Plus aria-hidden="true" />
                                                {createForm.processing ? 'Menambahkan...' : 'Tambah kuota'}
                                            </button>
                                        </form>
                                    </div>
                                )}

                                {selectedBranchPositions.length === 0 ? (
                                    <p className="simpeg-quota-empty">
                                        Belum ada jabatan aktif atau custom yang terhubung dengan kantor ini.
                                    </p>
                                ) : (
                                    <div className="simpeg-quota-position-list">
                                        {selectedBranchPositions.map((position, index) => {
                                            const quotaError = errorForBatchQuota(form.errors, index);
                                            const savedQuota = position.saved_quota || existingQuotaByPosition.get(String(position.kode_jabatan));
                                            const positionKey = position.custom_id ? 'custom:'+position.custom_id : String(position.kode_jabatan);
                                            const isDeleting = deletingPositionId === positionKey;
                                            const isEditing = editingPositionKey === positionKey;
                                            const isSaving = savingPositionKey === positionKey;

                                            return (
                                                <div className="simpeg-quota-position-row" key={positionKey}>
                                                    <div className="simpeg-quota-position-detail">
                                                        {isEditing ? (
                                                            <div className="simpeg-quota-position-name-edit">
                                                                <input
                                                                    type="text"
                                                                    maxLength="100"
                                                                    value={editingPositionName}
                                                                    onChange={(event) => setEditingPositionName(event.target.value)}
                                                                    className="simpeg-input simpeg-quota-name-input"
                                                                    autoFocus
                                                                />
                                                                <button
                                                                    type="button"
                                                                    className="simpeg-quota-name-save"
                                                                    onClick={() => savePositionName(position, index)}
                                                                    disabled={isSaving || !editingPositionName.trim()}
                                                                    title="Simpan nama jabatan"
                                                                >
                                                                    <Check aria-hidden="true" />
                                                                </button>
                                                                <button
                                                                    type="button"
                                                                    className="simpeg-quota-name-cancel"
                                                                    onClick={cancelEditPosition}
                                                                    disabled={isSaving}
                                                                    title="Batalkan edit"
                                                                >
                                                                    <X aria-hidden="true" />
                                                                </button>
                                                            </div>
                                                        ) : (
                                                            <div className="simpeg-quota-position-name-row">
                                                                <strong>{position.nama_jabatan}</strong>
                                                                <button
                                                                    type="button"
                                                                    className="simpeg-quota-edit"
                                                                    onClick={() => startEditPosition(position)}
                                                                    disabled={isDeleting || form.processing || savingPositionKey !== null}
                                                                    title="Edit nama jabatan"
                                                                >
                                                                    <Pencil aria-hidden="true" />
                                                                    <span>Edit</span>
                                                                </button>
                                                            </div>
                                                        )}
                                                        <span>
                                                            {position.custom_id ? 'Jabatan custom' : `Kode ${position.kode_jabatan}`} · {position.lingkup || '-'} · {position.group_jabatan || '-'}
                                                        </span>
                                                        <em className={savedQuota ? 'is-configured' : ''}>
                                                            {savedQuota?.source === 'master'
                                                                ? 'Mengikuti kuota master'
                                                                : savedQuota
                                                                    ? 'Kuota kantor tersimpan'
                                                                    : 'Belum diatur untuk kantor ini'}
                                                        </em>
                                                    </div>
                                                    <div className="simpeg-quota-position-input">
                                                        <label htmlFor={'quota-count-' + positionKey} className="simpeg-quota-label">
                                                            Kuota pegawai
                                                        </label>
                                                        <div className="simpeg-quota-position-input-row">
                                                            <input
                                                                id={'quota-count-' + positionKey}
                                                                type="number"
                                                                min="0"
                                                                max="100000"
                                                                value={form.data.quotas[index]?.kuota_pegawai ?? 0}
                                                                onChange={(event) => updateQuota(index, event.target.value)}
                                                                className="simpeg-input simpeg-quota-number"
                                                                required
                                                            />
                                                            <button
                                                                type="button"
                                                                className="simpeg-quota-delete"
                                                                onClick={() => deleteQuota(position)}
                                                                disabled={isDeleting || form.processing}
                                                                title="Hapus kuota"
                                                            >
                                                                <Trash2 aria-hidden="true" />
                                                                <span>{isDeleting ? 'Menghapus...' : 'Hapus'}</span>
                                                            </button>
                                                        </div>
                                                        {quotaError && <p className="simpeg-quota-error" role="alert">{quotaError}</p>}
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>
                                )}

                                <div className="simpeg-quota-form-actions">
                                    <button
                                        type="submit"
                                        className="simpeg-primary simpeg-quota-save"
                                        disabled={form.processing || !selectedBranchCode || selectedBranchPositions.length === 0}
                                    >
                                        <Check aria-hidden="true" />
                                        {form.processing ? 'Menyimpan...' : 'Simpan semua kuota'}
                                    </button>
                                </div>
                            </div>
                        )}

                    </form>
                </section>

                <p className="simpeg-quota-note">
                    Pengaturan disimpan untuk kantor yang dipilih. Nilai kuota 0 tetap dicatat agar jabatan tersebut tidak mendapat kuota pada kantor itu.
                </p>
            </div>
        </LegacyPageShell>
    );
}
export default function QuotaJabatan({ branchOptions = [], branchPositionOptions = [], positionOptions = [], quotas = [], filters = {} }) {
    return <QuotaJabatanBatch branchOptions={branchOptions} branchPositionOptions={branchPositionOptions} positionOptions={positionOptions} quotas={quotas} />;

    const { flash } = usePage().props;
    const [editingKey, setEditingKey] = useState(null);
    const [deletingKey, setDeletingKey] = useState(null);
    const [filterForm, setFilterForm] = useState({
        search: String(filters.search || ''),
        kode_cabang: String(filters.kode_cabang || ''),
        jabatan_id: String(filters.jabatan_id || ''),
    });
    const form = useForm(emptyForm);

    useEffect(() => {
        setFilterForm({
            search: String(filters.search || ''),
            kode_cabang: String(filters.kode_cabang || ''),
            jabatan_id: String(filters.jabatan_id || ''),
        });
    }, [filters.search, filters.kode_cabang, filters.jabatan_id]);

    const selectedQuota = useMemo(
        () => quotas.find((quota) => quota.row_key === editingKey),
        [editingKey, quotas],
    );
    const selectedPosition = positionOptions.find((position) => String(position.kode_jabatan) === String(form.data.jabatan_id));
    const selectedBranch = branchOptions.find((branch) => String(branch.kode_cabang) === String(form.data.kode_cabang));

    function editQuota(quota) {
        setEditingKey(quota.row_key);
        form.setData({
            kode_cabang: quota.kode_cabang,
            jabatan_id: String(quota.jabatan_id),
            kuota_pegawai: quota.kuota_pegawai,
        });
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function resetForm() {
        setEditingKey(null);
        form.reset();
        form.clearErrors();
    }

    function submit(event) {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: resetForm,
        };

        if (selectedQuota?.id) {
            form.put(`/config/kuota-jabatan/${selectedQuota.id}`, options);
            return;
        }

        form.post('/config/kuota-jabatan', options);
    }

    function applyFilters(event) {
        event.preventDefault();

        const params = {};
        const search = filterForm.search.trim();

        if (search) params.search = search;
        if (filterForm.kode_cabang) params.kode_cabang = filterForm.kode_cabang;
        if (filterForm.jabatan_id) params.jabatan_id = filterForm.jabatan_id;

        router.get('/config/kuota-jabatan', params, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    }

    function resetFilters() {
        setFilterForm({ search: '', kode_cabang: '', jabatan_id: '' });
        router.get('/config/kuota-jabatan', {}, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    }

    function deleteQuota(quota) {
        const confirmed = window.confirm(
            `Set kuota ${quota.nama_jabatan} untuk ${quota.nama_cabang} (${quota.kode_cabang}) menjadi 0? Data jabatan master tidak dihapus.`,
        );

        if (!confirmed) return;

        setDeletingKey(quota.row_key);
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                if (editingKey === quota.row_key) resetForm();
            },
            onFinish: () => setDeletingKey(null),
        };

        if (quota.id) {
            router.delete(`/config/kuota-jabatan/${quota.id}`, options);
            return;
        }

        router.post('/config/kuota-jabatan', {
            kode_cabang: quota.kode_cabang,
            jabatan_id: quota.jabatan_id,
            kuota_pegawai: 0,
        }, options);
    }

    return (
        <LegacyPageShell title="Kuota Jabatan">
            <Head title="Kuota Jabatan" />
            <div className="simpeg-quota-page">
                <div className="simpeg-quota-intro">
                    <div>
                        <p className="simpeg-quota-eyebrow">Administrasi jabatan</p>
                        <h1 className="simpeg-quota-title">Kuota jabatan</h1>
                        <p className="simpeg-quota-description">
                            Atur jumlah posisi per cabang tanpa mengubah data jabatan dan pegawai yang sudah ada.
                        </p>
                    </div>
                </div>

                {flash?.success && <div className="simpeg-quota-flash" role="status">{flash.success}</div>}

                <section className="simpeg-quota-card simpeg-quota-form-card" aria-labelledby="quota-form-title">
                    <div className="simpeg-quota-card-heading">
                        <div>
                            <p className="simpeg-quota-section-kicker">Konfigurasi per cabang</p>
                            <h2 id="quota-form-title" className="simpeg-quota-section-title">
                                {selectedQuota ? 'Ubah kuota jabatan' : 'Tambah kuota jabatan'}
                            </h2>
                        </div>
                        {selectedQuota && <button type="button" className="simpeg-quota-cancel" onClick={resetForm}>Batal ubah</button>}
                    </div>

                    <form onSubmit={submit} className="simpeg-quota-form">
                        <SelectCombobox
                            id="quota-branch"
                            name="kode_cabang"
                            label="Kode cabang"
                            value={form.data.kode_cabang}
                            options={branchOptions}
                            optionValue={(option) => option.kode_cabang}
                            optionLabel={(option) => option.label}
                            onChange={(value) => form.setData('kode_cabang', value)}
                            placeholder="Pilih cabang"
                            emptyLabel="Pilih cabang"
                            required
                            error={form.errors.kode_cabang}
                        />
                        <SelectCombobox
                            id="quota-position"
                            name="jabatan_id"
                            label="Jabatan"
                            value={form.data.jabatan_id}
                            options={positionOptions}
                            optionValue={(option) => option.kode_jabatan}
                            optionLabel={(option) => option.nama_jabatan}
                            optionDescription={(option) => [
                                `Kode ${option.kode_jabatan}`,
                                `Lingkup ${option.lingkup || '-'}`,
                                option.group_jabatan || 'Kelompok tidak tercatat',
                            ].join(' · ')}
                            onChange={(value) => form.setData('jabatan_id', value)}
                            placeholder="Pilih jabatan"
                            emptyLabel="Pilih jabatan"
                            required
                            error={form.errors.jabatan_id}
                        />
                        <div>
                            <label htmlFor="quota-count" className="simpeg-quota-label">Kuota pegawai</label>
                            <input
                                id="quota-count"
                                type="number"
                                min="0"
                                max="100000"
                                value={form.data.kuota_pegawai}
                                onChange={(event) => form.setData('kuota_pegawai', event.target.value)}
                                className="simpeg-input simpeg-quota-number"
                                required
                            />
                            {form.errors.kuota_pegawai && <p className="simpeg-quota-error" role="alert">{form.errors.kuota_pegawai}</p>}
                        </div>
                        <div className="simpeg-quota-form-actions">
                            <button type="submit" className="simpeg-primary simpeg-quota-save" disabled={form.processing}>
                                <Check aria-hidden="true" />
                                {form.processing ? 'Menyimpan...' : selectedQuota ? 'Simpan perubahan' : 'Simpan kuota'}
                            </button>
                        </div>
                    </form>
                    <p className="simpeg-quota-position-note" aria-live="polite">
                        {selectedPosition
                            ? `Kode ${selectedPosition.kode_jabatan} · Lingkup ${selectedPosition.lingkup || '-'} · Kelompok ${selectedPosition.group_jabatan || '-'}. ${selectedBranch ? `Lokasi kuota: ${selectedBranch.label}.` : 'Pilih kantor atau wilayah pada kolom Kode cabang.'} ${selectedQuota?.source === 'master' ? 'Nilai saat ini mengikuti kuota master; simpan untuk membuat kuota khusus cabang.' : ''}`
                            : 'Pilih jabatan untuk melihat lingkup dan kelompoknya. Tentukan kantor atau wilayah pada kolom Kode cabang.'}
                    </p>
                </section>

                <section className="simpeg-quota-card simpeg-quota-filter-card" aria-labelledby="quota-filter-title">
                    <div className="simpeg-quota-card-heading">
                        <div>
                            <p className="simpeg-quota-section-kicker">Penyaringan daftar</p>
                            <h2 id="quota-filter-title" className="simpeg-quota-section-title">Cari kuota jabatan</h2>
                        </div>
                        <p className="simpeg-quota-filter-help">Jabatan tidak wajib dipilih untuk menampilkan semua kuota.</p>
                    </div>

                    <form onSubmit={applyFilters} className="simpeg-quota-filter-form">
                        <div>
                            <label htmlFor="quota-filter-search" className="simpeg-quota-label">Pencarian</label>
                            <input
                                id="quota-filter-search"
                                type="search"
                                value={filterForm.search}
                                onChange={(event) => setFilterForm((current) => ({ ...current, search: event.target.value }))}
                                placeholder="Kode/nama cabang atau jabatan"
                                className="simpeg-input simpeg-quota-filter-input"
                            />
                        </div>
                        <SearchableCombobox
                            id="quota-filter-branch"
                            name="kode_cabang"
                            label="Cabang"
                            value={filterForm.kode_cabang}
                            options={[{ kode_cabang: '', label: 'Semua cabang' }, ...branchOptions]}
                            optionValue={(option) => option.kode_cabang}
                            optionLabel={(option) => option.label}
                            onChange={(value) => setFilterForm((current) => ({ ...current, kode_cabang: String(value ?? '') }))}
                            placeholder="Semua cabang"
                            emptyMessage="Cabang tidak ditemukan."
                        />
                        <SearchableCombobox
                            id="quota-filter-position"
                            name="jabatan_id"
                            label="Jabatan (opsional)"
                            value={filterForm.jabatan_id}
                            options={[{ kode_jabatan: '', nama_jabatan: 'Semua jabatan', group_jabatan: '', lingkup: '' }, ...positionOptions]}
                            optionValue={(option) => option.kode_jabatan}
                            optionLabel={(option) => option.nama_jabatan}
                            optionDescription={(option) => option.kode_jabatan ? [
                                `Kode ${option.kode_jabatan}`,
                                `Lingkup ${option.lingkup || '-'}`,
                                option.group_jabatan || 'Kelompok tidak tercatat',
                            ].join(' · ') : 'Tampilkan semua jabatan'}
                            onChange={(value) => setFilterForm((current) => ({ ...current, jabatan_id: String(value ?? '') }))}
                            placeholder="Semua jabatan"
                            emptyMessage="Jabatan tidak ditemukan."
                        />
                        <div className="simpeg-quota-filter-actions">
                            <button type="submit" className="simpeg-primary simpeg-quota-filter-apply">
                                Terapkan
                            </button>
                            <button type="button" className="simpeg-quota-filter-reset" onClick={resetFilters}>
                                Reset
                            </button>
                        </div>
                    </form>
                </section>

                <section className="simpeg-quota-card simpeg-quota-list-card" aria-labelledby="quota-list-title">
                    <div className="simpeg-quota-card-heading">
                        <div>
                            <p className="simpeg-quota-section-kicker">Dipakai dashboard</p>
                            <h2 id="quota-list-title" className="simpeg-quota-section-title">Kuota per cabang dan jabatan</h2>
                        </div>
                        <span className="simpeg-quota-count">{quotas.length} kuota ditemukan</span>
                    </div>

                    <div className="simpeg-quota-table-wrap">
                        <table className="simpeg-quota-table">
                            <thead>
                                <tr>
                                    <th>Kode cabang</th>
                                    <th>Jabatan</th>
                                    <th>Kelompok</th>
                                    <th className="text-right">Kuota</th>
                                    <th className="text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                {quotas.length === 0 ? (
                                    <tr><td colSpan="5" className="simpeg-quota-empty">{filters.search || filters.kode_cabang || filters.jabatan_id ? 'Tidak ada kuota yang sesuai dengan filter.' : 'Belum ada kuota aktif untuk jabatan yang sedang terisi.'}</td></tr>
                                ) : quotas.map((quota) => (
                                    <tr key={quota.row_key}>
                                        <td>
                                            <strong>{quota.kode_cabang}</strong>
                                            <span>{quota.nama_cabang}</span>
                                        </td>
                                        <td>
                                            <strong>{quota.nama_jabatan}</strong>
                                            <span>Kode {quota.jabatan_id} · Lingkup {quota.lingkup || '-'} · {quota.group_jabatan || '-'}</span>
                                        </td>
                                        <td>
                                            <strong>{quota.group_jabatan || '-'}</strong>
                                            <span className={`simpeg-quota-source is-${quota.source}`}>
                                                {quota.source === 'master' ? 'Bawaan master' : 'Pengaturan cabang'}
                                            </span>
                                        </td>
                                        <td className="text-right"><strong>{quota.kuota_pegawai}</strong></td>
                                        <td className="text-right">
                                            <div className="simpeg-quota-row-actions">
                                                <button type="button" className="simpeg-quota-edit" onClick={() => editQuota(quota)}>
                                                    <Pencil aria-hidden="true" />
                                                    Edit
                                                </button>
                                                <button
                                                    type="button"
                                                    className="simpeg-quota-delete"
                                                    onClick={() => deleteQuota(quota)}
                                                    disabled={deletingKey === quota.row_key}
                                                >
                                                    <Trash2 aria-hidden="true" />
                                                    {deletingKey === quota.row_key ? 'Menghapus...' : 'Hapus'}
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <div className="simpeg-quota-mobile-list">
                        {quotas.length === 0 ? <p className="simpeg-quota-empty">{filters.search || filters.kode_cabang || filters.jabatan_id ? 'Tidak ada kuota yang sesuai dengan filter.' : 'Belum ada kuota aktif untuk jabatan yang sedang terisi.'}</p> : quotas.map((quota) => (
                            <article key={quota.row_key} className="simpeg-quota-mobile-item">
                                <div>
                                    <strong>{quota.nama_jabatan}</strong>
                                    <span>{quota.kode_cabang} · {quota.nama_cabang}</span>
                                    <span>Kode {quota.jabatan_id} · Lingkup {quota.lingkup || '-'} · {quota.group_jabatan || '-'}</span>
                                    <span>{quota.group_jabatan || 'Kelompok belum ditentukan'}</span>
                                    <span className={`simpeg-quota-source is-${quota.source}`}>
                                        {quota.source === 'master' ? 'Bawaan master' : 'Pengaturan cabang'}
                                    </span>
                                </div>
                                <div className="simpeg-quota-mobile-meta">
                                    <strong>{quota.kuota_pegawai}</strong>
                                    <div className="simpeg-quota-mobile-actions">
                                        <button type="button" className="simpeg-quota-edit" onClick={() => editQuota(quota)}>
                                            <Pencil aria-hidden="true" />
                                            Edit
                                        </button>
                                        <button
                                            type="button"
                                            className="simpeg-quota-delete"
                                            onClick={() => deleteQuota(quota)}
                                            disabled={deletingKey === quota.row_key}
                                        >
                                            <Trash2 aria-hidden="true" />
                                            {deletingKey === quota.row_key ? 'Menghapus...' : 'Hapus'}
                                        </button>
                                    </div>
                                </div>
                            </article>
                        ))}
                    </div>
                </section>

                <p className="simpeg-quota-note">
                    Kuota bawaan master juga ditampilkan di sini. Edit akan membuat atau memperbarui kuota khusus cabang; hapus menyetel kuota cabang menjadi 0 tanpa menghapus master jabatan.
                </p>
            </div>
        </LegacyPageShell>
    );
}
