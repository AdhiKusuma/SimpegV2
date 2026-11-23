import { Head, Link, useForm } from '@inertiajs/react';
import { FilePenLine, Printer } from 'lucide-react';
import { useEffect, useState } from 'react';
import IconAction from '../../Components/IconAction';
import LegacyPageShell from '../../Components/LegacyPageShell';
import ProfileAvatar from '../../Components/ProfileAvatar';
import SearchableCombobox from '../../Components/SearchableCombobox';

const religionOptions = ['Islam', 'Protestan', 'Katolik', 'Hindu', 'Budha', 'KongHuCu'];
const genderOptions = ['Laki-laki', 'Perempuan'];
const employmentStatusOptions = ['Tetap', 'Kontrak', 'Outsource'];

const tabs = [
    ['biodata', 'Biodata'],
    ['family', 'Keluarga'],
    ['history', 'Data kepegawaian'],
];

function valueOrDash(value) {
    return value || '-';
}

function formatIndonesianDate(value) {
    if (!value) return '-';

    const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    const raw = String(value).trim();
    const dateParts = raw.match(/^(\d{2})-(\d{2})-(\d{4})$/);

    if (dateParts) {
        return `${Number(dateParts[1])} ${months[Number(dateParts[2]) - 1] || dateParts[2]} ${dateParts[3]}`;
    }

    const date = value instanceof Date ? value : new Date(raw);

    return Number.isNaN(date.getTime())
        ? raw
        : `${date.getDate()} ${months[date.getMonth()]} ${date.getFullYear()}`;
}

function dateInputValue(value) {
    if (!value) return '';
    const parts = String(value).split('-');
    return parts.length === 3 && parts[0].length === 2 ? `${parts[2]}-${parts[1]}-${parts[0]}` : value;
}

function DataTable({ title, items = [], columns, empty = 'Belum ada data.', action = null, rowAction = null }) {
    const hasRowActions = Boolean(rowAction);

    return (
        <section className="simpeg-profile-panel" aria-labelledby={`${title}-title`}>
            <div className="simpeg-profile-panel-heading">
                <div>
                    <p className="simpeg-profile-section-kicker">Data tersimpan</p>
                    <h2 id={`${title}-title`} className="simpeg-text font-medium">{title}</h2>
                </div>
                <div className="flex items-center gap-3">
                    {action}
                    <span className="simpeg-profile-count" aria-label={`${items.length} data`}>{items.length}</span>
                </div>
            </div>
            <div className="simpeg-profile-table-wrap">
                <table className="simpeg-profile-table min-w-full text-left text-sm">
                    <thead className="border-b border-slate-100 text-xs uppercase tracking-wide text-slate-500">
                        <tr>{columns.map(([label]) => <th key={label} className="whitespace-nowrap px-5 py-4 font-semibold">{label}</th>)}{hasRowActions && <th className="w-[13rem] whitespace-nowrap px-5 py-4 text-right font-semibold">Aksi</th>}</tr>
                    </thead>
                    <tbody className="simpeg-profile-table-body divide-y divide-slate-100">
                        {items.length === 0 ? <tr><td colSpan={columns.length + (hasRowActions ? 1 : 0)} className="px-5 py-12 text-center text-sm text-slate-500">{empty}</td></tr> : items.map((item, index) => <tr key={item.id || item.id_peg || index} className="align-top transition hover:bg-slate-50">{columns.map(([label, field]) => <td key={label} className="px-5 py-4 text-sm text-slate-700">{valueOrDash(item[field])}</td>)}{hasRowActions && <td className="w-[13rem] px-5 py-4 text-right align-top">{rowAction(item)}</td>}</tr>)}
                    </tbody>
                </table>
            </div>
        </section>
    );
}

