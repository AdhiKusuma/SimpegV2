import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Activity,
    Banknote,
    Bell,
    BriefcaseBusiness,
    Building2,
    ChevronDown,
    ChevronRight,
    ClipboardCheck,
    ClipboardList,
    FileBarChart2,
    GraduationCap,
    LayoutDashboard,
    LogOut,
    Menu,
    PanelLeftClose,
    PanelLeftOpen,
    Search,
    Settings2,
    ShieldAlert,
    ShieldCheck,
    SlidersHorizontal,
    UserPlus,
    UserRound,
    UsersRound,
    Upload,
    X,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { Button } from '../Components/ui/button';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '../Components/ui/collapsible';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '../Components/ui/dropdown-menu';
import { Input } from '../Components/ui/input';
import LiveDateTime from './LiveDateTime';
import ThemeToggle from './ThemeToggle';

const navItem = (href, label, icon) => ({ href, label, icon, permission: permissionForHref(href) });

function permissionForHref(href) {
    if (href === '/dashboard') return 'dashboard.view';
    if (href === '/pegawai') return 'employees.view';
    if (href === '/pegawai/create' || href === '/pegawai/import') return 'employees.manage';
    if (href === '/pengangkatan') return 'appointments.view';
    if (href === '/mutasi') return 'mutations.view';
    if (href === '/pendidikan') return 'education.view';
    if (href === '/jabatan') return 'positions.view';
    if (href === '/diklat') return 'training.view';
    if (href === '/biaya-pendidikan') return 'training_costs.view';
    if (href === '/sertifikasi') return 'certifications.view';
    if (href === '/pelanggaran') return 'violations.view';
    if (href === '/laporan/nominatif') return 'reports.nominative';
    if (href === '/laporan/keadaan') return 'reports.condition';
    if (href === '/laporan/formasi') return 'reports.formation';
    if (href === '/laporan/rekap-diklat') return 'reports.training';
    if (href === '/laporan/rekap-biaya-pendidikan') return 'reports.education';
    if (href === '/payroll') return 'payroll.view';
    if (href === '/payroll/edit') return 'payroll.view';
    if (href === '/payroll/approvals') return 'payroll.approval.manage';
    if (href === '/users') return 'users.view';
    if (href === '/users/access') return 'users.manage';
    if (href === '/config/aplikasi') return 'config.manage';
    if (href === '/config/kuota-jabatan') return 'positions.quota.manage';
    if (href === '/otorisasi') return 'authorization.view';
    if (href === '/pengajuan') return 'authorization.history';
    if (href === '/profile') return 'profile.view';
    if (href.startsWith('/keluarga/')) return 'family.view';

    return undefined;
}

function filterNavigation(items, permissions) {
    return items.reduce((filtered, item) => {
        const children = item.items ? filterNavigation(item.items, permissions) : null;
        if (item.items && children.length === 0) {
            return filtered;
        }
        const hasPermission = Array.isArray(item.permission)
            ? item.permission.some((permission) => permissions.includes(permission))
            : permissions.includes(item.permission);
        if (!item.items && item.permission && !hasPermission) {
            return filtered;
        }

        filtered.push({ ...item, ...(item.items ? { items: children } : {}) });

        return filtered;
    }, []);
}

