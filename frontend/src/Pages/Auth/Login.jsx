import { Head, useForm, usePage } from '@inertiajs/react';
import { Clock3, ExternalLink, Eye, EyeOff, Headset, ShieldCheck, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { BackgroundGrid } from '../../components/ui/background-grid';
import { Button } from '../../components/ui/button';
import { Label } from '../../components/ui/label';
import { Form } from '../../components/ui/form';
import { Input } from '../../components/ui/input';
import ThemeToggle from '../../Components/ThemeToggle';

const slides = [
    {
        title: <>Human Capital<br />System</>,
        description: 'Kelola data kepegawaian dalam satu ruang kerja yang mengikuti alur SIMPEG.',
        resource: {
            title: 'Layanan pengaduan konsumen',
            description: 'Buka kanal resmi OJK untuk informasi dan layanan pengaduan konsumen di sektor jasa keuangan.',
            label: 'Lihat kanal pengaduan konsumen',
            source: 'OJK',
            url: 'https://www.ojk.go.id/id/regulasi/Pages/Layanan-Pengaduan-Konsumen-di-Sektor-Jasa-Keuangan.aspx',
        },
    },
    {
        title: <>Data Kepegawaian<br />Terukur</>,
        description: 'Baca ringkasan pegawai, jabatan, pendidikan, dan laporan dari data yang tersimpan.',
        resource: {
            title: 'Kampanye anti-scam OJK',
            description: 'Baca siaran pers OJK tentang kampanye nasional untuk melawan penipuan dan aktivitas keuangan ilegal.',
            label: 'Baca kampanye anti-scam OJK',
            source: 'OJK',
            url: 'https://www.ojk.go.id/en/berita-dan-kegiatan/siaran-pers/Pages/The-Rise-of-Scams-OJK-and-the-Government-Launch-National-Campaign-to-Combat-Scam-and-Illegal-Financial-Activities.aspx',
        },
    },
    {
        title: <>Akses Sesuai<br />Peran</>,
        description: 'Menu dan data mengikuti hak akses yang tersedia pada sistem kepegawaian.',
        resource: {
            title: 'Indonesia Anti-Scam Centre',
            description: 'Kunjungi portal IASC untuk membaca informasi dan mengakses layanan pelaporan penipuan transaksi keuangan.',
            label: 'Cek Indonesia Anti-Scam Centre',
            source: 'OJK IASC',
            url: 'https://iasc.ojk.go.id/',
        },
    },
];

export default function Login({ appName, appConfig }) {
    const { flash } = usePage().props;
    const [activeSlide, setActiveSlide] = useState(0);
    const [showPassword, setShowPassword] = useState(false);
    const [showSupport, setShowSupport] = useState(false);
    const [showResource, setShowResource] = useState(false);
    const [showSessionExpired, setShowSessionExpired] = useState(Boolean(flash?.session_expired));
    const [showLoginRateLimit, setShowLoginRateLimit] = useState(false);
    const [currentTime, setCurrentTime] = useState(() => Date.now());
    const { data, setData, post, processing, errors } = useForm({
        id_user: '',
        password: '',
    });
    const loginRetryAt = Number(flash?.login_rate_limit?.retry_at) || null;
    const secondsUntilLoginRetry = loginRetryAt
        ? Math.max(0, loginRetryAt - Math.floor(currentTime / 1000))
        : 0;
    const loginWaitLabel = `${String(Math.floor(secondsUntilLoginRetry / 60)).padStart(2, '0')}:${String(secondsUntilLoginRetry % 60).padStart(2, '0')}`;

    useEffect(() => {
        if (!loginRetryAt) {
            return undefined;
        }

        setCurrentTime(Date.now());
        setShowLoginRateLimit(true);
        setData('password', '');

        const timer = window.setInterval(() => {
            const time = Date.now();
            setCurrentTime(time);

            if (Math.floor(time / 1000) >= loginRetryAt) {
                window.clearInterval(timer);
            }
        }, 1000);

        return () => window.clearInterval(timer);
    }, [loginRetryAt, setData]);

    useEffect(() => {
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const mobileViewport = window.matchMedia('(max-width: 767px)').matches;

        if (reducedMotion || mobileViewport) {
            return undefined;
        }

        const timer = window.setInterval(() => {
            setActiveSlide((current) => (current + 1) % slides.length);
        }, 5000);

        return () => window.clearInterval(timer);
    }, []);

    useEffect(() => {
        if (!showSupport) {
            return undefined;
        }

        const previouslyFocused = document.activeElement;
        const closeOnEscape = (event) => {
            if (event.key === 'Escape') {
                setShowSupport(false);
            }
        };

        document.body.style.overflow = 'hidden';
        document.addEventListener('keydown', closeOnEscape);
        window.setTimeout(() => document.getElementById('support-dialog-close')?.focus(), 0);

        return () => {
            document.body.style.overflow = '';
            document.removeEventListener('keydown', closeOnEscape);
            previouslyFocused?.focus?.();
        };
    }, [showSupport]);

    useEffect(() => {
        if (!showResource) {
            return undefined;
        }

        const previouslyFocused = document.activeElement;
        const closeOnEscape = (event) => {
            if (event.key === 'Escape') {
                setShowResource(false);
            }
        };

        document.body.style.overflow = 'hidden';
        document.addEventListener('keydown', closeOnEscape);
        window.setTimeout(() => document.getElementById('resource-dialog-close')?.focus(), 0);

        return () => {
            document.body.style.overflow = '';
            document.removeEventListener('keydown', closeOnEscape);
            previouslyFocused?.focus?.();
        };
    }, [showResource]);

    useEffect(() => {
        if (!showSessionExpired) {
            return undefined;
        }

        const previouslyFocused = document.activeElement;
        const closeOnEscape = (event) => {
            if (event.key === 'Escape') {
                setShowSessionExpired(false);
            }
        };

        document.body.style.overflow = 'hidden';
        document.addEventListener('keydown', closeOnEscape);
        window.setTimeout(() => document.getElementById('session-expired-dialog-close')?.focus(), 0);

        return () => {
            document.body.style.overflow = '';
            document.removeEventListener('keydown', closeOnEscape);
            previouslyFocused?.focus?.();
        };
    }, [showSessionExpired]);

    useEffect(() => {
        if (!showLoginRateLimit) {
            return undefined;
        }

        const previouslyFocused = document.activeElement;
        const handleDialogKeydown = (event) => {
            if (event.key === 'Escape') {
                setShowLoginRateLimit(false);
                return;
            }

            if (event.key === 'Tab') {
                const focusable = document.querySelectorAll('.simpeg-login-rate-limit-dialog button:not([disabled])');
                const first = focusable[0];
                const last = focusable[focusable.length - 1];

                if (event.shiftKey && document.activeElement === first) {
                    event.preventDefault();
                    last?.focus();
                } else if (!event.shiftKey && document.activeElement === last) {
                    event.preventDefault();
                    first?.focus();
                }
            }
        };

        document.body.style.overflow = 'hidden';
        document.addEventListener('keydown', handleDialogKeydown);
        window.setTimeout(() => document.getElementById('login-rate-limit-dialog-close')?.focus(), 0);

        return () => {
            document.body.style.overflow = '';
            document.removeEventListener('keydown', handleDialogKeydown);
            previouslyFocused?.focus?.();
        };
    }, [showLoginRateLimit]);

    function submit(event) {
        event.preventDefault();
        post('/login');
    }

    const slide = slides[activeSlide];
    const resolvedAppName = appConfig?.name || appName || 'SIMPEG';
    const resolvedAppAlias = appConfig?.alias || 'SIMPEG';
    const resolvedAppDescription = appConfig?.description || 'Kepegawaian';
    const resolvedAppLogoUrl = appConfig?.logoUrl || '/images/logo-simpeg-256.png';

    return (
        <>
            <Head title="Login" />
            <BackgroundGrid as="main" className="simpeg-login-page relative flex min-h-dvh items-center justify-center overflow-hidden px-4 py-6 sm:px-6 lg:px-10">
                <div className="simpeg-login-shell relative z-10 mx-auto w-full max-w-6xl">
                    <header className="simpeg-login-topbar">
                        <div className="simpeg-login-brand">
                            <span className="simpeg-login-brand-mark">
                                <img src={resolvedAppLogoUrl} alt={`Logo ${resolvedAppName}`} width="52" height="52" fetchPriority="high" decoding="async" className="h-full w-full object-contain" />
                            </span>
                            <div>
                                <p className="simpeg-login-brand-name">{resolvedAppAlias || resolvedAppName}</p>
                                <p className="simpeg-login-brand-caption">{resolvedAppDescription}</p>
                            </div>
                        </div>
                        <div className="simpeg-login-topbar-actions">
                            <div className="simpeg-login-topbar-meta">
                                <span>{resolvedAppName}</span>
                                <span>Akses aman</span>
                            </div>
                            <ThemeToggle />
                        </div>
                    </header>

                    <div className="simpeg-login-stage">
                        <section className="simpeg-login-clean-form" aria-labelledby="login-title">
                            <div className="simpeg-login-form-heading">
                                <p className="simpeg-login-form-kicker">Selamat datang</p>
                                <h2 id="login-title">Masuk ke SIMPEG</h2>
                                <p>Gunakan ID Pegawai dan password akun SIMPEG untuk melanjutkan.</p>
                            </div>

                            <Form onSubmit={submit} noValidate className="simpeg-login-form">
                                <div>
                                    <Label htmlFor="id_user" className="simpeg-login-label">ID Pegawai</Label>
                                    <Input
                                        id="id_user"
                                        type="text"
                                        value={data.id_user}
                                        onChange={(event) => setData('id_user', event.target.value)}
                                        placeholder="Masukkan ID Pegawai"
                                        autoComplete="username"
                                        autoFocus
                                        required
                                        aria-invalid={Boolean(errors.id_user)}
                                        aria-describedby={errors.id_user ? 'login-id-error' : undefined}
                                    />
                                    {errors.id_user && <p id="login-id-error" className="mt-2 text-sm text-rose-700" role="alert">{errors.id_user}</p>}
                                </div>

                                <div>
                                    <Label htmlFor="password" className="simpeg-login-label">Password</Label>
                                    <div className="relative">
                                        <Input
                                            id="password"
                                            type={showPassword ? 'text' : 'password'}
                                            value={data.password}
                                            onChange={(event) => setData('password', event.target.value)}
                                            placeholder="Masukkan password"
                                            autoComplete="current-password"
                                            required
                                            aria-invalid={Boolean(errors.password)}
                                            aria-describedby={errors.password ? 'login-password-error' : undefined}
                                            className="pr-14"
                                        />
                                        <button
                                            type="button"
                                            onClick={() => setShowPassword((visible) => !visible)}
                                            aria-label={showPassword ? 'Sembunyikan password' : 'Tampilkan password'}
                                            className="simpeg-login-password-toggle simpeg-muted absolute right-1 top-1/2 inline-flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-lg transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--simpeg-primary)]"
                                        >
                                            {showPassword ? <EyeOff className="h-4 w-4" strokeWidth={1.8} aria-hidden="true" /> : <Eye className="h-4 w-4" strokeWidth={1.8} aria-hidden="true" />}
                                        </button>
                                    </div>
                                    {errors.password && <p id="login-password-error" className="mt-2 text-sm text-rose-700" role="alert">{errors.password}</p>}
                                </div>

                                <Button type="submit" disabled={processing || secondsUntilLoginRetry > 0} className="simpeg-login-submit w-full">
                                    {processing ? 'Memproses...' : secondsUntilLoginRetry > 0 ? `Coba lagi dalam ${loginWaitLabel}` : 'Masuk ke SIMPEG'}
                                </Button>
                                {secondsUntilLoginRetry > 0 && (
                                    <p className="simpeg-login-rate-limit-inline">
                                        Percobaan login dibatasi sementara. Anda dapat mencoba kembali dalam {loginWaitLabel}.
                                    </p>
                                )}
                            </Form>

                            <div className="simpeg-login-form-footer">
                                <span className="simpeg-login-footer-line" aria-hidden="true" />
                                <button type="button" className="simpeg-login-support-trigger" onClick={() => setShowSupport(true)}>
                                    Belum memiliki akun? <span>Hubungi Bagian SDM atau IT</span>
                                </button>
                                <span className="simpeg-login-footer-line" aria-hidden="true" />
                            </div>
                        </section>

                        <aside className="simpeg-login-context" aria-labelledby="login-info-title" aria-live="polite">
                            <span className="simpeg-login-context-line" aria-hidden="true" />
                            <div>
                                <div className="simpeg-login-slide-meta">
                                    <span>0{activeSlide + 1}</span>
                                    <span>03</span>
                                </div>
                                <h2 id="login-info-title">{slide.title}</h2>
                                <p>{slide.description}</p>
                                <div className="simpeg-login-dots" role="tablist" aria-label="Pilihan informasi">
                                    {slides.map((item, index) => (
                                        <button
                                            key={item.description}
                                            type="button"
                                            role="tab"
                                            aria-selected={activeSlide === index}
                                            aria-label={`Tampilkan informasi ${index + 1}`}
                                            onClick={() => setActiveSlide(index)}
                                            className="group flex min-h-8 min-w-8 items-center justify-center rounded-full focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--simpeg-primary)]"
                                        >
                                            <span aria-hidden="true" className={`block h-1.5 rounded-full transition-all ${activeSlide === index ? 'w-8 bg-[var(--simpeg-primary)]' : 'w-1.5 bg-[var(--simpeg-border)] group-hover:bg-[var(--simpeg-muted)]'}`} />
                                        </button>
                                    ))}
                                </div>
                                <button type="button" className="simpeg-login-resource-trigger" onClick={() => setShowResource(true)}>
                                    <span>{slide.resource.label}</span>
                                    <ExternalLink className="h-4 w-4 shrink-0" strokeWidth={1.8} aria-hidden="true" />
                                </button>
                                <div className="simpeg-login-access-note">
                                    <ShieldCheck className="h-5 w-5 shrink-0" strokeWidth={1.8} aria-hidden="true" />
                                    <span>Akses mengikuti peran akun dan data yang tersedia.</span>
                                </div>
                            </div>
                        </aside>
                    </div>
                </div>
            </BackgroundGrid>
            {showSessionExpired && (
                <div className="simpeg-session-expired-overlay" onMouseDown={(event) => { if (event.target === event.currentTarget) setShowSessionExpired(false); }}>
                    <section className="simpeg-session-expired-dialog" role="dialog" aria-modal="true" aria-labelledby="session-expired-dialog-title" aria-describedby="session-expired-dialog-description">
                        <div className="simpeg-session-expired-topline">
                            <span className="simpeg-session-expired-icon" aria-hidden="true">
                                <Clock3 className="h-6 w-6" strokeWidth={1.8} />
                            </span>
                            <button id="session-expired-dialog-close" type="button" className="simpeg-session-expired-close" onClick={() => setShowSessionExpired(false)} aria-label="Tutup pemberitahuan sesi">
                                <X className="h-5 w-5" strokeWidth={1.8} />
                            </button>
                        </div>
                        <p className="simpeg-session-expired-kicker">Keamanan akun</p>
                        <h2 id="session-expired-dialog-title">Sesi login berakhir</h2>
                        <p id="session-expired-dialog-description">Sesi Anda sudah tidak aktif. Silakan masuk kembali untuk melanjutkan pekerjaan dengan aman.</p>
                        <button type="button" className="simpeg-session-expired-action" onClick={() => setShowSessionExpired(false)}>Mengerti</button>
                    </section>
                </div>
            )}
            {showLoginRateLimit && loginRetryAt && (
                <div className="simpeg-login-rate-limit-overlay" onMouseDown={(event) => { if (event.target === event.currentTarget) setShowLoginRateLimit(false); }}>
                    <section className="simpeg-login-rate-limit-dialog" role="dialog" aria-modal="true" aria-labelledby="login-rate-limit-title" aria-describedby="login-rate-limit-description">
                        <div className="simpeg-login-rate-limit-topline">
                            <span className="simpeg-login-rate-limit-icon" aria-hidden="true">
                                <Clock3 className="h-6 w-6" strokeWidth={1.8} />
                            </span>
                            <button id="login-rate-limit-dialog-close" type="button" className="simpeg-login-rate-limit-close" onClick={() => setShowLoginRateLimit(false)} aria-label="Tutup pemberitahuan batas login">
                                <X className="h-5 w-5" strokeWidth={1.8} aria-hidden="true" />
                            </button>
                        </div>
                        <p className="simpeg-login-rate-limit-kicker">Keamanan akun</p>
                        <h2 id="login-rate-limit-title">Terlalu banyak percobaan login</h2>
                        <p id="login-rate-limit-description">Untuk melindungi akun, login dibatasi sementara. Silakan tunggu sebelum mencoba kembali.</p>
                        <div className="simpeg-login-rate-limit-countdown" aria-live="off">
                            <span>Waktu tunggu tersisa</span>
                            <strong>{secondsUntilLoginRetry > 0 ? loginWaitLabel : '00:00'}</strong>
                        </div>
                        <p className="sr-only" role="status">
                            {secondsUntilLoginRetry === 0 ? 'Waktu tunggu selesai. Anda dapat mencoba login kembali.' : ''}
                        </p>
                        <button
                            type="button"
                            className="simpeg-login-rate-limit-action"
                            onClick={() => {
                                setShowLoginRateLimit(false);
                                window.setTimeout(() => document.getElementById('id_user')?.focus(), 0);
                            }}
                        >
                            {secondsUntilLoginRetry > 0 ? 'Mengerti' : 'Coba lagi'}
                        </button>
                    </section>
                </div>
            )}
            {showSupport && (
                <div className="simpeg-support-overlay" onMouseDown={(event) => { if (event.target === event.currentTarget) setShowSupport(false); }}>
                    <section className="simpeg-support-dialog" role="dialog" aria-modal="true" aria-labelledby="support-dialog-title" aria-describedby="support-dialog-description">
                        <div className="simpeg-support-dialog-topline">
                            <span className="simpeg-support-dialog-icon" aria-hidden="true">
                                <Headset className="h-6 w-6" strokeWidth={1.8} />
                            </span>
                            <button id="support-dialog-close" type="button" className="simpeg-support-dialog-close" onClick={() => setShowSupport(false)} aria-label="Tutup informasi bantuan">
                                <X className="h-5 w-5" strokeWidth={1.8} aria-hidden="true" />
                            </button>
                        </div>
                        <p className="simpeg-support-dialog-kicker">Bantuan akses</p>
                        <h2 id="support-dialog-title">Belum punya akun?</h2>
                        <p id="support-dialog-description">Aplikasi ini digunakan untuk kebutuhan internal. Untuk mendapatkan akun atau mengatur ulang password, silakan hubungi:</p>
                        <div className="simpeg-support-dialog-contacts">
                            <div>
                                <span>SDM</span>
                                <strong>Bagian Sumber Daya Manusia</strong>
                            </div>
                            <div>
                                <span>IT</span>
                                <strong>Tim Teknologi Informasi</strong>
                            </div>
                        </div>
                        <div className="simpeg-support-dialog-note">
                            <ShieldCheck className="h-4 w-4 shrink-0" strokeWidth={1.8} aria-hidden="true" />
                            <span>Siapkan ID pegawai saat menghubungi petugas agar proses bantuan lebih cepat.</span>
                        </div>
                        <button type="button" className="simpeg-support-dialog-action" onClick={() => setShowSupport(false)}>Mengerti</button>
                    </section>
                </div>
            )}
            {showResource && (
                <div className="simpeg-login-resource-overlay" onMouseDown={(event) => { if (event.target === event.currentTarget) setShowResource(false); }}>
                    <section className="simpeg-login-resource-dialog" role="dialog" aria-modal="true" aria-labelledby="resource-dialog-title" aria-describedby="resource-dialog-description">
                        <div className="simpeg-login-resource-dialog-header">
                            <div>
                                <p className="simpeg-login-resource-dialog-kicker">Tautan informasi</p>
                                <h2 id="resource-dialog-title">{slide.resource.title}</h2>
                            </div>
                            <button id="resource-dialog-close" type="button" className="simpeg-login-resource-dialog-close" onClick={() => setShowResource(false)} aria-label="Tutup informasi">
                                <X className="h-5 w-5" strokeWidth={1.8} aria-hidden="true" />
                            </button>
                        </div>
                        <p id="resource-dialog-description" className="simpeg-login-resource-dialog-description">{slide.resource.description}</p>
                        <div className="simpeg-login-resource-dialog-source">
                            <span>Sumber</span>
                            <strong>{slide.resource.source}</strong>
                        </div>
                        <div className="simpeg-login-resource-dialog-actions">
                            <button type="button" className="simpeg-login-resource-dialog-cancel" onClick={() => setShowResource(false)}>Tutup</button>
                            <a className="simpeg-login-resource-dialog-link" href={slide.resource.url} target="_blank" rel="noreferrer">
                                Buka tautan
                                <ExternalLink className="h-4 w-4" strokeWidth={1.8} aria-hidden="true" />
                            </a>
                        </div>
                    </section>
                </div>
            )}
        </>
    );
}