function BioTab({ employee }) {
    const fields = [
        ['NIK', employee.nik],
        ['Nama lengkap', employee.nama],
        ['Tempat, tanggal lahir', [employee.tempat_lhr, employee.tgl_lhr].filter(Boolean).join(', ')],
        ['Jenis kelamin', employee.jk],
        ['Agama', employee.agama],
        ['Golongan darah', employee.gol_darah],
        ['Status nikah', employee.status_nikah],
        ['Status kepegawaian', employee.status_kepeg],
        ['Alamat', employee.alamat],
        ['Telepon', employee.telp],
        ['Email', employee.email],
        ['TMT kerja', employee.tmt_kerja],
        ['Tanggal pensiun', employee.tgl_pensiun],
    ];

    return (
        <section className="simpeg-profile-panel p-4 sm:p-6" aria-labelledby="biodata-title">
            <div className="simpeg-profile-panel-heading-block">
                <p className="simpeg-profile-section-kicker">Informasi dasar</p>
                <h2 id="biodata-title" className="simpeg-text text-lg font-medium">Data biodata</h2>
                <p className="simpeg-muted mt-1 text-sm">Informasi yang tersimpan pada data pegawai.</p>
            </div>
            <dl className="simpeg-profile-fields mt-6">
                {fields.map(([label, value]) => <div key={label} className="simpeg-profile-field"><dt className="text-sm simpeg-muted">{label}</dt><dd className="mt-1 break-words text-sm font-medium text-slate-800">{valueOrDash(value)}</dd></div>)}
            </dl>
        </section>
    );
}

