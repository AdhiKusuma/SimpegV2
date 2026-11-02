import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Check, Pencil, SlidersHorizontal, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import SelectCombobox from '../../Components/SelectCombobox';
import LegacyPageShell from '../../Components/LegacyPageShell';

const emptyForm = {
    kode_cabang: '',
    jabatan_id: '',
    kuota_pegawai: 0,
};

export default function QuotaJabatan({ branchOptions = [], positionOptions = [], quotas = [] }) {
    const { flash } = usePage().props;
    const [editingKey, setEditingKey] = useState(null);
    const [deletingKey, setDeletingKey] = useState(null);
    const form = useForm(emptyForm);

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
                    <div className="simpeg-quota-intro-icon" aria-hidden="true">
                        <SlidersHorizontal />
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

                <section className="simpeg-quota-card simpeg-quota-list-card" aria-labelledby="quota-list-title">
                    <div className="simpeg-quota-card-heading">
                        <div>
                            <p className="simpeg-quota-section-kicker">Dipakai dashboard</p>
                            <h2 id="quota-list-title" className="simpeg-quota-section-title">Kuota per cabang dan jabatan</h2>
                        </div>
                        <span className="simpeg-quota-count">{quotas.length} kuota aktif</span>
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
                                    <tr><td colSpan="5" className="simpeg-quota-empty">Belum ada kuota aktif untuk jabatan yang sedang terisi.</td></tr>
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
                        {quotas.length === 0 ? <p className="simpeg-quota-empty">Belum ada kuota aktif untuk jabatan yang sedang terisi.</p> : quotas.map((quota) => (
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
