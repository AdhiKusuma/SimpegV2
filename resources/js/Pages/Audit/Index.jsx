import { Head, Link, router, usePage } from '@inertiajs/react';
import { Expand, Search, ShieldAlert, Trash2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import LegacyPageShell from '../../Components/LegacyPageShell';
import MobileFilterGroup from '../../Components/MobileFilterGroup';
import SelectCombobox from '../../Components/SelectCombobox';

const roleLabels = {
    admin: 'Admin',
    superadmin: 'Superadmin',
    kepala: 'Supervisor',
    user: 'User',
};

const actionTone = {
    Create: 'create',
    Update: 'update',
    Delete: 'delete',
    Import: 'import',
    Export: 'export',
    Process: 'process',
    View: 'view',
};

const monthLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
const cleanupOptions = [
    { value: '30', label: '30 hari' },
    { value: '90', label: '90 hari' },
    { value: '180', label: '180 hari' },
    { value: '365', label: '1 tahun' },
    { value: '730', label: '2 tahun' },
];

function formatDate(value) {
    if (!value) {
        return '-';
    }

    const [date, time] = String(value).split(' ');
    const [year, month, day] = date.split('-');
    const formattedDate = day + ' ' + (monthLabels[Math.max(Number(month) - 1, 0)] || month) + ' ' + year;

    return time ? formattedDate + ' ' + time : formattedDate;
}

export default function AuditLogIndex({ logs, filters, modules, actions }) {
    const { auth = {}, flash = {} } = usePage().props;
    const pageRef = useRef(null);
    const [fullscreen, setFullscreen] = useState(false);
    const [form, setForm] = useState({ ...filters });
    const [cleanupOpen, setCleanupOpen] = useState(false);
    const [cleanupDays, setCleanupDays] = useState('180');
    const [cleanupPreview, setCleanupPreview] = useState(null);
    const [cleanupLoading, setCleanupLoading] = useState(false);
    const permissions = auth?.user?.permissions || [];
    const canCleanup = permissions.includes('audit.cleanup');

    useEffect(() => {
        const syncFullscreen = () => setFullscreen(Boolean(document.fullscreenElement));

        document.addEventListener('fullscreenchange', syncFullscreen);

        return () => document.removeEventListener('fullscreenchange', syncFullscreen);
    }, []);

    function updateField(field, value) {
        setForm((current) => ({ ...current, [field]: value }));
    }

    function applyFilters(event) {
        event.preventDefault();
        const params = Object.fromEntries(Object.entries(form).filter(([, value]) => String(value || '').trim() !== ''));

        router.get('/audit-log', params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }

    async function previewCleanup() {
        setCleanupLoading(true);
        setCleanupPreview(null);

        try {
            const response = await fetch('/audit-log/cleanup-preview?older_than_days=' + encodeURIComponent(cleanupDays), {
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                throw new Error('Preview cleanup tidak tersedia.');
            }

            setCleanupPreview(await response.json());
        } catch (error) {
            setCleanupPreview({ error: error.message || 'Preview cleanup gagal.' });
        } finally {
            setCleanupLoading(false);
        }
    }

    function submitCleanup() {
        const count = Number(cleanupPreview?.count || 0);
        const confirmed = count > 0 && window.confirm(
            'Hapus ' + count.toLocaleString('id-ID') + ' audit log yang lebih lama dari '
            + cleanupDays + ' hari? Log 30 hari terakhir tetap dilindungi.',
        );
        if (!confirmed) {
            return;
        }

        router.post('/audit-log/cleanup', { older_than_days: Number(cleanupDays) }, {
            preserveScroll: true,
            onStart: () => setCleanupLoading(true),
            onFinish: () => setCleanupLoading(false),
            onSuccess: () => setCleanupPreview(null),
        });
    }

    async function toggleFullscreen() {
        if (document.fullscreenElement) {
            await document.exitFullscreen();
            setFullscreen(false);
            return;
        }

        if (pageRef.current?.requestFullscreen) {
            await pageRef.current.requestFullscreen();
            setFullscreen(true);
        }
    }

    return (
        <LegacyPageShell title="Audit Log">
            <Head title="Audit Log" />
            <div ref={pageRef} className={'simpeg-audit-page' + (fullscreen ? ' is-fullscreen' : '')}>
                <div className="simpeg-audit-heading">
                    <div>
                        <p className="simpeg-audit-kicker">Administrasi sistem</p>
                        <h1>Audit Log</h1>
                        <p>Jejak aktivitas append-only untuk pemeriksaan internal dan penelusuran perubahan data.</p>
                    </div>
                    <div className="simpeg-audit-heading-actions">
                        {canCleanup && (
                            <button type="button" className="simpeg-audit-maintenance-button" onClick={() => {
                                setCleanupOpen((current) => !current);
                                setCleanupPreview(null);
                            }}>
                                <Trash2 className="h-4 w-4" strokeWidth={1.8} aria-hidden="true" />
                                {cleanupOpen ? 'Tutup cleanup' : 'Bersihkan log'}
                            </button>
                        )}
                        <button type="button" className="simpeg-audit-fullscreen" onClick={toggleFullscreen}>
                            <Expand className="h-4 w-4" strokeWidth={1.8} aria-hidden="true" />
                            {fullscreen ? 'Keluar layar penuh' : 'Layar penuh'}
                        </button>
                    </div>
                </div>

                {flash.success && <div className="simpeg-audit-alert simpeg-audit-alert-success" role="status">{flash.success}</div>}
                {flash.error && <div className="simpeg-audit-alert simpeg-audit-alert-error" role="alert">{flash.error}</div>}

                {canCleanup && cleanupOpen && (
                    <section className="simpeg-audit-cleanup-card" aria-label="Pembersihan audit log">
                        <div>
                            <p className="simpeg-audit-kicker">Pemeliharaan</p>
                            <h2>Bersihkan audit log lama</h2>
                            <p>Proses dijalankan melalui queue agar tidak mengganggu request utama. Log 30 hari terakhir selalu dilindungi.</p>
                        </div>
                        <div className="simpeg-audit-cleanup-controls">
                            <label htmlFor="audit-cleanup-days">Hapus log lebih lama dari</label>
                            <div className="simpeg-audit-cleanup-row">
                                <SelectCombobox
                                    id="audit-cleanup-days"
                                    value={cleanupDays}
                                    options={cleanupOptions}
                                    placeholder="Pilih periode"
                                    className="simpeg-audit-cleanup-select"
                                    onChange={(value) => {
                                        setCleanupDays(value);
                                        setCleanupPreview(null);
                                    }}
                                />
                                <button type="button" className="simpeg-audit-preview-button" onClick={previewCleanup} disabled={cleanupLoading}>
                                    <Search className="h-4 w-4" strokeWidth={1.8} aria-hidden="true" />
                                    {cleanupLoading ? 'Memproses...' : 'Pratinjau'}
                                </button>
                            </div>
                            {cleanupPreview?.error && <p className="simpeg-audit-cleanup-error">{cleanupPreview.error}</p>}
                            {cleanupPreview && !cleanupPreview.error && (
                                <div className="simpeg-audit-cleanup-preview">
                                    <ShieldAlert className="h-4 w-4" strokeWidth={1.8} aria-hidden="true" />
                                    <span>{Number(cleanupPreview.count || 0).toLocaleString('id-ID')} log siap dibersihkan.</span>
                                    <button type="button" className="simpeg-audit-delete-button" onClick={submitCleanup} disabled={!cleanupPreview.count || cleanupLoading}>
                                        <Trash2 className="h-4 w-4" strokeWidth={1.8} aria-hidden="true" />
                                        Hapus sekarang
                                    </button>
                                </div>
                            )}
                        </div>
                    </section>
                )}

                <section className="simpeg-audit-filter-card simpeg-filter-shell" aria-label="Filter audit log">
                    <form onSubmit={applyFilters} className="simpeg-filter-toolbar simpeg-filter-toolbar--mobile-panel simpeg-audit-filter-grid">
                        <MobileFilterGroup id="audit-mobile-filters" activeCount={Object.values(form).filter((value) => String(value || '').trim() !== '').length}>
                            <div className="simpeg-audit-search-field">
                                <label htmlFor="audit-search">Cari aktivitas</label>
                                <div className="simpeg-audit-search-control">
                                    <Search className="h-4 w-4" strokeWidth={1.8} aria-hidden="true" />
                                    <input id="audit-search" type="search" value={form.search || ''} onChange={(event) => updateField('search', event.target.value)} placeholder="Pelaku, deskripsi, entitas" />
                                </div>
                            </div>
                            <SelectCombobox id="audit-module" label="Modul" value={form.module || ''} options={modules.map((module) => ({ value: module, label: module }))} emptyLabel="Semua modul" placeholder="Semua modul" onChange={(value) => updateField('module', value)} />
                            <SelectCombobox id="audit-action" label="Aksi" value={form.action || ''} options={actions.map((action) => ({ value: action, label: action }))} emptyLabel="Semua aksi" placeholder="Semua aksi" onChange={(value) => updateField('action', value)} />
                            <div className="simpeg-audit-date-field">
                                <label htmlFor="audit-from-date">Dari tanggal</label>
                                <input id="audit-from-date" type="date" value={form.from_date || ''} onChange={(event) => updateField('from_date', event.target.value)} />
                            </div>
                            <div className="simpeg-audit-date-field">
                                <label htmlFor="audit-to-date">Sampai tanggal</label>
                                <input id="audit-to-date" type="date" value={form.to_date || ''} onChange={(event) => updateField('to_date', event.target.value)} />
                            </div>
                            <div className="simpeg-audit-filter-actions">
                                <button type="submit" className="simpeg-audit-apply simpeg-mobile-filter-apply">Terapkan</button>
                            </div>
                        </MobileFilterGroup>
                    </form>
                </section>

                <section className="simpeg-audit-table-card" aria-label="Daftar audit log">
                    <div className="simpeg-audit-table-wrap">
                        <table className="simpeg-audit-table">
                            <thead>
                                <tr>
                                    <th>Waktu</th>
                                    <th>Pelaku</th>
                                    <th>Modul</th>
                                    <th>Aksi</th>
                                    <th>Deskripsi</th>
                                    <th>Entitas</th>
                                    <th>IP</th>
                                </tr>
                            </thead>
                            <tbody>
                                {logs.data.length === 0 ? (
                                    <tr><td colSpan="7" className="simpeg-audit-empty">Belum ada aktivitas yang sesuai dengan filter.</td></tr>
                                ) : logs.data.map((log) => (
                                    <tr key={log.id}>
                                        <td className="simpeg-audit-time">{formatDate(log.created_at)}</td>
                                        <td>
                                            <strong>{log.actor_name || 'Sistem'}</strong>
                                            <span>{roleLabels[String(log.actor_role || '').toLowerCase()] || log.actor_role || log.actor_id || 'System'}</span>
                                        </td>
                                        <td><span className="simpeg-audit-module">{log.module}</span></td>
                                        <td><span className={'simpeg-audit-action simpeg-audit-action-' + (actionTone[log.action] || 'view')}>{log.action}</span></td>
                                        <td>
                                            <span className="simpeg-audit-description">{log.description}</span>
                                            <span className="simpeg-audit-route">{log.method} {log.route_name || '-'}</span>
                                        </td>
                                        <td>
                                            <span className="simpeg-audit-entity">{log.entity_id || '-'}</span>
                                            {log.entity_type && <span className="simpeg-audit-entity-type">{log.entity_type}</span>}
                                        </td>
                                        <td className="simpeg-audit-ip">{log.ip_address || '-'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <div className="simpeg-audit-pagination">
                        <span>Menampilkan {logs.from || 0}–{logs.to || 0} dari {logs.total} aktivitas</span>
                        <div className="simpeg-audit-pagination-links" aria-label="Paginasi audit log">
                            {logs.links.filter((link) => link.url).map((link) => (
                                <Link key={link.label} href={link.url} preserveState preserveScroll dangerouslySetInnerHTML={{ __html: link.label }} className={'simpeg-audit-page-link' + (link.active ? ' is-active' : '')} />
                            ))}
                        </div>
                    </div>
                </section>
            </div>
        </LegacyPageShell>
    );
}
