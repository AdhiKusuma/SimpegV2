import { Link, router, usePage } from '@inertiajs/react';
import { lazy, Suspense, useEffect, useRef, useState } from 'react';
import {
    Activity,
    ArrowUpRight,
    ChevronLeft,
    ChevronRight,
    FileBarChart2,
    GraduationCap,
    LockKeyhole,
    ShieldAlert,
    X,
    UsersRound,
} from 'lucide-react';
import LegacyPageShell from '../../Components/LegacyPageShell';
import SelectCombobox from '../../Components/SelectCombobox';
import { buttonVariants } from '../../Components/ui/button';
import { Badge } from '../../Components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '../../Components/ui/card';
import { NumberTicker } from '../../Components/ui/number-ticker';

const DashboardCharts = lazy(() => import('../../Components/DashboardCharts').then(({ DashboardCharts: Component }) => ({ default: Component })));

const statMeta = {
    employees: { icon: UsersRound, iconClass: 'simpeg-text', kicker: 'Data aktif', badge: 'success' },
    inactive: { icon: Activity, iconClass: 'simpeg-text', kicker: 'Perubahan status', badge: 'warning' },
    punishments: { icon: ShieldAlert, iconClass: 'simpeg-text', kicker: 'Catatan tahun berjalan', badge: 'danger' },
    training: { icon: GraduationCap, iconClass: 'simpeg-text', kicker: 'Peserta tahun berjalan', badge: 'info' },
};

const tenureColors = {
    primary: 'from-[var(--simpeg-primary)] to-[var(--simpeg-primary-hover)]',
    accent: 'from-[var(--simpeg-primary-hover)] to-[var(--simpeg-primary)]',
};

function StatCard({ stat }) {
    const meta = statMeta[stat.key] || statMeta.employees;
    const Icon = meta.icon;

    return (
        <Card className="simpeg-dashboard-stat group relative h-full min-w-0 border p-0 transition-colors">
            <Link href={stat.href} className="block min-w-0 p-4 sm:p-5">
                <div className="flex items-start justify-between gap-4">
                    <p className="simpeg-muted min-w-0 break-words text-sm">{stat.label}</p>
                    <Icon className={`h-5 w-5 shrink-0 ${meta.iconClass}`} strokeWidth={1.7} aria-hidden="true" />
                </div>
                <p className="simpeg-dashboard-stat-value simpeg-text mt-5"><NumberTicker value={stat.value} /></p>
                <div className="mt-3 flex min-h-6 items-center">
                    <Badge variant={meta.badge}>{meta.kicker}</Badge>
                </div>
                <p className="simpeg-muted mt-4 break-words text-sm leading-5">Ringkasan pada tahun berjalan</p>
                <span className="simpeg-dashboard-stat-link simpeg-muted mt-5 inline-flex items-center gap-2 text-sm">
                    Buka ringkasan
                    <ArrowUpRight className="h-4 w-4 transition-transform group-hover:-translate-y-0.5 group-hover:translate-x-0.5" strokeWidth={1.8} aria-hidden="true" />
                </span>
            </Link>
        </Card>
    );
}