function FamilyTab({ family, familyRequests = [], canRequestFamily, canManageFamily, employeeId }) {
    const [deleteTarget, setDeleteTarget] = useState(null);
    const deleteForm = useForm({ reason: '' });
    const familyKeys = { spouses: 'id_si', children: 'id_anak', parents: 'id_ortu' };
    const canEditFamily = canRequestFamily || canManageFamily;
    const addLabels = { spouses: 'Tambah pasangan', children: 'Tambah anak', parents: 'Tambah orang tua' };

    const requestAction = (type, label) => canEditFamily ? (
        <Link href={'/keluarga/' + type + '/create?uid=' + encodeURIComponent(employeeId)} className="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:border-blue-600 hover:text-blue-600 focus:outline-none focus:ring-4 focus:ring-blue-600/10">{canRequestFamily ? label : addLabels[type]}</Link>
    ) : null;

    const rowAction = (type) => canEditFamily ? (item) => {
        const recordId = item[familyKeys[type]];
        if (!recordId) {
            return null;
        }

        return <div className="flex min-w-[10.5rem] items-center justify-end gap-2">
            <IconAction href={`/keluarga/${type}/${encodeURIComponent(recordId)}/edit?uid=${encodeURIComponent(employeeId)}`} label={canRequestFamily ? 'Ajukan ubah' : 'Edit'} />
            <IconAction variant="delete" onClick={() => { setDeleteTarget({ type, item }); deleteForm.setData('reason', ''); }} label={canRequestFamily ? 'Ajukan hapus' : 'Hapus'} />
        </div>;
    } : null;

    const deleteTitle = canRequestFamily ? 'Ajukan penghapusan data?' : 'Hapus data keluarga?';
    const deleteDescription = canRequestFamily
        ? <>Pengajuan penghapusan <strong>{deleteTarget?.item.nama}</strong> akan dikirim ke kepala cabang. Data tetap tampil sampai disetujui.</>
        : <>Data <strong>{deleteTarget?.item.nama}</strong> akan disimpan ke Recycle Bin sebelum dihapus. Alasan wajib diisi.</>;
    const deleteSubmitLabel = canRequestFamily ? 'Kirim pengajuan' : 'Ya, Hapus';

    function submitDelete(event) {
        event.preventDefault();

        if (!deleteTarget) {
            return;
        }

        const recordId = deleteTarget.item[familyKeys[deleteTarget.type]];
        deleteForm.delete(`/keluarga/${deleteTarget.type}/${encodeURIComponent(recordId)}`, {
            preserveScroll: true,
            onSuccess: () => {
                setDeleteTarget(null);
                deleteForm.reset();
            },
        });
    }

    return (
        <div className="space-y-4">
            {canRequestFamily && familyRequests.length > 0 && <section className="simpeg-profile-panel p-4 sm:p-6" aria-labelledby="family-requests-title">
                <div className="simpeg-profile-panel-heading-block">
                    <p className="simpeg-profile-section-kicker">Status pengajuan</p>
                    <h2 id="family-requests-title" className="simpeg-text text-lg font-medium">Perubahan yang diajukan</h2>
                    <p className="simpeg-muted mt-1 text-sm">Data aktif tidak berubah sebelum pengajuan disetujui.</p>
                </div>
                <div className="mt-5 space-y-3">
                    {familyRequests.map((request) => <div key={request.id_edit} className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 px-4 py-3">
                        <div>
                            <p className="text-sm font-semibold text-slate-800">{request.label}</p>
                            <p className="mt-1 text-sm simpeg-muted">{request.nama} · {request.submitted_at || '-'}</p>
                        </div>
                        <span className="rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-800">{request.status}</span>
                    </div>)}
                </div>
            </section>}
            <DataTable title="Pasangan (suami/istri)" items={family.spouses} columns={[["Nama", 'nama'], ['TTL', 'tgl_lhr'], ['Pendidikan', 'pendidikan'], ['Pekerjaan', 'pekerjaan'], ['Status', 'status_hub']]} action={requestAction('spouses', 'Ajukan pasangan')} rowAction={rowAction('spouses')} />
            <DataTable title="Anak" items={family.children} columns={[["Nama", 'nama'], ['TTL', 'tgl_lhr'], ['Pendidikan', 'pendidikan'], ['Pekerjaan', 'pekerjaan'], ['Anak ke', 'anak_ke']]} action={requestAction('children', 'Ajukan anak')} rowAction={rowAction('children')} />
            <DataTable title="Orang tua" items={family.parents} columns={[["Nama", 'nama'], ['TTL', 'tgl_lhr'], ['Pendidikan', 'pendidikan'], ['Pekerjaan', 'pekerjaan'], ['Hubungan', 'status_hub']]} action={requestAction('parents', 'Ajukan orang tua')} rowAction={rowAction('parents')} />

            {deleteTarget && <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" role="presentation"><div className="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="profile-family-delete-title"><h2 id="profile-family-delete-title" className="text-lg font-extrabold text-slate-900">{deleteTitle}</h2><p className="mt-2 text-sm text-slate-600">{deleteDescription}</p><form onSubmit={submitDelete} className="mt-5"><label htmlFor="profile-family-delete-reason" className="mb-2 block text-sm font-semibold text-slate-800">Alasan penghapusan</label><textarea id="profile-family-delete-reason" value={deleteForm.data.reason} onChange={(event) => deleteForm.setData('reason', event.target.value)} rows="3" required className="w-full rounded-xl border border-slate-200 p-3 text-sm outline-none focus:border-blue-600 focus:ring-4 focus:ring-blue-600/10" placeholder="Contoh: data sudah tidak sesuai" />{deleteForm.errors.reason && <p className="mt-2 text-sm text-red-600" role="alert">{deleteForm.errors.reason}</p>}<div className="mt-5 flex justify-end gap-3"><button type="button" onClick={() => setDeleteTarget(null)} className="min-h-11 rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-600">Batal</button><button type="submit" disabled={deleteForm.processing} className="min-h-11 rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-bold text-white hover:bg-slate-700 disabled:opacity-60">{deleteForm.processing ? (canRequestFamily ? 'Mengirim...' : 'Menghapus...') : deleteSubmitLabel}</button></div></form></div></div>}
        </div>
    );
}

