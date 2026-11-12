import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import LegacyPageShell from '../../Components/LegacyPageShell';

export default function ApplicationConfig({ config }) {
    const { flash } = usePage().props;
    const [lightPreview, setLightPreview] = useState(config.logo_url);
    const [darkPreview, setDarkPreview] = useState(config.logo_dark_url);
    const previewUrls = useRef(new Set());
    const form = useForm({
        nama_app: config.nama_app || '',
        desc_app: config.desc_app || '',
        alias_app: config.alias_app || '',
        url_app: config.url_app || '',
        anchor_app: config.anchor_app || '',
        logo: null,
        logo_dark: null,
    });

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
        form.post(`/config/aplikasi?id=${encodeURIComponent(config.id_app)}`, { forceFormData: true, preserveScroll: true });
    }

    function field(name, label, type = 'text', extraClass = '') {
        return <div className={extraClass}>
            <label htmlFor={`config-${name}`} className="simpeg-config-label">{label}</label>
            <input id={`config-${name}`} name={name} type={type} value={form.data[name]} onChange={(event) => form.setData(name, event.target.value)} className="simpeg-input h-11 w-full rounded-xl border px-3 text-sm outline-none transition focus:border-[var(--simpeg-primary)] focus:ring-4 focus:ring-[var(--simpeg-primary)]/20" />
            {form.errors[name] && <p className="mt-1 text-xs font-semibold text-red-600" role="alert">{form.errors[name]}</p>}
        </div>;
    }

    return (
        <LegacyPageShell title="Konfigurasi Aplikasi">
            <Head title="Konfigurasi Aplikasi" />
            <div className="simpeg-config-page">
                <div className="simpeg-config-intro">
                    <div>
                        <p className="simpeg-config-eyebrow">Pengaturan aplikasi</p>
                        <h1 className="simpeg-config-title">Identitas aplikasi</h1>
                        <p className="simpeg-config-description">Atur nama, keterangan, alamat, dan logo yang tampil di SIMPEG.</p>
                    </div>
                    <span className="simpeg-config-intro-mark">SIMPEG / ADMIN</span>
                </div>

                {flash?.success && <div className="simpeg-config-flash" role="status">{flash.success}</div>}

                <form onSubmit={submit} encType="multipart/form-data" className="simpeg-config-form">
                    <section className="simpeg-config-overview" aria-labelledby="logo-heading">
                        <div className="simpeg-config-preview simpeg-config-logo-previews">
                            <div className="simpeg-config-logo-choice">
                                <div className="simpeg-config-logo-frame">
                                    {lightPreview ? <img src={lightPreview} alt="Pratinjau logo mode terang" className="max-h-full max-w-full object-contain p-2" /> : <span className="text-xs">Logo terang</span>}
                                </div>
                                <div className="min-w-0">
                                    <p className="simpeg-config-preview-kicker">Light mode</p>
                                    <h2 className="simpeg-config-preview-title">{form.data.nama_app || 'Nama aplikasi'}</h2>
                                    <p className="simpeg-config-preview-alias">{form.data.alias_app || 'SIMPEG'}</p>
                                    <p className="simpeg-config-preview-description">Logo untuk tampilan terang.</p>
                                </div>
                            </div>
                            <div className="simpeg-config-logo-choice">
                                <div className="simpeg-config-logo-frame simpeg-config-logo-frame-dark">
                                    {darkPreview ? <img src={darkPreview} alt="Pratinjau logo mode gelap" className="max-h-full max-w-full object-contain p-2" /> : <span className="text-xs">Logo gelap</span>}
                                </div>
                                <div className="min-w-0">
                                    <p className="simpeg-config-preview-kicker">Dark mode</p>
                                    <h2 className="simpeg-config-preview-title">{form.data.nama_app || 'Nama aplikasi'}</h2>
                                    <p className="simpeg-config-preview-alias">{form.data.alias_app || 'SIMPEG'}</p>
                                    <p className="simpeg-config-preview-description">Logo untuk tampilan gelap.</p>
                                </div>
                            </div>
                        </div>

                        <div className="simpeg-config-upload">
                            <p id="logo-heading" className="simpeg-config-section-kicker">Logo aplikasi</p>
                            <p className="simpeg-config-upload-description">Atur logo yang dipakai pada navigasi dan halaman masuk untuk setiap tema.</p>
                            <div className="simpeg-config-upload-actions">
                                <label htmlFor="config-logo" className="simpeg-config-upload-button">Logo light mode
                                    <input id="config-logo" name="logo" type="file" accept=".jpg,.jpeg,.png,.gif,image/*" onChange={(event) => chooseLogo('logo', setLightPreview, event)} className="sr-only" />
                                </label>
                                <label htmlFor="config-logo-dark" className="simpeg-config-upload-button">Logo dark mode
                                    <input id="config-logo-dark" name="logo_dark" type="file" accept=".jpg,.jpeg,.png,.gif,image/*" onChange={(event) => chooseLogo('logo_dark', setDarkPreview, event)} className="sr-only" />
                                </label>
                            </div>
                            <p className="simpeg-config-upload-note">JPG, PNG, atau GIF. Maksimal 2 MB per logo.</p>
                            {form.errors.logo && <p className="mt-2 text-xs font-semibold text-red-600" role="alert">{form.errors.logo}</p>}
                            {form.errors.logo_dark && <p className="mt-2 text-xs font-semibold text-red-600" role="alert">{form.errors.logo_dark}</p>}
                        </div>
                    </section>

                    <section className="simpeg-config-details" aria-label="Detail konfigurasi">
                        <div className="simpeg-config-details-header">
                            <div>
                                <p className="simpeg-config-section-kicker">Identitas sistem</p>
                                <h2 className="simpeg-config-details-title">Detail konfigurasi</h2>
                            </div>
                            <p className="simpeg-config-details-note">Perubahan akan diterapkan setelah disimpan.</p>
                        </div>
                        <div className="grid gap-5 sm:grid-cols-2">
                            {field('nama_app', 'Nama aplikasi')}
                            {field('alias_app', 'Singkatan / alias')}
                            {field('desc_app', 'Deskripsi singkat', 'text', 'sm:col-span-2')}
                            {field('url_app', 'URL website', 'url')}
                            {field('anchor_app', 'Teks footer')}
                        </div>
                    </section>

                    <div className="simpeg-config-actions">
                        <Link href="/dashboard" className="simpeg-config-secondary-action">Batal</Link>
                        <button type="submit" disabled={form.processing} className="simpeg-primary simpeg-config-primary-action">{form.processing ? 'Menyimpan...' : 'Simpan perubahan'}</button>
                    </div>
                </form>
            </div>
        </LegacyPageShell>
    );
}