const navigationCatalog = [
    navItem('/dashboard', 'Dashboard', LayoutDashboard),
    {
        label: 'Data Pegawai',
        icon: UsersRound,
        items: [
            navItem('/pegawai', 'Daftar Pegawai', UsersRound),
            navItem('/pegawai/create', 'Tambah Pegawai', UserPlus),
            navItem('/pengangkatan', 'Pengangkatan Pegawai', BriefcaseBusiness),
            navItem('/pegawai/import', 'Import Excel', Upload),
            navItem('/mutasi', 'Penonaktifan', UserRound),
        ],
    },
    {
        label: 'Master Data',
        icon: Building2,
        items: [
            {
                label: 'Keluarga',
                icon: UsersRound,
                items: [
                    navItem('/keluarga/spouses', 'Suami Istri', UsersRound),
                    navItem('/keluarga/children', 'Anak', UsersRound),
                    navItem('/keluarga/parents', 'Orang Tua', UsersRound),
                ],
            },
            navItem('/jabatan', 'Jabatan', BriefcaseBusiness),
            navItem('/pendidikan', 'Pendidikan', GraduationCap),
            navItem('/diklat', 'Pelatihan', GraduationCap),
            navItem('/biaya-pendidikan', 'Daftar Kegiatan Diklat', ClipboardList),
            navItem('/sertifikasi', 'Sertifikasi', ShieldCheck),
            navItem('/pelanggaran', 'Pelanggaran', ShieldAlert),
        ],
    },
    {
        label: 'Laporan',
        icon: FileBarChart2,
        items: [
            navItem('/laporan/nominatif', 'Kepegawaian', FileBarChart2),
            navItem('/laporan/keadaan', 'Keadaan Pegawai', Activity),
            navItem('/laporan/formasi', 'Formasi Jabatan', BriefcaseBusiness),
            navItem('/laporan/rekap-diklat', 'Pelatihan Pegawai', GraduationCap),
            navItem('/laporan/rekap-biaya-pendidikan', 'Daftar Diklat', ClipboardList),
        ],
    },
    {
        label: 'Master Gaji',
        icon: Banknote,
        items: [
            navItem('/payroll', 'Payroll', Banknote),
            navItem('/payroll/edit', 'Edit Payroll', Banknote),
            navItem('/payroll/approvals', 'Approval Payroll', ClipboardCheck),
        ],
    },
    navItem('/otorisasi', 'Approval Pengajuan', ClipboardCheck),
    navItem('/pengajuan', 'Riwayat Pengajuan', ClipboardList),
    {
        label: 'Data User',
        icon: UserRound,
        items: [
            navItem('/users', 'Daftar User', UserRound),
            navItem('/users/access', 'Matriks akses', ShieldCheck),
        ],
    },
    navItem('/config/aplikasi', 'Pengaturan Aplikasi', Settings2),
    navItem('/config/kuota-jabatan', 'Kuota Jabatan', SlidersHorizontal),
    navItem('/profile', 'Profil Saya', UserRound),
];

function routeIsActive(url, href) {
    const path = (url || '').split('?')[0];

    return path === href || path.startsWith(`${href}/`);
}

function navigationItemIsActive(item, url) {
    return item.items
        ? item.items.some((child) => navigationItemIsActive(child, url))
        : routeIsActive(url, item.href);
}

function NavigationEntry({ item, url, onNavigate, onExpandSidebar, depth = 0, sidebarCollapsed = false }) {
    const active = navigationItemIsActive(item, url);
    const Icon = item.icon || ChevronRight;

    if (item.items) {
        return <NavigationGroup item={item} url={url} onNavigate={onNavigate} onExpandSidebar={onExpandSidebar} depth={depth} sidebarCollapsed={sidebarCollapsed} />;
    }

    return (
        <Link
            href={item.href}
            onClick={onNavigate}
            title={sidebarCollapsed ? item.label : undefined}
            className={`simpeg-sidebar-nav-link group inline-flex min-h-11 w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-medium transition focus:outline-none focus:ring-2 focus:ring-[#FAD873]/70 ${depth > 0 ? 'text-[0.8125rem]' : ''} ${active ? 'simpeg-nav-active shadow-sm' : 'simpeg-nav-link'}`}
        >
            <Icon className={`h-[1.05rem] w-[1.05rem] shrink-0 ${active ? 'simpeg-nav-active-icon' : 'simpeg-nav-icon'}`} strokeWidth={1.8} aria-hidden="true" />
            <span className="simpeg-sidebar-nav-label min-w-0 flex-1 truncate">{item.label}</span>
            {active && <span className="simpeg-sidebar-active-indicator h-1.5 w-1.5 shrink-0 rounded-full bg-[#FAD873]" aria-hidden="true" />}
        </Link>
    );
}

