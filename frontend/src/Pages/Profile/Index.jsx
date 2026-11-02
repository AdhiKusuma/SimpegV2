import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import LegacyPageShell from '../../Components/LegacyPageShell';

const tabs = [
    ['biodata', 'Biodata'],
    ['family', 'Keluarga'],
    ['history', 'Data kepegawaian'],
];

function valueOrDash(value) {
    return value || '-';
}

function DefaultProfileAvatar({ className = '' }) {
    return <svg viewBox="0 0 80 80" fill="none" role="img" aria-label="Foto profil belum tersedia" className={`h-full w-full ${className}`}>
        <circle cx="40" cy="40" r="40" fill="currentColor" fillOpacity="0.08" />
        <circle cx="40" cy="29" r="11" stroke="currentColor" strokeWidth="3.5" />
        <path d="M20 66c2.6-11.3 10.2-17 20-17s17.4 5.7 20 17" stroke="currentColor" strokeLinecap="round" strokeWidth="3.5" />
    </svg>;
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

        return <div className="flex min-w-[10.5rem] flex-col items-stretch gap-2">
            <Link href={`/keluarga/${type}/${encodeURIComponent(recordId)}/edit?uid=${encodeURIComponent(employeeId)}`} className="inline-flex min-h-10 w-full items-center justify-center rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:border-blue-600 hover:text-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-600/20">{canRequestFamily ? 'Ajukan ubah' : 'Edit'}</Link>
            <button type="button" onClick={() => { setDeleteTarget({ type, item }); deleteForm.setData('reason', ''); }} className="inline-flex min-h-10 w-full items-center justify-center rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:border-red-500 hover:text-red-600 focus:outline-none focus:ring-2 focus:ring-red-600/20">{canRequestFamily ? 'Ajukan hapus' : 'Hapus'}</button>
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
            <DataTable title="Pengangkatan" items={history.appointment} columns={[["Jenis", 'jns_mutasi'], ['Tanggal', 'tgl_mutasi'], ['Nomor SK', 'no_mutasi'], ['TMT', 'tmt']]} />
            <DataTable title="Diklat" items={history.training} columns={[["Diklat", 'diklat'], ['Penyelenggara', 'penyelenggara'], ['Tempat', 'tempat'], ['Tahun', 'tahun']]} />
            <DataTable title="Sertifikasi" items={history.certification} columns={[["Sertifikasi", 'sertifikasi'], ['Penyelenggara', 'penyelenggara'], ['Tanggal', 'tgl_sertifikat'], ['Berlaku sampai', 'tgl_expired']]} />
            <DataTable title="Pelanggaran" items={history.discipline} columns={[["Pelanggaran", 'hukuman'], ['Tanggal SK', 'tgl_sk'], ['Nomor SK', 'no_sk'], ['Keterangan', 'keterangan']]} />
            <DataTable title="Jabatan" items={history.position} columns={[["Jabatan", 'jabatan'], ['Unit kerja', 'unit_kerja'], ['Mulai', 'tmt_jabatan'], ['Selesai', 'sampai_tgl'], ['Status', 'status_jab']]} />
            <DataTable title="Pendidikan terakhir" items={latestEducation} columns={[["Jenjang", 'jenjang'], ['Sekolah', 'nama_sekolah'], ['Jurusan', 'jurusan'], ['Tanggal ijazah', 'tgl_ijazah'], ['Tahun lulus', 'th_lulus']]} />
        </div>
    );
}

export default function Profile({ employee, family, familyRequests = [], canRequestFamily, canManageFamily, history }) {
    const [activeTab, setActiveTab] = useState('biodata');

    return (
        <LegacyPageShell title="Profil Saya">
            <Head title="Profil Saya" />
            <div className="simpeg-profile-intro">
                <div>
                    <p className="simpeg-profile-eyebrow">Profil Saya</p>
                    <h1 className="simpeg-text mt-2 text-3xl font-medium sm:text-4xl">Ruang profil</h1>
                    <p className="simpeg-muted mt-3 max-w-2xl text-sm leading-6 sm:text-base">Kelola informasi pribadi, keluarga, dan data kepegawaian yang terkait dengan akun ini.</p>
                </div>
                <span className="simpeg-profile-intro-mark" aria-hidden="true">SIMPEG</span>
            </div>

            {!employee ? <div className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm font-medium text-amber-800" role="alert">Data pegawai untuk akun ini belum ditemukan.</div> : <div className="simpeg-profile-page">
                <section className="simpeg-profile-hero" aria-labelledby="profile-identity-title">
                    <div className="simpeg-profile-identity">
                        <div className="simpeg-profile-avatar">
                            {employee.foto_url ? <>
                                <img src={employee.foto_url} alt={`Foto ${employee.nama}`} className="h-full w-full object-cover" onError={(event) => { event.currentTarget.classList.add('hidden'); event.currentTarget.nextElementSibling?.classList.remove('hidden'); }} />
                                <DefaultProfileAvatar className="hidden p-5" />
                            </> : <DefaultProfileAvatar className="p-5" />}
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
            </div>}
        </LegacyPageShell>
    );
}