function DeferredDashboardCharts({ charts, retirement, executivePositions, structuralPositions, year }) {
    const containerRef = useRef(null);
    const [shouldRender, setShouldRender] = useState(false);

    useEffect(() => {
        const container = containerRef.current;
        if (!container) {
            return undefined;
        }

        const renderCharts = () => setShouldRender(true);
        if (!window.matchMedia('(max-width: 1023px)').matches) {
            renderCharts();
            return undefined;
        }

        let idleId;
        let idleType = null;

        if (typeof window.requestIdleCallback === 'function') {
            idleId = window.requestIdleCallback(renderCharts, { timeout: 1600 });
            idleType = 'idle';
        } else {
            idleId = window.setTimeout(renderCharts, 1600);
            idleType = 'timeout';
        }

        if (!('IntersectionObserver' in window)) {
            renderCharts();
        } else {
            const observer = new IntersectionObserver(([entry]) => {
                if (entry.isIntersecting) {
                    renderCharts();
                    observer.disconnect();
                }
            }, { rootMargin: '240px 0px' });

            observer.observe(container);

            return () => {
                observer.disconnect();
                if (idleType === 'idle') {
                    window.cancelIdleCallback(idleId);
                } else {
                    window.clearTimeout(idleId);
                }
            };
        }

        return () => {
            if (idleType === 'idle') {
                window.cancelIdleCallback(idleId);
            } else {
                window.clearTimeout(idleId);
            }
        };
    }, []);

    return (
        <div ref={containerRef} className="simpeg-dashboard-deferred min-w-0" aria-busy={!shouldRender}>
            {shouldRender ? (
                <Suspense fallback={(
                    <div className="simpeg-card rounded-2xl border p-5" role="status" aria-live="polite">
                        <p className="simpeg-text text-sm font-medium">Ringkasan lanjutan sedang dimuat.</p>
                        <p className="simpeg-muted mt-1 text-sm">Chart dan tabel akan segera tampil.</p>
                    </div>
                )}>
                    <DashboardCharts
                        charts={charts}
                        retirement={retirement}
                        executivePositions={executivePositions}
                        structuralPositions={structuralPositions}
                        year={year}
                    />
                </Suspense>
            ) : (
                <div className="simpeg-card rounded-2xl border p-5" role="status" aria-live="polite">
                    <p className="simpeg-text text-sm font-medium">Ringkasan lanjutan sedang disiapkan.</p>
                    <p className="simpeg-muted mt-1 text-sm">Chart dan tabel akan tampil saat bagian ini mendekati layar.</p>
                </div>
            )}
        </div>
    );
}