function NavigationGroup({ item, url, onNavigate, onExpandSidebar, depth, sidebarCollapsed = false }) {
    const active = navigationItemIsActive(item, url);
    const [open, setOpen] = useState(() => !sidebarCollapsed && active);
    const Icon = item.icon || ChevronRight;

    useEffect(() => {
        setOpen(!sidebarCollapsed && active);
    }, [active, sidebarCollapsed]);

    return (
        <Collapsible open={open} onOpenChange={setOpen} className={`simpeg-sidebar-nav-group w-full ${depth > 0 ? 'pl-2' : ''}`}>
            <CollapsibleTrigger asChild>
                <button
                    type="button"
                    onClick={(event) => {
                        if (sidebarCollapsed) {
                            event.preventDefault();
                            onExpandSidebar?.();
                        }
                    }}
                    title={sidebarCollapsed ? item.label : undefined}
                    className={`simpeg-sidebar-group-trigger group inline-flex min-h-11 w-full items-center justify-between gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-medium transition focus:outline-none focus:ring-2 focus:ring-[#FAD873]/70 ${active ? 'simpeg-nav-group-active' : 'simpeg-nav-group-link'}`}
                >
                    <span className="flex min-w-0 items-center gap-3">
                        <Icon className={`h-[1.05rem] w-[1.05rem] shrink-0 ${active ? 'simpeg-nav-group-active-icon' : 'simpeg-nav-icon'}`} strokeWidth={1.8} aria-hidden="true" />
                        <span className="simpeg-sidebar-group-label truncate">{item.label}</span>
                    </span>
                    <ChevronDown className={`simpeg-sidebar-group-chevron h-4 w-4 shrink-0 transition-transform ${active ? 'simpeg-nav-group-active-icon' : 'simpeg-nav-icon'} ${open ? 'rotate-180' : ''}`} strokeWidth={1.8} aria-hidden="true" />
                </button>
            </CollapsibleTrigger>
            <CollapsibleContent className="simpeg-sidebar-nav-content mt-1 flex flex-col gap-1 border-l border-slate-200 pl-3 dark:border-slate-700">
                {item.items.map((child) => <NavigationEntry key={child.href || child.label} item={child} url={url} onNavigate={onNavigate} onExpandSidebar={onExpandSidebar} depth={depth + 1} sidebarCollapsed={sidebarCollapsed} />)}
            </CollapsibleContent>
        </Collapsible>
    );
}

function initials(name) {
    return String(name || 'SIMPEG')
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase();
}

