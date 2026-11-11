import { Link } from '@inertiajs/react';
import { Activity, ArrowUpRight, FileBarChart2, UsersRound } from 'lucide-react';
import LegacyPageShell from '../../Components/LegacyPageShell';
import { Badge } from '../../Components/ui/badge';
import { buttonVariants } from '../../Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '../../Components/ui/card';
import { NumberTicker } from '../../Components/ui/number-ticker';

const statIcons = {
    employees: UsersRound,
    inactive: Activity,
    default: UsersRound,
};

function BranchStat({ stat }) {
    const Icon = statIcons[stat.key] || statIcons.default;

    return (
        <Card className="simpeg-dashboard-stat group h-full min-w-0 border p-0 transition-colors">
            <Link href={stat.href} className="block min-w-0 p-4 sm:p-5">
                <div className="flex items-start justify-between gap-4">
                    <p className="simpeg-muted min-w-0 break-words text-sm">{stat.label}</p>
                    <Icon className="h-5 w-5 shrink-0 simpeg-text" strokeWidth={1.7} aria-hidden="true" />
                </div>
                <p className="simpeg-dashboard-stat-value simpeg-text mt-5"><NumberTicker value={stat.value} /></p>
                <div className="mt-3 flex min-h-6 items-center">
                    <Badge variant="default">Data unit kerja</Badge>
                </div>
                <p className="simpeg-muted mt-4 break-words text-sm leading-5">Ringkasan wilayah aktif</p>
                <span className="simpeg-dashboard-stat-link simpeg-muted mt-5 inline-flex items-center gap-2 text-sm">
                    Buka ringkasan
                    <ArrowUpRight className="h-4 w-4 transition-transform group-hover:-translate-y-0.5 group-hover:translate-x-0.5" strokeWidth={1.8} aria-hidden="true" />
                </span>
            </Link>
        </Card>
    );
}

export default function BranchDashboard({ stats = [], tenure = [], year, unit, scopeAvailable }) {
    return (
        <LegacyPageShell title="Dashboard Cabang">
            <div className="simpeg-dashboard-shell space-y-6">
                <section className="border-b simpeg-border pb-7" aria-labelledby="branch-dashboard-title">
                    <p className="mb-3 text-sm font-medium text-[#8A6820]">Dashboard cabang</p>
                    <div className="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                        <div className="min-w-0">
                            <h1 id="branch-dashboard-title" className="simpeg-text text-3xl font-medium sm:text-4xl">Ruang kerja kepala cabang</h1>
                            <p className="simpeg-muted mt-3 max-w-2xl text-sm leading-6 sm:text-base">Ringkasan unit kerja {unit || 'belum ditentukan'} untuk tahun {year}.</p>
                        </div>
                        <Link href="/laporan/nominatif" className={buttonVariants({ variant: 'default', className: 'simpeg-dashboard-action' })}>
                            <FileBarChart2 className="h-4 w-4" strokeWidth={1.8} aria-hidden="true" />
                            Laporan nominatif
                        </Link>
                    </div>
                </section>

                {!scopeAvailable ? (
                    <div className="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm leading-6 text-amber-900" role="alert">
                        Unit kerja aktif tidak ditemukan pada data jabatan. Hubungi Admin untuk memperbarui data jabatan aktif.
                    </div>
                ) : (
                    <>
                        <section className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-12" aria-label="Ringkasan dashboard cabang">
                            {stats.map((stat) => <div key={stat.key} className="lg:col-span-3"><BranchStat stat={stat} /></div>)}
                        </section>

                        <Card aria-labelledby="branch-tenure-title">
                            <CardHeader>
                                <CardTitle as="h2" id="branch-tenure-title">Statistik Masa Kerja</CardTitle>
                                <CardDescription>Pegawai aktif berdasarkan selisih penuh tahun dari TMT kerja.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                                    {tenure.map((item) => (
                                        <div key={item.label} className="simpeg-card-soft rounded-2xl border p-4">
                                            <div className="flex items-start justify-between gap-3">
                                        <span className="simpeg-muted text-xs">{item.label}</span>
                                        <strong className="simpeg-text text-2xl font-medium"><NumberTicker value={item.value} /></strong>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                    </>
                )}

                <Link href="/dashboard" className={`${buttonVariants({ variant: 'outline' })} mt-6`}>
                    Kembali ke dashboard
                </Link>
            </div>
        </LegacyPageShell>
    );
}