function HistoryTab({ history }) {
    const latestEducation = history.education?.slice(0, 1) || [];

    return (
        <div className="space-y-4">
            <DataTable title="Pengangkatan" items={history.appointment} columns={[["Jenis", 'jns_mutasi'], ['Tanggal', 'tgl_mutasi'], ['Nomor SK', 'no_mutasi'], ['TMT mulai', 'tmt'], ['TMT selesai', 'tmt_selesai']]} />
            <DataTable title="Diklat" items={history.training} columns={[["Diklat", 'diklat'], ['Penyelenggara', 'penyelenggara'], ['Tempat', 'tempat'], ['Tahun', 'tahun']]} />
            <DataTable title="Sertifikasi" items={history.certification} columns={[["Sertifikasi", 'sertifikasi'], ['Penyelenggara', 'penyelenggara'], ['Tanggal', 'tgl_sertifikat'], ['Berlaku sampai', 'tgl_expired']]} />
            <DataTable title="Pelanggaran" items={history.discipline} columns={[["Pelanggaran", 'hukuman'], ['Tanggal SK', 'tgl_sk'], ['Nomor SK', 'no_sk'], ['Keterangan', 'keterangan']]} />
            <DataTable title="Jabatan" items={history.position} columns={[["Jabatan", 'jabatan'], ['Unit kerja', 'unit_kerja'], ['Mulai', 'tmt_jabatan'], ['Selesai', 'sampai_tgl'], ['Status', 'status_jab']]} />
            <DataTable title="Pendidikan terakhir" items={latestEducation} columns={[["Jenjang", 'jenjang'], ['Sekolah', 'nama_sekolah'], ['Jurusan', 'jurusan'], ['Tanggal ijazah', 'tgl_ijazah'], ['Tahun lulus', 'th_lulus']]} />
        </div>
    );
}