export default function LegacyPageShell({ title, children }) {
    const page = usePage();
    const { appConfig, auth, notificationSummary } = page.props;
    const { url } = page;
    const [mobileNavOpen, setMobileNavOpen] = useState(false);
    const [sidebarCollapsed, setSidebarCollapsed] = useState(() => {
        if (typeof window === 'undefined') {
            return false;
        }

        try {
            return window.localStorage.getItem('simpeg-sidebar-collapsed') === '1';
        } catch {
            return false;
        }
    });
    const [profileOpen, setProfileOpen] = useState(false);
    const [searchOpen, setSearchOpen] = useState(false);
    const [notificationsOpen, setNotificationsOpen] = useState(false);
    const [globalSearch, setGlobalSearch] = useState('');
    const user = auth?.user ?? {};
    const permissions = user.permissions || [];
    const canSearchEmployees = permissions.includes('employees.view');
    const canViewNotifications = permissions.includes('notifications.view');
    const unreadCount = Number(notificationSummary?.unread_count || 0);
    const latestNotifications = Array.isArray(notificationSummary?.latest) ? notificationSummary.latest : [];
    const homeHref = permissions.includes('dashboard.view')
        ? '/dashboard'
        : '/profile';
    const navigation = filterNavigation(navigationCatalog, permissions);
    const displayName = user.nama_user || 'Pengguna SIMPEG';
    const roleLabel = user.hak_akses || 'Pengguna';
    const appName = appConfig?.name || 'SIMPEG';
    const appAlias = appConfig?.alias || 'SIMPEG';
    const appDescription = appConfig?.description || 'Kepegawaian';
    const appLogoUrl = appConfig?.logoUrl || '/images/logo-simpeg-256.png';
    const profileHref = '/profile';
    const profileLabel = 'Profil Saya';
    const pageLabel = useMemo(() => title || 'SIMPEG', [title]);
    const handleNavigation = () => setMobileNavOpen(false);
    const expandSidebar = () => setSidebarCollapsed(false);

    useEffect(() => {
        try {
            window.localStorage.setItem('simpeg-sidebar-collapsed', sidebarCollapsed ? '1' : '0');
        } catch {
            // The collapse control still works for the current page when storage is unavailable.
        }
    }, [sidebarCollapsed]);

    useEffect(() => {
        if (!mobileNavOpen && !profileOpen && !searchOpen && !notificationsOpen) {
            return undefined;
        }

        function closeMenus(event) {
            if (event.key === 'Escape') {
                setMobileNavOpen(false);
                setProfileOpen(false);
                setSearchOpen(false);
                setNotificationsOpen(false);
            }
        }

        document.addEventListener('keydown', closeMenus);

        return () => document.removeEventListener('keydown', closeMenus);
    }, [mobileNavOpen, profileOpen, searchOpen, notificationsOpen]);

    useEffect(() => {
        if (!notificationsOpen) {
            return undefined;
        }

        function closeOnOutsideClick(event) {
            if (!event.target.closest('[data-notification-popover]')) {
                setNotificationsOpen(false);
            }
        }

        document.addEventListener('pointerdown', closeOnOutsideClick);

        return () => document.removeEventListener('pointerdown', closeOnOutsideClick);
    }, [notificationsOpen]);

    function submitGlobalSearch(event) {
        event.preventDefault();
        if (!canSearchEmployees) {
            return;
        }

        const query = globalSearch.trim();

        if (query) {
            setSearchOpen(false);
            router.get('/pegawai', { search: query }, { preserveState: true, preserveScroll: true });
        }
    }

    const sidebar = (
        <aside id="primary-navigation" className={`simpeg-sidebar fixed inset-y-0 left-0 z-50 flex h-dvh w-[18rem] flex-col overflow-hidden border-r shadow-xl transition-[width,transform] duration-300 lg:sticky lg:top-0 lg:inset-x-auto lg:bottom-auto lg:z-auto lg:h-dvh lg:min-h-0 lg:w-[17rem] lg:self-start lg:translate-x-0 lg:shadow-none ${sidebarCollapsed ? 'simpeg-sidebar-collapsed' : ''} ${mobileNavOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'}`}>
            <div className="simpeg-sidebar-inner flex min-h-0 flex-1 flex-col p-4 sm:p-5">
                <div className="simpeg-sidebar-brand relative flex items-center gap-2">
                    <Link href={homeHref} onClick={() => setMobileNavOpen(false)} className="simpeg-sidebar-brand-link flex min-w-0 flex-1 items-center gap-3 rounded-2xl px-2 py-2 pr-12 text-left focus:outline-none focus:ring-2 focus:ring-[#FAD873]/70 lg:pr-2">
                        <span className="simpeg-sidebar-logo flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-[16px]">
                            <img src={appLogoUrl} alt={`Logo ${appName}`} width="44" height="44" decoding="async" className="h-full w-full object-contain" />
                        </span>
                        <span className="simpeg-sidebar-brand-details min-w-0">
                            <span className="block truncate text-base font-medium">{appAlias || appName}</span>
                            <span className="simpeg-muted block truncate text-xs">{appDescription}</span>
                        </span>
                    </Link>
                    <button type="button" className="simpeg-sidebar-collapse-toggle simpeg-utility hidden min-h-11 min-w-11 shrink-0 items-center justify-center rounded-xl border lg:inline-flex" onClick={() => setSidebarCollapsed((collapsed) => !collapsed)} aria-label={sidebarCollapsed ? 'Tampilkan label navigasi' : 'Sembunyikan sidebar'} aria-expanded={!sidebarCollapsed} aria-controls="primary-navigation" title={sidebarCollapsed ? 'Tampilkan label navigasi' : 'Sembunyikan sidebar'}>
                        {sidebarCollapsed ? <PanelLeftOpen className="h-5 w-5" strokeWidth={1.8} aria-hidden="true" /> : <PanelLeftClose className="h-5 w-5" strokeWidth={1.8} aria-hidden="true" />}
                    </button>
                    <button type="button" className="simpeg-utility absolute right-0 top-0 inline-flex min-h-11 min-w-11 items-center justify-center rounded-xl border lg:hidden" onClick={() => setMobileNavOpen(false)} aria-label="Tutup navigasi">
                        <X className="h-5 w-5" strokeWidth={1.8} aria-hidden="true" />
                    </button>
                </div>

                <div className="mt-8 min-h-0 flex-1 overflow-y-auto overscroll-contain pr-1">
                    <p className="simpeg-sidebar-section-label simpeg-muted mb-3 px-3 text-xs font-medium">Menu utama</p>
                    <nav className="flex flex-col gap-1.5" aria-label="Navigasi utama">
                        {navigation.map((item) => <NavigationEntry key={item.href || item.label} item={item} url={url} onNavigate={handleNavigation} onExpandSidebar={expandSidebar} sidebarCollapsed={sidebarCollapsed} />)}
                    </nav>
                </div>

                <div className="simpeg-border mt-auto shrink-0 border-t pt-4">
                    <div className="simpeg-sidebar-profile mb-3 flex items-center gap-3 rounded-2xl bg-slate-100 p-3 dark:bg-slate-800">
                        <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-900 text-xs font-medium text-[#FAD873] dark:bg-slate-700">{initials(displayName)}</span>
                        <div className="simpeg-sidebar-profile-details min-w-0">
                            <p className="simpeg-text truncate text-sm font-medium">{displayName}</p>
                            <p className="simpeg-muted truncate text-xs">{roleLabel}</p>
                        </div>
                    </div>
                    <Link href="/logout" method="post" as="button" className="simpeg-sidebar-logout inline-flex min-h-11 w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-rose-700 transition hover:bg-rose-50 focus:outline-none focus:ring-2 focus:ring-[#FAD873]/70 dark:text-rose-300 dark:hover:bg-rose-950/40">
                        <LogOut className="h-[1.05rem] w-[1.05rem]" strokeWidth={1.8} aria-hidden="true" />
                        <span className="simpeg-sidebar-logout-label">Keluar</span>
                    </Link>
                </div>
            </div>
        </aside>
    );

    return (
        <>
            <Head title={title} />
            <div className={`simpeg-app-canvas min-h-dvh ${searchOpen ? 'simpeg-search-active' : ''}`}>
                <div className="simpeg-shell-frame flex min-h-dvh w-full overflow-x-clip">
                    {sidebar}
                    {mobileNavOpen && <button type="button" className="fixed inset-0 z-40 bg-[#131822]/60 backdrop-blur-sm lg:hidden" onClick={() => setMobileNavOpen(false)} aria-label="Tutup menu" />}

                    <div className="simpeg-shell-content min-w-0 flex-1">
                    <header className="simpeg-header sticky top-0 z-30 border-b backdrop-blur-xl">
                        <div className="mx-auto flex min-h-[4.75rem] min-w-0 max-w-[1600px] items-center gap-3 px-3 sm:px-6 lg:px-8">
                            <Button type="button" variant="outline" size="icon" className="simpeg-utility shrink-0 lg:hidden" onClick={() => setMobileNavOpen(true)} aria-expanded={mobileNavOpen} aria-controls="primary-navigation" aria-label="Buka navigasi">
                                <Menu className="h-5 w-5" strokeWidth={1.8} aria-hidden="true" />
                            </Button>
                            <div className="simpeg-header-context hidden min-w-0 shrink-0 sm:block">
                                <p className="simpeg-muted text-xs font-medium">SIMPEG / {roleLabel}</p>
                                <p className="simpeg-text truncate text-base font-medium">{pageLabel}</p>
                            </div>

                            <LiveDateTime />

                            {canSearchEmployees && <button type="button" className="simpeg-search-trigger min-w-0 max-w-xl flex-1" onClick={() => setSearchOpen(true)} aria-label="Buka pencarian pegawai">
                                <Search className="h-4 w-4 shrink-0" strokeWidth={1.8} aria-hidden="true" />
                                <span className="min-w-0 flex-1 truncate text-left">{globalSearch || 'Cari pegawai / NIK'}</span>
                            </button>}

                            <div className="ml-auto flex shrink-0 items-center gap-2 sm:gap-3">
                            <ThemeToggle />

                            {canViewNotifications && <div className="relative shrink-0" data-notification-popover>
                                <button
                                    type="button"
                                    className="simpeg-utility relative inline-flex min-h-11 min-w-11 items-center justify-center rounded-xl border shadow-sm focus:outline-none focus:ring-2 focus:ring-[var(--simpeg-primary-border)]"
                                    onClick={() => setNotificationsOpen((open) => !open)}
                                    aria-label={unreadCount > 0 ? `Notifikasi, ${unreadCount} belum dibaca` : 'Notifikasi'}
                                    aria-expanded={notificationsOpen}
                                    aria-haspopup="dialog"
                                >
                                    <Bell className="h-5 w-5" strokeWidth={1.8} aria-hidden="true" />
                                    {unreadCount > 0 && <span className="simpeg-notification-badge" aria-hidden="true">{unreadCount > 9 ? '9+' : unreadCount}</span>}
                                </button>

                                {notificationsOpen && <div className="simpeg-notification-popover fixed inset-x-3 top-[calc(4.75rem+0.6rem)] z-50 w-auto overflow-hidden rounded-2xl border shadow-2xl sm:absolute sm:inset-x-auto sm:right-0 sm:top-[calc(100%+0.6rem)] sm:w-[min(24rem,calc(100vw-1.5rem))]" role="dialog" aria-label="Notifikasi terbaru">
                                    <div className="simpeg-border flex items-center justify-between gap-3 border-b px-4 py-3">
                                        <div>
                                            <p className="simpeg-text text-sm font-semibold">Notifikasi</p>
                                            <p className="simpeg-muted mt-0.5 text-xs">Pantau pengajuan dan hasil persetujuan.</p>
                                        </div>
                                        {unreadCount > 0 && <span className="simpeg-notification-count">{unreadCount} belum dibaca</span>}
                                    </div>

                                    {latestNotifications.length > 0 ? <div className="max-h-80 overflow-y-auto p-2">
                                        {latestNotifications.map((notification) => <Link
                                            key={notification.id_notif}
                                            href={notification.action_url || '/notifikasi'}
                                            onClick={() => setNotificationsOpen(false)}
                                            className="simpeg-notification-item block rounded-xl px-3 py-3 transition focus:outline-none focus:ring-2 focus:ring-[var(--simpeg-primary-border)]"
                                        >
                                            <div className="flex items-start justify-between gap-3">
                                                <p className="simpeg-text min-w-0 text-sm font-medium">{notification.judul || 'Pemberitahuan'}</p>
                                                {notification.status_baca === 'unread' && <span className="simpeg-notification-dot" aria-label="Belum dibaca" />}
                                            </div>
                                            <p className="simpeg-muted mt-1 line-clamp-2 text-xs leading-5">{notification.pesan || '-'}</p>
                                            <p className="simpeg-muted mt-2 text-[0.6875rem]">{notification.waktu || '-'}</p>
                                        </Link>)}
                                    </div> : <div className="px-4 py-8 text-center">
                                        <Bell className="simpeg-muted mx-auto h-5 w-5" strokeWidth={1.8} aria-hidden="true" />
                                        <p className="simpeg-text mt-3 text-sm font-medium">Belum ada notifikasi baru.</p>
                                        <p className="simpeg-muted mt-1 text-xs">Status pengajuan akan tampil di sini.</p>
                                    </div>}

                                    <div className="simpeg-border border-t p-2">
                                        <Link href="/notifikasi" onClick={() => setNotificationsOpen(false)} className="simpeg-notification-all inline-flex min-h-10 w-full items-center justify-center rounded-xl px-3 py-2 text-sm font-medium transition focus:outline-none focus:ring-2 focus:ring-[var(--simpeg-primary-border)]">Lihat semua notifikasi</Link>
                                    </div>
                                </div>}
                            </div>}

                            <div className="relative shrink-0">
                                <DropdownMenu open={profileOpen} onOpenChange={setProfileOpen}>
                                    <DropdownMenuTrigger asChild>
                                        <button type="button" className="simpeg-utility inline-flex min-h-11 max-w-[11rem] items-center gap-2 rounded-2xl border px-2.5 shadow-sm focus:outline-none focus:ring-2 focus:ring-[#F5CE59]/70 sm:gap-3 sm:px-3">
                                            <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-900 text-[0.625rem] font-medium text-[#FAD873] dark:bg-slate-700">{initials(displayName)}</span>
                                            <span className="hidden min-w-0 text-left sm:block">
                                                <span className="simpeg-text block truncate text-xs font-medium">{displayName}</span>
                                                <span className="simpeg-muted block truncate text-xs">{roleLabel}</span>
                                            </span>
                                            <ChevronDown className={`hidden h-4 w-4 shrink-0 text-slate-400 transition-transform sm:block ${profileOpen ? 'rotate-180' : ''}`} strokeWidth={1.8} aria-hidden="true" />
                                        </button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent className="simpeg-account-menu absolute right-0 top-[calc(100%+0.6rem)] w-60 backdrop-blur-xl">
                                        <div className="simpeg-border border-b px-3 py-2.5">
                                            <p className="simpeg-text truncate text-sm font-medium">{displayName}</p>
                                            <p className="simpeg-muted truncate text-xs">{roleLabel}</p>
                                        </div>
                                        <DropdownMenuItem asChild>
                                            <Link href={profileHref}>
                                                <UserRound className="h-4 w-4" strokeWidth={1.8} aria-hidden="true" />
                                                {profileLabel}
                                            </Link>
                                        </DropdownMenuItem>
                                        <DropdownMenuItem asChild className="simpeg-account-menu-logout text-rose-700 hover:bg-rose-50 dark:text-rose-300 dark:hover:bg-rose-950/40">
                                            <Link href="/logout" method="post" as="button">
                                                <LogOut className="h-4 w-4" strokeWidth={1.8} aria-hidden="true" />
                                                Keluar
                                            </Link>
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </div>
                            </div>
                        </div>
                    </header>

                    <main className="mx-auto min-w-0 max-w-[1600px] px-3 py-5 sm:px-6 sm:py-7 lg:px-8 lg:py-8">{children}</main>
                    </div>
                </div>
            </div>
            {canSearchEmployees && searchOpen && <div className="simpeg-search-overlay" onMouseDown={(event) => { if (event.target === event.currentTarget) setSearchOpen(false); }}>
                <div className="simpeg-search-dialog" role="dialog" aria-modal="true" aria-labelledby="global-search-title">
                    <p id="global-search-title" className="sr-only">Cari pegawai atau NIK</p>
                    <form onSubmit={submitGlobalSearch}>
                        <div className="simpeg-search-dialog-input">
                            <Search className="h-5 w-5 shrink-0" strokeWidth={1.8} aria-hidden="true" />
                            <label className="sr-only" htmlFor="global-employee-search">Cari pegawai atau NIK</label>
                            <Input unstyled autoFocus id="global-employee-search" type="search" value={globalSearch} onChange={(event) => setGlobalSearch(event.target.value)} placeholder="Cari pegawai atau NIK" aria-label="Cari pegawai atau NIK" className="simpeg-text min-w-0 flex-1 bg-transparent px-2 text-base outline-none placeholder:text-[var(--simpeg-subtle)]" />
                            <button type="button" className="simpeg-search-close" onClick={() => setSearchOpen(false)} aria-label="Tutup pencarian"><kbd>Esc</kbd></button>
                        </div>
                    </form>
                    <div className="simpeg-search-dialog-footer">
                        <span>Masukkan nama, ID pegawai, atau NIK.</span>
                        <span className="hidden sm:inline">Enter untuk mencari · Esc untuk menutup</span>
                    </div>
                </div>
            </div>}
        </>
    );
}
