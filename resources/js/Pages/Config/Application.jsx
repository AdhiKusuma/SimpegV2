import { Head, useForm, usePage } from '@inertiajs/react';
import {
    Check,
    ChevronRight,
    Image as ImageIcon,
    LockKeyhole,
    Monitor,
    Moon,
    Palette,
    RefreshCw,
    ShieldCheck,
    SlidersHorizontal,
    Sun,
    Type,
    Upload,
    UsersRound,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import LegacyPageShell from '../../Components/LegacyPageShell';
import {
    applyAppearancePreferences,
    applyTheme,
    DEFAULT_THEME,
} from '../../Components/ThemeToggle';

const themeModes = [
    { value: 'light', label: 'Terang', icon: Sun },
    { value: 'dark', label: 'Gelap', icon: Moon },
    { value: 'system', label: 'Ikuti Sistem', icon: Monitor },
];

const sidebarColorOptions = [
    {
        value: 'theme',
        label: 'Simpeg Blue',
        group: 'Klasik',
        description: 'Mengikuti warna tema aktif.',
        swatch: 'linear-gradient(135deg, #0D5FE0 0%, #2B7BF0 100%)',
        accent: '#0D5FE0',
        badge: 'Default',
    },
    {
        value: 'light',
        label: 'Cloud',
        group: 'Klasik',
        description: 'Sidebar terang dengan teks gelap.',
        swatch: 'linear-gradient(135deg, #EDF3FD 0%, #FFFFFF 100%)',
        accent: '#6C7FA3',
    },
    {
        value: 'navy',
        label: 'Deep Navy',
        group: 'Dark premium',
        description: 'Biru navy dengan identitas BKK.',
        swatch: 'linear-gradient(135deg, #0F2747 0%, #0A2A66 100%)',
        accent: '#0A2A66',
    },
    {
        value: 'ink',
        label: 'Ink',
        group: 'Dark premium',
        description: 'Abu gelap untuk tampilan fokus.',
        swatch: 'linear-gradient(135deg, #30343B 0%, #424242 100%)',
        accent: '#424242',
    },
];

const fontSizeOptions = [
    { value: 'compact', label: 'Kecil', pixels: 14, description: 'Teks lebih ringkas.' },
    { value: 'normal', label: 'Normal', pixels: 16, description: 'Ukuran standar SIMPEG.' },
    { value: 'large', label: 'Besar', pixels: 17, description: 'Teks lebih mudah dibaca.' },
];

const densityOptions = [
    { value: 'compact', label: 'Ringkas' },
    { value: 'comfortable', label: 'Nyaman' },
    { value: 'spacious', label: 'Longgar' },
];

function selectedThemeOption(value) {
    return sidebarColorOptions.find((option) => option.value === value) || sidebarColorOptions[0];
}

export default function ApplicationConfig({ config }) {
    const { flash } = usePage().props;
    const [lightPreview, setLightPreview] = useState(config.logo_url);
    const [darkPreview, setDarkPreview] = useState(config.logo_dark_url);
    const [themeMode, setThemeMode] = useState(config.default_theme || DEFAULT_THEME);
    const [advanced, setAdvanced] = useState(() => ({
        allowTheme: config.allow_user_theme ?? true,
        reduceMotion: Boolean(config.reduce_motion),
        applyLoginTheme: config.apply_login_theme ?? true,
    }));
    const previewUrls = useRef(new Set());
    const lightLogoInput = useRef(null);
    const darkLogoInput = useRef(null);
    const form = useForm({
        nama_app: config.nama_app || '',
        desc_app: config.desc_app || '',
        alias_app: config.alias_app || '',
        url_app: config.url_app || '',
        anchor_app: config.anchor_app || '',
        logo: null,
        logo_dark: null,
        sidebar_color: config.sidebar_color || 'theme',
        font_size: config.font_size || 'normal',
        density: config.density || 'comfortable',
        default_theme: config.default_theme || DEFAULT_THEME,
        allow_user_theme: config.allow_user_theme ?? true,
        reduce_motion: Boolean(config.reduce_motion),
        apply_login_theme: config.apply_login_theme ?? true,
    });

    useEffect(() => {
        if (typeof document === 'undefined') {
            return;
        }

        if (form.data.font_size === 'normal') {
            delete document.documentElement.dataset.simpegFontSize;
        } else {
            document.documentElement.dataset.simpegFontSize = form.data.font_size;
        }
    }, [form.data.font_size]);

    useEffect(() => {
        if (typeof document === 'undefined') {
            return;
        }

        document.documentElement.dataset.simpegDensity = form.data.density || 'comfortable';
    }, [form.data.density]);

    useEffect(() => () => {
        previewUrls.current.forEach((preview) => URL.revokeObjectURL(preview));
        previewUrls.current.clear();
    }, []);

    function chooseLogo(field, setPreview, event) {
        const file = event.target.files?.[0];
        if (!file) {
            return;
        }

        const nextPreview = URL.createObjectURL(file);
        previewUrls.current.add(nextPreview);
        setPreview((currentPreview) => {
            if (currentPreview?.startsWith('blob:')) {
                URL.revokeObjectURL(currentPreview);
                previewUrls.current.delete(currentPreview);
            }

            return nextPreview;
        });
        form.setData(field, file);
    }

    function submit(event) {
        event.preventDefault();
        form.post(`/config/aplikasi?id=${encodeURIComponent(config.id_app)}`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: (page) => {
                const savedConfig = page?.props?.config;

                if (savedConfig?.logo_url) {
                    setLightPreview((currentPreview) => {
                        if (currentPreview?.startsWith('blob:')) {
                            URL.revokeObjectURL(currentPreview);
                            previewUrls.current.delete(currentPreview);
                        }

                        return savedConfig.logo_url;
                    });
                }
                if (savedConfig?.logo_dark_url) {
                    setDarkPreview((currentPreview) => {
                        if (currentPreview?.startsWith('blob:')) {
                            URL.revokeObjectURL(currentPreview);
                            previewUrls.current.delete(currentPreview);
                        }

                        return savedConfig.logo_dark_url;
                    });
                }

                form.setData('logo', null);
                form.setData('logo_dark', null);
                if (lightLogoInput.current) {
                    lightLogoInput.current.value = '';
                }
                if (darkLogoInput.current) {
                    darkLogoInput.current.value = '';
                }

                window.location.reload();
            },
        });
    }

    function resetChanges() {
        form.reset();
        setLightPreview(config.logo_url);
        setDarkPreview(config.logo_dark_url);
        if (lightLogoInput.current) {
            lightLogoInput.current.value = '';
        }
        if (darkLogoInput.current) {
            darkLogoInput.current.value = '';
        }
        setThemeMode(config.default_theme || DEFAULT_THEME);
        setAdvanced({
            allowTheme: config.allow_user_theme ?? true,
            reduceMotion: Boolean(config.reduce_motion),
            applyLoginTheme: config.apply_login_theme ?? true,
        });
        applyTheme(config.default_theme === 'dark' ? 'dark' : config.default_theme === 'system' && typeof window !== 'undefined' && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : DEFAULT_THEME);
        applyAppearancePreferences({ reduceMotion: Boolean(config.reduce_motion) });
    }

    function selectThemeMode(nextMode) {
        setThemeMode(nextMode);
        form.setData('default_theme', nextMode);

        const resolvedTheme = nextMode === 'system' && typeof window !== 'undefined'
            ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : DEFAULT_THEME)
            : nextMode;

        applyTheme(resolvedTheme);
    }

    function toggleAdvanced(name) {
        const nextValue = !advanced[name];
        const fieldName = {
            allowTheme: 'allow_user_theme',
            reduceMotion: 'reduce_motion',
            applyLoginTheme: 'apply_login_theme',
        }[name];

        setAdvanced((current) => ({ ...current, [name]: nextValue }));
        form.setData(fieldName, nextValue);

        if (name === 'reduceMotion') {
            applyAppearancePreferences({ reduceMotion: nextValue });
        }
    }

    function field(name, label, type = 'text', extraClass = '') {
        return (
            <div className={extraClass}>
                <label htmlFor={`config-${name}`} className="simpeg-settings-label">{label}</label>
                <input
                    id={`config-${name}`}
                    name={name}
                    type={type}
                    value={form.data[name]}
                    onChange={(event) => form.setData(name, event.target.value)}
                    className="simpeg-input simpeg-settings-input"
                />
                {form.errors[name] && <p className="simpeg-settings-error" role="alert">{form.errors[name]}</p>}
            </div>
        );
    }

    const selectedTheme = selectedThemeOption(form.data.sidebar_color);
    const selectedFont = fontSizeOptions.find((option) => option.value === form.data.font_size) || fontSizeOptions[1];
    const previewLogo = themeMode === 'dark' ? (darkPreview || lightPreview) : lightPreview;
    const previewModeLabel = themeMode === 'system' ? 'Ikuti Sistem' : themeMode === 'dark' ? 'Gelap' : 'Terang';
    const groupedThemes = sidebarColorOptions.reduce((groups, option) => {
        groups[option.group] = [...(groups[option.group] || []), option];
        return groups;
    }, {});

    return (
        <LegacyPageShell title="Pengaturan Tampilan">
            <Head title="Pengaturan Tampilan" />
            <div className="simpeg-settings-page">
                <form onSubmit={submit} encType="multipart/form-data" className="simpeg-settings-form">
                    <header className="simpeg-settings-header">
                        <div className="simpeg-settings-heading">
                            <p className="simpeg-settings-breadcrumb">Pengaturan <ChevronRight aria-hidden="true" /> <span>Tampilan &amp; Tema</span></p>
                            <h1>Pengaturan Tampilan</h1>
                            <p>Atur tema, ukuran font, dan logo aplikasi. Perubahan terlihat langsung di pratinjau sebelum disimpan.</p>
                        </div>
                        <div className="simpeg-settings-header-actions">
                            <button type="button" className="simpeg-settings-reset" onClick={resetChanges}>
                                <RefreshCw aria-hidden="true" />
                                Reset
                            </button>
                            <button type="submit" className="simpeg-settings-save" disabled={form.processing}>
                                <Check aria-hidden="true" />
                                {form.processing ? 'Menyimpan...' : 'Simpan Perubahan'}
                            </button>
                        </div>
                    </header>

                    {flash?.success && <div className="simpeg-settings-flash" role="status">{flash.success}</div>}

                    <div className="simpeg-settings-layout">
                        <main className="simpeg-settings-main">
                            <section className="simpeg-settings-card simpeg-settings-theme-card" aria-labelledby="theme-heading">
                                <div className="simpeg-settings-card-heading">
                                    <div>
                                        <div className="simpeg-settings-title-row">
                                            <Palette aria-hidden="true" />
                                            <h2 id="theme-heading">Tema Default Aplikasi</h2>
                                            <span className="simpeg-settings-badge simpeg-settings-badge-amber">Global</span>
                                        </div>
                                        <p>Tema default dipakai setiap kali seluruh user mengakses aplikasi.</p>
                                    </div>
                                </div>
                                <div className="simpeg-settings-status"><Check aria-hidden="true" /> Sudah jadi tema default</div>

                                <div className="simpeg-settings-card-section">
                                    <div className="simpeg-settings-section-heading">
                                        <div>
                                            <h3>Mode Tampilan</h3>
                                            <p>Tema gelap premium tetap tampil gelap, apa pun modenya.</p>
                                        </div>
                                    </div>
                                    <div className="simpeg-settings-segmented" role="group" aria-label="Mode tampilan">
                                        {themeModes.map((mode) => {
                                            const Icon = mode.icon;
                                            const active = themeMode === mode.value || (mode.value === 'light' && themeMode !== 'dark' && themeMode !== 'system');

                                            return (
                                                <button key={mode.value} type="button" className={active ? 'is-active' : ''} onClick={() => selectThemeMode(mode.value)}>
                                                    <Icon aria-hidden="true" />
                                                    <span>{mode.label}</span>
                                                </button>
                                            );
                                        })}
                                    </div>
                                </div>

                                <div className="simpeg-settings-card-section simpeg-settings-theme-options">
                                    {Object.entries(groupedThemes).map(([group, options]) => (
                                        <div key={group} className="simpeg-settings-theme-group">
                                            <div className="simpeg-settings-theme-group-heading">
                                                <span>{group}</span>
                                                <small>{group === 'Klasik' ? 'Pilihan sidebar yang tersedia' : 'Navy dan tone hangat, tidak terlalu pekat'}</small>
                                            </div>
                                            <div className="simpeg-settings-theme-grid">
                                                {options.map((option) => {
                                                    const active = form.data.sidebar_color === option.value;

                                                    return (
                                                        <button
                                                            key={option.value}
                                                            type="button"
                                                            className={`simpeg-settings-theme-option ${active ? 'is-active' : ''}`}
                                                            onClick={() => form.setData('sidebar_color', option.value)}
                                                            aria-pressed={active}
                                                        >
                                                            <span className="simpeg-settings-theme-swatch" style={{ background: option.swatch }}>
                                                                {active && <span className="simpeg-settings-theme-check"><Check aria-hidden="true" /></span>}
                                                            </span>
                                                            <strong>{option.label}</strong>
                                                            {option.badge && <small>{option.badge}</small>}
                                                        </button>
                                                    );
                                                })}
                                            </div>
                                        </div>
                                    ))}
                                    {form.errors.sidebar_color && <p className="simpeg-settings-error" role="alert">{form.errors.sidebar_color}</p>}
                                </div>
                            </section>

                            <section className="simpeg-settings-card" aria-labelledby="logo-heading">
                                <div className="simpeg-settings-card-heading">
                                    <div>
                                        <div className="simpeg-settings-title-row">
                                            <ImageIcon aria-hidden="true" />
                                            <h2 id="logo-heading">Logo Aplikasi</h2>
                                        </div>
                                        <p>Aplikasi otomatis memakai versi logo sesuai mode tampilan.</p>
                                    </div>
                                </div>
                                <div className="simpeg-settings-logo-grid">
                                    <div className="simpeg-settings-logo-field">
                                        <div className="simpeg-settings-field-heading">
                                            <strong><Sun aria-hidden="true" /> Logo Mode Terang</strong>
                                            <span>Aktif</span>
                                        </div>
                                        <div className="simpeg-settings-logo-preview is-light">
                                            {lightPreview ? <img src={lightPreview} alt="Pratinjau logo mode terang" /> : <span>Logo terang</span>}
                                        </div>
                                        <label htmlFor="config-logo" className="simpeg-settings-upload-button">
                                            <Upload aria-hidden="true" /> Ganti Logo
                                            <input ref={lightLogoInput} id="config-logo" name="logo" type="file" accept=".jpg,.jpeg,.png,.gif,image/*" onChange={(event) => chooseLogo('logo', setLightPreview, event)} />
                                        </label>
                                        {form.errors.logo && <p className="simpeg-settings-error" role="alert">{form.errors.logo}</p>}
                                    </div>
                                    <div className="simpeg-settings-logo-field">
                                        <div className="simpeg-settings-field-heading">
                                            <strong><Moon aria-hidden="true" /> Logo Mode Gelap</strong>
                                            <span>Aktif</span>
                                        </div>
                                        <div className="simpeg-settings-logo-preview is-dark">
                                            {darkPreview ? <img src={darkPreview} alt="Pratinjau logo mode gelap" /> : <span>Logo gelap</span>}
                                        </div>
                                        <label htmlFor="config-logo-dark" className="simpeg-settings-upload-button">
                                            <Upload aria-hidden="true" /> Ganti Logo
                                            <input ref={darkLogoInput} id="config-logo-dark" name="logo_dark" type="file" accept=".jpg,.jpeg,.png,.gif,image/*" onChange={(event) => chooseLogo('logo_dark', setDarkPreview, event)} />
                                        </label>
                                        {form.errors.logo_dark && <p className="simpeg-settings-error" role="alert">{form.errors.logo_dark}</p>}
                                    </div>
                                </div>
                                <p className="simpeg-settings-helper">Format JPG, PNG, atau GIF. Maksimal 2 MB per logo. Gunakan PNG transparan agar hasil tetap tajam di layar beresolusi tinggi.</p>
                            </section>

                            <section className="simpeg-settings-card" aria-labelledby="identity-heading">
                                <div className="simpeg-settings-card-heading">
                                    <div>
                                        <div className="simpeg-settings-title-row">
                                            <ShieldCheck aria-hidden="true" />
                                            <h2 id="identity-heading">Identitas Aplikasi</h2>
                                        </div>
                                        <p>Informasi ini dipakai pada judul, header, dan halaman masuk SIMPEG.</p>
                                    </div>
                                </div>
                                <div className="simpeg-settings-fields-grid">
                                    {field('nama_app', 'Nama aplikasi')}
                                    {field('alias_app', 'Singkatan / alias')}
                                    {field('desc_app', 'Deskripsi singkat', 'text', 'simpeg-settings-field-wide')}
                                    {field('url_app', 'URL website', 'url')}
                                    {field('anchor_app', 'Teks footer')}
                                </div>
                            </section>
                        </main>

                        <aside className="simpeg-settings-aside">
                            <section className="simpeg-settings-card simpeg-settings-preview-card" aria-labelledby="preview-heading">
                                <div className="simpeg-settings-card-heading simpeg-settings-card-heading-compact">
                                    <div>
                                        <div className="simpeg-settings-title-row">
                                            <Monitor aria-hidden="true" />
                                            <h2 id="preview-heading">Pratinjau Langsung</h2>
                                        </div>
                                    </div>
                                    <span className="simpeg-settings-badge">Data contoh</span>
                                </div>
                                <div className={`simpeg-settings-live-preview ${themeMode === 'dark' ? 'is-dark' : ''}`} style={{ '--preview-accent': selectedTheme.accent }}>
                                    <div className="simpeg-settings-live-preview-topline">
                                        {previewLogo ? <img src={previewLogo} alt="Logo pratinjau" /> : <span className="simpeg-settings-preview-placeholder">AD</span>}
                                        <span className="simpeg-settings-preview-avatar">AD</span>
                                    </div>
                                    <h3>Daftar Pegawai</h3>
                                    <p>Kelola data pegawai dan status kepegawaian.</p>
                                    <div className="simpeg-settings-preview-list">
                                        {[
                                            ['SR', 'Siti Rahma', 'Staf Administrasi', 'Aktif'],
                                            ['BP', 'Budi Prasetyo', 'Kepala Bagian', 'Aktif'],
                                            ['DW', 'Dewi Wulandari', 'Staf Keuangan', 'Cuti'],
                                        ].map(([initial, name, role, status]) => (
                                            <div key={name} className="simpeg-settings-preview-row">
                                                <span>{initial}</span>
                                                <div><strong>{name}</strong><small>{role}</small></div>
                                                <b className={status === 'Cuti' ? 'is-muted' : ''}>{status}</b>
                                            </div>
                                        ))}
                                    </div>
                                    <div className="simpeg-settings-preview-actions">
                                        <button type="button">Tambah Pegawai</button>
                                        <button type="button">Ekspor</button>
                                    </div>
                                </div>
                                <p className="simpeg-settings-preview-meta">Tema <strong>{selectedTheme.label}</strong> · Mode <strong>{previewModeLabel}</strong> · Font <strong>{selectedFont.pixels}px</strong></p>
                            </section>

                            <section className="simpeg-settings-card" aria-labelledby="font-heading">
                                <div className="simpeg-settings-card-heading simpeg-settings-card-heading-compact">
                                    <div>
                                        <div className="simpeg-settings-title-row">
                                            <Type aria-hidden="true" />
                                            <h2 id="font-heading">Ukuran Font</h2>
                                        </div>
                                        <p>Geser untuk mengubah ukuran teks seluruh aplikasi.</p>
                                    </div>
                                </div>
                                <div className="simpeg-settings-font-value"><strong>{selectedFont.pixels}</strong><span>px</span><em>100% · {selectedFont.label}</em></div>
                                <div className="simpeg-settings-range-labels"><span>A</span><input type="range" min="0" max="2" step="1" value={fontSizeOptions.findIndex((option) => option.value === form.data.font_size)} onChange={(event) => form.setData('font_size', fontSizeOptions[Number(event.target.value)].value)} aria-label="Ukuran font" /><span>A</span></div>
                                <div className="simpeg-settings-range-ticks"><span>14</span><span>16</span><span>17</span></div>
                                <div className="simpeg-settings-font-buttons">
                                    {fontSizeOptions.map((option) => <button key={option.value} type="button" className={form.data.font_size === option.value ? 'is-active' : ''} onClick={() => form.setData('font_size', option.value)}>{option.label}</button>)}
                                </div>
                                <div className="simpeg-settings-sample"><span>CONTOH TEKS</span><p>Sistem Kepegawaian membantu mengelola data pegawai, absensi, dan cuti dalam satu tempat.</p></div>
                            </section>

                            <section className="simpeg-settings-card" aria-labelledby="advanced-heading">
                                <div className="simpeg-settings-card-heading simpeg-settings-card-heading-compact">
                                    <div>
                                        <div className="simpeg-settings-title-row">
                                            <SlidersHorizontal aria-hidden="true" />
                                            <h2 id="advanced-heading">Penyesuaian Lanjutan</h2>
                                        </div>
                                        <p>Kerapatan, sudut, dan preferensi akses.</p>
                                    </div>
                                </div>
                                <div className="simpeg-settings-density">
                                    <strong>Kerapatan Tampilan</strong>
                                    <div role="group" aria-label="Kerapatan tampilan">
                                        {densityOptions.map((option) => (
                                            <button
                                                key={option.value}
                                                type="button"
                                                className={form.data.density === option.value ? 'is-active' : ''}
                                                onClick={() => form.setData('density', option.value)}
                                                aria-pressed={form.data.density === option.value}
                                            >
                                                {option.label}
                                            </button>
                                        ))}
                                    </div>
                                    {form.errors.density && <p className="simpeg-settings-error" role="alert">{form.errors.density}</p>}
                                </div>
                                <div className="simpeg-settings-toggle-list">
                                    {[
                                        ['allowTheme', 'Izinkan user memilih tema sendiri', 'Jika mati, semua user memakai tema default global.', UsersRound],
                                        ['reduceMotion', 'Kurangi animasi', 'Matikan transisi dan efek gerak di seluruh aplikasi.', SlidersHorizontal],
                                        ['applyLoginTheme', 'Terapkan ke halaman login', 'Tema dan logo juga dipakai sebelum user masuk.', LockKeyhole],
                                    ].map(([name, label, description, Icon]) => (
                                        <div key={name} className="simpeg-settings-toggle-row">
                                            <Icon aria-hidden="true" />
                                            <div><strong>{label}</strong><p>{description}</p></div>
                                            <button type="button" className={`simpeg-settings-switch ${advanced[name] ? 'is-on' : ''}`} onClick={() => toggleAdvanced(name)} aria-pressed={advanced[name]} aria-label={label}><span /></button>
                                        </div>
                                    ))}
                                </div>
                            </section>
                        </aside>
                    </div>
                </form>
            </div>
        </LegacyPageShell>
    );
}
