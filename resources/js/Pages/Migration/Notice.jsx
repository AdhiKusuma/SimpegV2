import { Link } from '@inertiajs/react';
import LegacyPageShell from '../../Components/LegacyPageShell';

export default function MigrationNotice({ module, legacyPath }) {
    return (
        <LegacyPageShell title={module}>
            <div className="mx-auto max-w-2xl rounded-2xl border border-amber-200 bg-amber-50 p-8 text-center"><p className="text-sm font-semibold uppercase tracking-[0.18em] text-amber-700">Fitur bertahap</p><h1 className="mt-3 text-2xl font-extrabold text-slate-900">{module} sedang disiapkan</h1><p className="mt-3 text-sm leading-6 text-slate-700">Modul ini sedang disempurnakan agar seluruh proses berjalan dalam satu sistem. Tidak ada data yang diubah oleh halaman ini.</p><p className="mt-4 font-mono text-xs text-slate-500">Rute referensi: {legacyPath}</p><Link href="/dashboard" className="mt-6 inline-flex rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-600/20">Kembali ke dashboard</Link></div>
        </LegacyPageShell>
    );
}