export default function Dashboard({ stats = [], tenure = [], year, unit, offices = [], filters = {}, charts = {}, retirement = [], executivePositions = [], structuralPositions = [], dashboardLocked = false, canSelectOffice = !dashboardLocked }) {
    const { auth, flash } = usePage().props;
    const user = auth?.user ?? {};
    const displayName = user.nama_user || 'Pengguna SIMPEG';
    const [showWelcome, setShowWelcome] = useState(Boolean(flash?.welcome));
    const [selectedTenure, setSelectedTenure] = useState(null);
    const [tenurePage, setTenurePage] = useState(1);
    const [tenureResult, setTenureResult] = useState(null);
    const [tenureLoading, setTenureLoading] = useState(false);
    const [tenureError, setTenureError] = useState('');
    const tenureDialogRef = useRef(null);
    const maximumTenure = Math.max(...tenure.map((item) => Number(item.value) || 0), 1);
    const selectedUnit = String(filters.unit_kerja ?? unit ?? '');
    const selectedOffice = offices.find((office) => String(office.kode) === selectedUnit);
    const dashboardLabel = dashboardLocked ? 'Dashboard wilayah' : 'Dashboard';

    useEffect(() => {
        if (!showWelcome) {
            return undefined;
        }

        const timer = window.setTimeout(() => setShowWelcome(false), 4500);

        return () => window.clearTimeout(timer);
    }, [showWelcome]);

    useEffect(() => {
        const dialog = tenureDialogRef.current;
        if (!dialog) return;

        if (selectedTenure && !dialog.open) dialog.showModal();
        if (!selectedTenure && dialog.open) dialog.close();
    }, [selectedTenure]);

    useEffect(() => {
        if (!selectedTenure) return undefined;

        const controller = new AbortController();
        const params = new URLSearchParams({ page: String(tenurePage) });
        if (selectedUnit) params.set('unit_kerja', selectedUnit);

        setTenureLoading(true);
        setTenureError('');
        fetch(`/dashboard/tenure/${encodeURIComponent(selectedTenure.key)}?${params.toString()}`, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        })
            .then(async (response) => {
                if (!response.ok) throw new Error('Daftar pegawai tidak dapat dimuat. Coba buka kembali statistik ini.');
                return response.json();
            })
            .then((result) => setTenureResult(result))
            .catch((error) => {
                if (error.name !== 'AbortError') setTenureError(error.message || 'Terjadi kendala saat memuat daftar pegawai.');
            })
            .finally(() => {
                if (!controller.signal.aborted) setTenureLoading(false);
            });

        return () => controller.abort();
    }, [selectedTenure, selectedUnit, tenurePage]);

    function handleOfficeChange(nextUnit) {
        router.get('/dashboard', nextUnit ? { unit_kerja: nextUnit } : {}, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    }

    function openTenureEmployees(item) {
        setTenurePage(1);
        setTenureResult(null);
        setSelectedTenure(item);
    }

    function closeTenureDialog() {
        tenureDialogRef.current?.close();
        setSelectedTenure(null);
        setTenureResult(null);
        setTenureError('');
    }

    return (
        <LegacyPageShell title="Dashboard">
            {showWelcome && (
                <div className="simpeg-card fixed right-4 top-20 z-40 w-[min(22rem,calc(100vw-2rem))] rounded-2xl border p-4 shadow-lg" role="status" aria-live="polite">
                    <p className="simpeg-muted text-sm">Selamat datang</p>
                    <p className="simpeg-text mt-1 truncate text-base font-medium" title={displayName}>{displayName}</p>
                </div>
            )}
            <div className="simpeg-dashboard-shell space-y-6">
                <section className="flex flex-col gap-6 border-b simpeg-border pb-7 xl:flex-row xl:items-end xl:justify-between" aria-labelledby="dashboard-title">
                    <div className="min-w-0">
                        <p className="simpeg-text mb-3 text-sm font-medium">{dashboardLabel}</p>
                        <h1 id="dashboard-title" className="simpeg-text max-w-3xl text-3xl font-medium sm:text-4xl">Ringkasan kepegawaian</h1>
                        <p className="simpeg-muted mt-3 max-w-3xl text-sm leading-6 sm:text-base">Ringkasan data kepegawaian dari sistem SIMPEG untuk tahun {year}{selectedUnit ? ` pada wilayah ${selectedOffice?.nama || selectedUnit}` : ''}.</p>
                    </div>
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-end xl:shrink-0">
                        <SelectCombobox
                            id="dashboard-region"
                            label={dashboardLocked ? 'Wilayah aktif' : 'Wilayah'}
                            value={selectedUnit}
                            options={offices}
                            optionValue={(office) => office.kode}
                            optionLabel={(office) => office.nama}
                            emptyLabel={dashboardLocked ? undefined : 'Konsolidasi'}
                            placeholder={dashboardLocked ? 'Wilayah belum ditentukan' : 'Konsolidasi'}
                            emptyMessage="Wilayah tidak ditemukan."
                            onChange={canSelectOffice ? handleOfficeChange : undefined}
                            disabled={!canSelectOffice}
                            description={dashboardLocked ? 'Terkunci sesuai kantor pada akun kepala.' : undefined}
                            className="w-full sm:min-w-56"
                        />
                        <Link href="/pegawai" className={buttonVariants({ variant: 'default', className: 'simpeg-dashboard-action' })}>
                            <UsersRound className="h-4 w-4" strokeWidth={1.8} aria-hidden="true" />
                            Daftar pegawai
                        </Link>
                        <Link href="/laporan/nominatif" className={buttonVariants({ variant: 'default', className: 'simpeg-dashboard-action' })}>
                            <FileBarChart2 className="h-4 w-4" strokeWidth={1.8} aria-hidden="true" />
                            Laporan nominatif
                        </Link>
                    </div>
                </section>

                <section className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-12" aria-label="Ringkasan dashboard">
                    {stats.map((stat) => <div key={stat.key} className="lg:col-span-3"><StatCard stat={stat} /></div>)}
                </section>

                <Card className="lg:col-span-12" aria-labelledby="tenure-title">
                    <CardHeader className="simpeg-dashboard-card-header flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p className="simpeg-muted text-sm font-medium">Distribusi masa kerja</p>
                            <CardTitle as="h2" id="tenure-title" className="mt-1 text-xl">Statistik masa kerja</CardTitle>
                            <CardDescription className="mt-1">Pegawai aktif berdasarkan selisih penuh tahun dari TMT kerja.</CardDescription>
                        </div>
                        <Link href="/laporan/nominatif" className="simpeg-utility inline-flex min-h-11 items-center gap-2 self-start rounded-xl border px-3 text-sm font-medium transition hover:border-[var(--simpeg-primary-border)] hover:bg-[var(--simpeg-primary-soft)] focus:outline-none focus:ring-2 focus:ring-[var(--simpeg-primary-border)]">
                            Lihat laporan
                            <ArrowUpRight className="h-4 w-4" strokeWidth={1.8} aria-hidden="true" />
                        </Link>
                    </CardHeader>
                    <CardContent className="simpeg-dashboard-card-content">
                    <div className="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        {tenure.map((item) => {
                            const value = Number(item.value) || 0;
                            const width = Math.max((value / maximumTenure) * 100, value > 0 ? 3 : 0);

                            return (
                                <button
                                    key={item.key || item.label}
                                    type="button"
                                    className="simpeg-card-soft group w-full rounded-2xl border p-4 text-left transition-colors hover:border-slate-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--simpeg-primary)] focus-visible:ring-offset-2"
                                    aria-label={`Lihat ${value} pegawai dengan masa kerja ${item.label}`}
                                    onClick={() => openTenureEmployees(item)}
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <span className="simpeg-muted text-xs">{item.label}</span>
                                        <span className="flex items-center gap-2">
                                            <strong className="simpeg-text text-2xl font-medium"><NumberTicker value={value} /></strong>
                                            <ArrowUpRight className="h-4 w-4 text-slate-400 transition group-hover:-translate-y-0.5 group-hover:translate-x-0.5 group-hover:text-slate-700 dark:group-hover:text-slate-100" aria-hidden="true" />
                                        </span>
                                    </div>
                                    <div className="simpeg-track mt-5 h-2.5 overflow-hidden rounded-full" aria-hidden="true">
                                        <div className="simpeg-progress-fill h-full rounded-full" style={{ width: `${width}%` }} />
                                    </div>
                                    <span className="simpeg-muted mt-3 block text-xs">Lihat daftar pegawai</span>
                                </button>
                            );
                        })}
                    </div>
                    </CardContent>
                </Card>

                <DeferredDashboardCharts charts={charts} retirement={retirement} executivePositions={executivePositions} structuralPositions={structuralPositions} year={year} />
            </div>
            <dialog
                ref={tenureDialogRef}
                className="simpeg-tenure-dialog"
                aria-labelledby="tenure-dialog-title"
                aria-describedby="tenure-dialog-description"
                onClose={() => setSelectedTenure(null)}
                onClick={(event) => {
                    if (event.target === event.currentTarget) closeTenureDialog();
                }}
            >
                {selectedTenure && (
                    <div className="flex max-h-[calc(100dvh-2rem)] flex-col">
                        <header className="flex items-start justify-between gap-4 border-b simpeg-border p-5 sm:p-6">
                            <div className="min-w-0">
                                <p className="simpeg-muted text-sm">Statistik masa kerja</p>
                                <h2 id="tenure-dialog-title" className="simpeg-text mt-1 text-xl font-semibold">{selectedTenure.label}</h2>
                                <p id="tenure-dialog-description" className="simpeg-muted mt-1 text-sm">
                                    Pegawai aktif{selectedUnit ? ` di ${selectedOffice?.nama || selectedUnit}` : ''} dalam rentang masa kerja ini.
                                </p>
                            </div>
                            <button type="button" className="simpeg-utility inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border" onClick={closeTenureDialog} aria-label="Tutup daftar pegawai">
                                <X className="h-4 w-4" aria-hidden="true" />
                            </button>
                        </header>
                        <div className="min-h-0 flex-1 overflow-y-auto p-5 sm:p-6" aria-live="polite" aria-busy={tenureLoading}>
                            {tenureLoading && <p className="simpeg-muted py-8 text-center text-sm" role="status">Memuat daftar pegawai…</p>}
                            {!tenureLoading && tenureError && <p className="py-8 text-center text-sm text-red-600 dark:text-red-400" role="alert">{tenureError}</p>}
                            {!tenureLoading && !tenureError && tenureResult?.data?.length > 0 && (
                                <ul className="divide-y simpeg-border">
                                    {tenureResult.data.map((employee) => (
                                        <li key={employee.id_peg} className="flex flex-col gap-1 py-4 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                                            <div className="min-w-0">
                                                <p className="simpeg-text break-words text-sm font-medium">{employee.nama || 'Nama tidak tersedia'}</p>
                                                <p className="simpeg-muted mt-1 text-xs">ID pegawai: {employee.id_peg}</p>
                                            </div>
                                            <div className="shrink-0 text-xs sm:text-right">
                                                <p className="simpeg-text font-medium">{employee.masa_kerja} tahun masa kerja</p>
                                                <p className="simpeg-muted mt-1">Mulai kerja: {employee.tmt_kerja || '-'}</p>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            )}
                            {!tenureLoading && !tenureError && tenureResult && tenureResult.data?.length === 0 && (
                                <p className="simpeg-muted py-8 text-center text-sm">Belum ada pegawai dalam rentang masa kerja ini.</p>
                            )}
                        </div>
                        {tenureResult && tenureResult.last_page > 1 && (
                            <footer className="simpeg-tenure-pagination border-t simpeg-border p-4 sm:px-6">
                                <div className="hidden items-center justify-between gap-3 sm:flex">
                                    <p className="simpeg-muted text-xs">Halaman {tenureResult.current_page} dari {tenureResult.last_page} · {tenureResult.total} pegawai</p>
                                    <div className="flex gap-2">
                                        <button type="button" className="simpeg-utility inline-flex min-h-10 items-center gap-1 rounded-xl border px-3 text-sm disabled:cursor-not-allowed disabled:opacity-50" onClick={() => setTenurePage((page) => Math.max(1, page - 1))} disabled={tenureLoading || tenureResult.current_page <= 1}>
                                            <ChevronLeft className="h-4 w-4" aria-hidden="true" /> Sebelumnya
                                        </button>
                                        <button type="button" className="simpeg-utility inline-flex min-h-10 items-center gap-1 rounded-xl border px-3 text-sm disabled:cursor-not-allowed disabled:opacity-50" onClick={() => setTenurePage((page) => Math.min(tenureResult.last_page, page + 1))} disabled={tenureLoading || tenureResult.current_page >= tenureResult.last_page}>
                                            Berikutnya <ChevronRight className="h-4 w-4" aria-hidden="true" />
                                        </button>
                                    </div>
                                </div>
                                <div className="grid gap-2 sm:hidden">
                                    <div className="flex items-center justify-between gap-3">
                                        <button type="button" className="simpeg-utility inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border disabled:cursor-not-allowed disabled:opacity-50" onClick={() => setTenurePage((page) => Math.max(1, page - 1))} disabled={tenureLoading || tenurePage <= 1} aria-label="Halaman sebelumnya">
                                            <ChevronLeft className="h-5 w-5" aria-hidden="true" />
                                        </button>
                                        <p className="simpeg-muted text-center text-xs" aria-live="polite">
                                            Halaman <span className="simpeg-text font-semibold">{tenurePage}</span> dari {tenureResult.last_page}
                                        </p>
                                        <button type="button" className="simpeg-utility inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border disabled:cursor-not-allowed disabled:opacity-50" onClick={() => setTenurePage((page) => Math.min(tenureResult.last_page, page + 1))} disabled={tenureLoading || tenurePage >= tenureResult.last_page} aria-label="Halaman berikutnya">
                                            <ChevronRight className="h-5 w-5" aria-hidden="true" />
                                        </button>
                                    </div>
                                    <input
                                        type="range"
                                        className="simpeg-tenure-page-slider"
                                        min="1"
                                        max={tenureResult.last_page}
                                        step="1"
                                        value={Math.min(tenurePage, tenureResult.last_page)}
                                        onChange={(event) => setTenurePage(Number(event.target.value))}
                                        disabled={tenureLoading}
                                        aria-label="Pilih halaman daftar pegawai"
                                        aria-valuetext={`Halaman ${tenurePage} dari ${tenureResult.last_page}`}
                                    />
                                    <p className="simpeg-muted text-center text-[0.6875rem]">Geser untuk memilih halaman · {tenureResult.total} pegawai</p>
                                </div>
                            </footer>
                        )}
                    </div>
                )}
            </dialog>
        </LegacyPageShell>
    );
}