export default function Profile({ employee, family, familyRequests = [], canRequestFamily, canRequestBiodata, canManageBiodata, canManageFamily, history, flash = {}, readOnly = false, backUrl = null, pageTitle = 'Profil Saya', pageEyebrow = 'Profil Saya', pageHeading = 'Ruang profil', pageDescription = 'Kelola informasi pribadi, keluarga, dan data kepegawaian yang terkait dengan akun ini.', canManageEmployee = false }) {
    const [activeTab, setActiveTab] = useState('biodata');
    const [biodataDialogOpen, setBiodataDialogOpen] = useState(false);
    const [fotoPreview, setFotoPreview] = useState(employee?.foto_url || null);
    const biodataForm = useForm({ nip: '', nama: '', tempat_lhr: '', tgl_lhr: '', jk: '', agama: '', gol_darah: '', status_nikah: '', status_kepeg: '', alamat: '', telp: '', email: '', request_comment: '', foto: null });

    useEffect(() => {
        if (!biodataForm.data.foto) return undefined;

        const previewUrl = URL.createObjectURL(biodataForm.data.foto);
        setFotoPreview(previewUrl);

        return () => URL.revokeObjectURL(previewUrl);
    }, [biodataForm.data.foto]);

    function openBiodataRequest() {
        setFotoPreview(employee.foto_url || null);
        biodataForm.setData({
            nip: employee.nik || '', nama: employee.nama || '', tempat_lhr: employee.tempat_lhr || '',
            tgl_lhr: dateInputValue(employee.tgl_lhr), jk: employee.jk || '', agama: employee.agama || '',
            gol_darah: employee.gol_darah || '', status_nikah: employee.status_nikah || '', status_kepeg: employee.status_kepeg || '', alamat: employee.alamat || '',
            telp: employee.telp || '', email: employee.email || '', request_comment: '', foto: null,
        });
        setBiodataDialogOpen(true);
    }

    function submitBiodataRequest(event) {
        event.preventDefault();
        const submit = canManageBiodata ? biodataForm.put.bind(biodataForm) : biodataForm.post.bind(biodataForm);
        submit(canManageBiodata ? '/profile/biodata' : '/profile/biodata-request', {
            preserveScroll: true,
            onSuccess: () => setBiodataDialogOpen(false),
        });
    }

    return (
        <LegacyPageShell title={pageTitle}>
            <Head title={pageTitle} />
            <div className="simpeg-profile-intro">
                <div>
                    <p className="simpeg-profile-eyebrow">{pageEyebrow}</p>
                    <h1 className="simpeg-text mt-2 text-3xl font-medium sm:text-4xl">{pageHeading}</h1>
                    <p className="simpeg-muted mt-3 max-w-2xl text-sm leading-6 sm:text-base">{pageDescription}</p>
                </div>
                <span className="simpeg-profile-intro-mark" aria-hidden="true">SIMPEG</span>
            </div>

            {flash.error && <div className="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm font-medium text-amber-800" role="alert">{flash.error}</div>}
            {flash.success && <div className="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-4 text-sm font-medium text-emerald-800" role="status">{flash.success}</div>}

            {!employee ? <div className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm font-medium text-amber-800" role="alert">Data pegawai untuk akun ini belum ditemukan.</div> : <div className="simpeg-profile-page">
                <section className="simpeg-profile-hero" aria-labelledby="profile-identity-title">
                    <div className="simpeg-profile-identity">
                        <div className="simpeg-profile-avatar">
                            <ProfileAvatar gender={employee.jk} photoUrl={employee.foto_url} alt={`Foto ${employee.nama}`} />
                        </div>
                        <div className="min-w-0">
                            <p className="simpeg-profile-identity-kicker">Pegawai aktif</p>
                            <h2 id="profile-identity-title" className="mt-1 truncate text-2xl font-medium">{employee.nama}</h2>
                            <p className="mt-2 font-mono text-sm opacity-75">ID {employee.id_peg}</p>
                            <span className="simpeg-profile-status mt-5">{valueOrDash(employee.status_kepeg)}</span>
                        </div>
                    </div>
                    <div className="simpeg-profile-facts">
                        <div className="simpeg-profile-fact"><span>Kontak</span><strong>{valueOrDash(employee.telp)}</strong></div>
                        <div className="simpeg-profile-fact"><span>Email</span><strong className="break-all">{valueOrDash(employee.email)}</strong></div>
                        <div className="simpeg-profile-fact"><span>TMT kerja</span><strong>{valueOrDash(employee.tmt_kerja)}</strong></div>
                        <div className="simpeg-profile-fact"><span>Tanggal pensiun</span><strong>{valueOrDash(employee.tgl_pensiun)}</strong></div>
                    </div>
                </section>

                <div className="simpeg-profile-actions">
                    {readOnly && backUrl && <Link href={backUrl} className="simpeg-config-secondary-action simpeg-profile-print-button">← Kembali ke Data Pegawai</Link>}
                    {readOnly && canManageEmployee && <Link href={`/pegawai/${encodeURIComponent(employee.id_peg)}/edit`} className="simpeg-action-primary simpeg-profile-request-button"><FilePenLine aria-hidden="true" />Edit Biodata</Link>}
                    {canRequestBiodata && <button type="button" onClick={openBiodataRequest} className="simpeg-action-primary simpeg-profile-request-button"><FilePenLine aria-hidden="true" />Ajukan Edit Biodata</button>}
                    {!readOnly && canManageBiodata && <button type="button" onClick={openBiodataRequest} className="simpeg-action-primary simpeg-profile-request-button"><FilePenLine aria-hidden="true" />Edit Biodata</button>}
                    <button type="button" onClick={() => window.print()} className="simpeg-config-secondary-action simpeg-profile-print-button"><Printer aria-hidden="true" />Cetak CV</button>
                </div>

                <div className="simpeg-profile-workspace">
                    <aside className="simpeg-profile-navigation" aria-label="Navigasi profil">
                        <div>
                            <p className="simpeg-profile-section-kicker">Bagian profil</p>
                            <p className="simpeg-muted mt-2 text-sm leading-5">Pilih informasi yang ingin dilihat.</p>
                        </div>
                        <div className="simpeg-profile-tabs" role="tablist" aria-label="Bagian profil">
                            {tabs.map(([value, label]) => <button key={value} type="button" role="tab" aria-selected={activeTab === value} onClick={() => setActiveTab(value)} className={`simpeg-profile-tab ${activeTab === value ? 'simpeg-profile-tab-active' : ''}`}>{label}<span aria-hidden="true">{activeTab === value ? '→' : '＋'}</span></button>)}
                        </div>
                        <div className="simpeg-profile-records" aria-label="Ringkasan jumlah data">
                            <div><span>{family.spouses.length + family.children.length + family.parents.length}</span><p>Data keluarga</p></div>
                            <div><span>{history.position.length + history.education.length}</span><p>Data karier</p></div>
                        </div>
                    </aside>

                    <section className="simpeg-profile-content min-w-0" aria-label="Informasi profil pegawai">
                        {activeTab === 'biodata' && <BioTab employee={employee} />}
                        {activeTab === 'family' && <FamilyTab family={family} familyRequests={familyRequests} canRequestFamily={canRequestFamily} canManageFamily={canManageFamily} employeeId={employee.id_peg} />}
                        {activeTab === 'history' && <HistoryTab history={history} />}
                    </section>
                </div>

                <article className="simpeg-profile-cv-print">
                    <header className="simpeg-profile-cv-heading">
                        <strong>PT BPR BKK JATENG (PERSERODA)</strong>
                        <span>BIODATA PEGAWAI</span>
                    </header>

                    <section className="simpeg-profile-cv-personal" aria-labelledby="cv-personal-title">
                        <h2 id="cv-personal-title">I. DATA PRIBADI</h2>
                        <div className="simpeg-profile-cv-personal-body">
                            <dl>
                                {[
                                    ['NIP', employee.id_peg],
                                    ['Nama Lengkap', employee.nama],
                                    ['Tempat, Tgl Lahir', [employee.tempat_lhr, employee.tgl_lhr].filter(Boolean).join(', ')],
                                    ['Jenis Kelamin', employee.jk],
                                    ['Agama', employee.agama],
                                    ['Status Nikah', employee.status_nikah],
                                    ['Status Kepegawaian', employee.status_kepeg],
                                    ['Alamat', employee.alamat],
                                    ['No. Telepon / HP', employee.telp],
                                    ['Email', employee.email],
                                ].map(([label, value], index) => (
                                    <div key={label}>
                                        <span className="simpeg-profile-cv-number">{index + 1}.</span>
                                        <dt>{label}</dt>
                                        <span className="simpeg-profile-cv-colon">:</span>
                                        <dd>{valueOrDash(value)}</dd>
                                    </div>
                                ))}
                            </dl>
                            <div className="simpeg-profile-cv-photo-wrap">
                                <div className="simpeg-profile-cv-photo">
                                    <ProfileAvatar gender={employee.jk} photoUrl={employee.foto_url} alt={`Foto ${employee.nama}`} />
                                </div>
                                <span>{valueOrDash(employee.id_peg)}</span>
                            </div>
                        </div>
                    </section>

                    <section className="simpeg-profile-cv-section" aria-labelledby="cv-position-title">
                        <h2 id="cv-position-title">2. RIWAYAT JABATAN</h2>
                        {history.position?.length ? history.position.map((item, index) => (
                            <div className="simpeg-profile-cv-position" key={item.id_jab || `${item.jabatan}-${index}`}>
                                <div className="simpeg-profile-cv-line">
                                    <strong>{valueOrDash(item.jabatan)}</strong>
                                    <span>{valueOrDash(item.status_jab)}</span>
                                </div>
                                <div className="simpeg-profile-cv-meta">
                                    <span><b>Unit:</b> {valueOrDash(item.unit_kerja)}</span>
                                    <span><b>TMT:</b> {valueOrDash(item.tmt_jabatan)}</span>
                                </div>
                            </div>
                        )) : <p className="simpeg-profile-cv-empty">Belum ada riwayat jabatan.</p>}
                    </section>

                    <section className="simpeg-profile-cv-section" aria-labelledby="cv-training-title">
                        <h2 id="cv-training-title">3. RIWAYAT DIKLAT</h2>
                        {history.training?.length ? history.training.map((item, index) => (
                            <div className="simpeg-profile-cv-training" key={item.id_diklat || `${item.diklat}-${index}`}>
                                <div className="simpeg-profile-cv-line">
                                    <strong>{valueOrDash(item.diklat)}</strong>
                                    <span>{valueOrDash(item.tahun)}</span>
                                </div>
                                <div className="simpeg-profile-cv-meta">
                                    <span><b>Penyelenggara:</b> {valueOrDash(item.penyelenggara)}</span>
                                    <span><b>Tempat:</b> {valueOrDash(item.tempat)}</span>
                                </div>
                            </div>
                        )) : <p className="simpeg-profile-cv-empty">Belum ada riwayat diklat.</p>}
                    </section>

                    <div className="simpeg-profile-cv-signature">
                        <p>Semarang, {formatIndonesianDate(new Date())}</p>
                        <p>Pegawai Yang Bersangkutan,</p>
                        <div className="simpeg-profile-cv-signature-name">{valueOrDash(employee.nama)}</div>
                        <p>NIP. {valueOrDash(employee.id_peg)}</p>
                    </div>

                    <footer>Halaman 1/1 | Dicetak: {formatIndonesianDate(new Date())}</footer>
                </article>
            </div>}

            {biodataDialogOpen && <div className="fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/55 p-4" role="presentation" onMouseDown={(event) => { if (event.target === event.currentTarget) setBiodataDialogOpen(false); }}>
                <section className="simpeg-profile-panel simpeg-profile-edit-dialog max-h-[90dvh] w-full max-w-3xl p-5 shadow-2xl sm:p-7" role="dialog" aria-modal="true" aria-labelledby="biodata-request-title">
                    <div className="flex items-start justify-between gap-4"><div><p className="simpeg-profile-section-kicker">{canManageBiodata ? 'Perubahan langsung' : 'Pengajuan perubahan'}</p><h2 id="biodata-request-title" className="simpeg-text mt-1 text-xl font-bold">{canManageBiodata ? 'Edit Biodata' : 'Ajukan Edit Biodata'}</h2><p className="simpeg-muted mt-2 text-sm">{canManageBiodata ? 'Perubahan akan langsung disimpan ke data pegawai.' : 'Perubahan akan diterapkan setelah disetujui.'}</p></div><button type="button" onClick={() => setBiodataDialogOpen(false)} className="simpeg-config-secondary-action simpeg-utility min-h-10 rounded-lg border px-3 text-sm font-semibold">Tutup</button></div>
                    <form onSubmit={submitBiodataRequest} className="mt-5">
                        <div className="grid gap-4 sm:grid-cols-2">
                            {[
                                ['nip', 'NIK', 'text'], ['nama', 'Nama lengkap', 'text'], ['tempat_lhr', 'Tempat lahir', 'text'], ['tgl_lhr', 'Tanggal lahir', 'date'],
                                ['gol_darah', 'Golongan darah', 'text'], ['status_nikah', 'Status nikah', 'text'],
                                ['telp', 'Telepon', 'text'], ['email', 'Email', 'email'],
                            ].map(([name, label, type]) => <label key={name} className="grid gap-1.5 text-sm font-semibold simpeg-text">{label}<input type={type} value={biodataForm.data[name]} onChange={(event) => biodataForm.setData(name, event.target.value)} className="simpeg-input min-h-11 w-full rounded-xl border px-3 py-2 font-normal" />{biodataForm.errors[name] && <span className="text-xs font-medium text-red-600" role="alert">{biodataForm.errors[name]}</span>}</label>)}
                            <SearchableCombobox id="profile-edit-gender" label="Jenis kelamin" value={biodataForm.data.jk} onChange={(value) => biodataForm.setData('jk', value)} options={genderOptions} searchable={false} placeholder="Pilih jenis kelamin" error={biodataForm.errors.jk} className="min-w-0" />
                            <SearchableCombobox id="profile-edit-religion" label="Agama" value={biodataForm.data.agama} onChange={(value) => biodataForm.setData('agama', value)} options={religionOptions} searchable={false} placeholder="Pilih agama" error={biodataForm.errors.agama} className="min-w-0" />
                            <SearchableCombobox id="profile-edit-employment-status" label="Status kepegawaian" value={biodataForm.data.status_kepeg} onChange={(value) => biodataForm.setData('status_kepeg', value)} options={[...new Set([...employmentStatusOptions, employee.status_kepeg].filter(Boolean))]} searchable={false} placeholder="Pilih status kepegawaian" error={biodataForm.errors.status_kepeg} className="min-w-0" />
                            <label className="grid gap-1.5 text-sm font-semibold simpeg-text sm:col-span-2">Alamat<textarea rows="2" value={biodataForm.data.alamat} onChange={(event) => biodataForm.setData('alamat', event.target.value)} className="simpeg-input w-full rounded-xl border px-3 py-2 font-normal" />{biodataForm.errors.alamat && <span className="text-xs font-medium text-red-600" role="alert">{biodataForm.errors.alamat}</span>}</label>
                            {(canManageBiodata || canRequestBiodata) && <div className="grid gap-2 text-sm font-semibold simpeg-text sm:col-span-2">
                                <span>Foto profil</span>
                                <div className="flex min-w-0 items-center gap-3 rounded-xl border border-slate-200 p-3">
                                    <div className="h-14 w-14 shrink-0 overflow-hidden rounded-full border border-slate-200 bg-slate-100">
                                        <ProfileAvatar gender={employee.jk} photoUrl={fotoPreview} alt={`Pratinjau foto ${employee.nama}`} />
                                    </div>
                                    <div className="min-w-0">
                                        <label className="inline-flex min-h-9 cursor-pointer items-center rounded-lg border border-slate-300 px-3 text-sm font-semibold transition hover:border-blue-500 hover:text-blue-700">
                                            Pilih foto
                                            <input type="file" accept=".jpg,.jpeg,.png,.gif,image/jpeg,image/png,image/gif" onChange={(event) => biodataForm.setData('foto', event.target.files?.[0] || null)} className="sr-only" />
                                        </label>
                                        <p className="mt-1 max-w-full truncate text-xs font-normal simpeg-muted">{biodataForm.data.foto?.name || 'JPG, PNG, atau GIF · Maksimal 2 MB'}</p>
                                    </div>
                                </div>
                                {biodataForm.errors.foto && <span className="text-xs font-medium text-red-600" role="alert">{biodataForm.errors.foto}</span>}
                            </div>}
                            {canRequestBiodata && <label className="grid gap-1.5 text-sm font-semibold simpeg-text sm:col-span-2">Catatan pengajuan (opsional)<textarea rows="2" value={biodataForm.data.request_comment} onChange={(event) => biodataForm.setData('request_comment', event.target.value)} className="simpeg-input w-full rounded-xl border px-3 py-2 font-normal" placeholder="Jelaskan alasan perubahan" /></label>}
                        </div>
                        <div className="mt-6 flex flex-wrap justify-end gap-3"><button type="button" onClick={() => setBiodataDialogOpen(false)} className="simpeg-config-secondary-action simpeg-utility min-h-11 rounded-xl border px-4 font-semibold">Batal</button><button type="submit" disabled={biodataForm.processing} className="simpeg-action-primary simpeg-profile-request-button disabled:cursor-wait disabled:opacity-60">{biodataForm.processing ? (canManageBiodata ? 'Menyimpan...' : 'Mengirim...') : (canManageBiodata ? 'Simpan Perubahan' : 'Kirim Pengajuan')}</button></div>
                    </form>
                </section>
            </div>}
        </LegacyPageShell>
    );
}
