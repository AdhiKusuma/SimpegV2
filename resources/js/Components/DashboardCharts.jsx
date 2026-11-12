import { useEffect, useMemo, useState } from 'react';
import {
    Activity,
    BriefcaseBusiness,
    CalendarClock,
    ChevronLeft,
    ChevronRight,
    GraduationCap,
    Search,
    ShieldAlert,
    UsersRound,
} from 'lucide-react';
import { Badge } from '../Components/ui/badge';
import { Button } from '../Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '../Components/ui/card';
import { Bar, BarChart, CartesianGrid, Cell, XAxis } from 'recharts';
import { ChartContainer, ChartTooltip, ChartTooltipContent } from '../Components/ui/chart';
import { Input } from '../Components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '../Components/ui/table';

const chartColors = [
    'var(--simpeg-chart-1)',
    'var(--simpeg-chart-2)',
    'var(--simpeg-chart-3)',
    'var(--simpeg-chart-4)',
    'var(--simpeg-chart-5)',
];

function number(value) {
    return new Intl.NumberFormat('id-ID').format(Number(value) || 0);
}

function asList(value) {
    if (Array.isArray(value)) {
        return value;
    }

    if (value && typeof value === 'object') {
        return Object.values(value);
    }

    return [];
}

function hasValues(values = []) {
    return asList(values).some((value) => Number(value) > 0);
}

function ChartCard({ id, title, description, children, className = '', icon: Icon = Activity }) {
    return (
        <Card className={`simpeg-dashboard-card min-w-0 border shadow-sm ${className}`} aria-labelledby={id}>
            <CardHeader className="simpeg-dashboard-card-header flex-row items-start gap-3 border-b simpeg-border">
                <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                    <Icon className="h-5 w-5" strokeWidth={1.8} aria-hidden="true" />
                </span>
                <div className="min-w-0">
                    <CardTitle as="h2" id={id}>{title}</CardTitle>
                    {description && <CardDescription className="mt-1">{description}</CardDescription>}
                </div>
            </CardHeader>
            <CardContent className="simpeg-dashboard-card-content">{children}</CardContent>
        </Card>
    );
}

function EmptyData({ message = 'Belum ada data untuk ditampilkan.' }) {
    return <p className="simpeg-empty rounded-xl border border-dashed px-4 py-8 text-center text-sm" role="status">{message}</p>;
}

function DonutChart({ data }) {
    const total = Number(data?.total) || 0;
    const labels = asList(data?.labels);
    const values = asList(data?.values);
    const radius = 70;
    const circumference = 2 * Math.PI * radius;
    let offset = 0;

    const segments = values.map((value, index) => {
        const length = total > 0 ? (Number(value) / total) * circumference : 0;
        const segment = { length, offset, color: chartColors[index] || 'var(--simpeg-chart-3)' };
        offset += length;
        return segment;
    });

    if (total === 0) {
        return <EmptyData message="Belum ada data gender pegawai aktif." />;
    }

    return (
        <div className="grid items-center gap-6 sm:grid-cols-[minmax(0,220px)_minmax(0,1fr)]">
            <div className="mx-auto w-full max-w-[220px]">
                <svg viewBox="0 0 200 200" className="h-auto w-full" role="img" aria-label={`Komposisi gender, total ${number(total)} pegawai`}>
                    <circle cx="100" cy="100" r={radius} fill="none" stroke="var(--simpeg-text)" strokeOpacity="0.12" strokeWidth="26" />
                    {segments.map((segment, index) => (
                        <circle
                            key={`${labels[index] || index}-${segment.color}`}
                            cx="100"
                            cy="100"
                            r={radius}
                            fill="none"
                            stroke={segment.color}
                            strokeDasharray={`${segment.length} ${circumference}`}
                            strokeDashoffset={-segment.offset}
                            strokeLinecap="butt"
                            strokeWidth="26"
                            transform="rotate(-90 100 100)"
                        >
                            <title>{`${labels[index] || '-'}: ${number(values[index])} pegawai`}</title>
                        </circle>
                    ))}
                    <text x="100" y="96" textAnchor="middle" className="fill-[#131822] text-[22px] font-medium">{number(total)}</text>
                    <text x="100" y="116" textAnchor="middle" className="fill-[#131822]/50 text-[10px]">Total</text>
                </svg>
            </div>
            <div className="grid gap-2">
                {labels.map((label, index) => (
                    <div key={label} className="grid min-w-0 grid-cols-[minmax(0,1fr)_auto] items-center gap-3 rounded-xl border border-[#131822]/10 bg-[#F7F5F0]/70 px-3 py-2.5">
                        <span className="flex min-w-0 items-center gap-3 text-sm text-[#131822]">
                            <span className="h-3 w-3 shrink-0 rounded-sm" style={{ backgroundColor: chartColors[index] || 'var(--simpeg-chart-3)' }} aria-hidden="true" />
                            <span className="min-w-0 truncate" title={label}>{label}</span>
                        </span>
                        <span className="whitespace-nowrap text-right text-sm font-medium tabular-nums text-[#131822]">
                            {number(values[index])}
                            <span className="ml-1 text-xs font-normal text-[#131822]/60">pegawai</span>
                        </span>
                    </div>
                ))}
            </div>
        </div>
    );
}

function HorizontalBars({ labels = [], values = [], emptyMessage, colors = chartColors }) {
    const safeLabels = asList(labels);
    const safeValues = asList(values);

    if (!hasValues(safeValues)) {
        return <EmptyData message={emptyMessage} />;
    }

    const maximum = Math.max(...safeValues.map((value) => Number(value) || 0), 1);

    return (
        <div className="space-y-4">
            {safeLabels.map((label, index) => {
                const value = Number(safeValues[index]) || 0;
                const width = value > 0 ? Math.max((value / maximum) * 100, 2) : 0;

                return (
                    <div key={label} className="grid grid-cols-[minmax(5rem,8rem)_minmax(0,1fr)_auto] items-center gap-3 sm:grid-cols-[minmax(7rem,12rem)_minmax(0,1fr)_auto]">
                        <span className="break-words text-xs leading-5 text-[#131822]/60" title={label}>{label}</span>
                        <div className="simpeg-track h-3 rounded-full" aria-hidden="true">
                            <div className="h-3 rounded-full" style={{ width: `${width}%`, backgroundColor: colors[index] || colors[0] || chartColors[index] || 'var(--simpeg-chart-1)' }} />
                        </div>
                        <span className="min-w-8 text-right text-sm font-medium text-[#131822]">{number(value)}</span>
                    </div>
                );
            })}
        </div>
    );
}

function VerticalBars({ labels = [], values = [], emptyMessage }) {
    const safeLabels = asList(labels);
    const safeValues = asList(values);

    if (!hasValues(safeValues)) {
        return <EmptyData message={emptyMessage} />;
    }

    const data = safeLabels.map((label, index) => ({
        label,
        value: Number(safeValues[index]) || 0,
        fill: `var(--simpeg-status-bar-${(index % 4) + 1})`,
    }));
    const config = {
        value: {
            label: 'Jumlah pegawai',
            color: 'var(--simpeg-status-bar-4)',
        },
    };

    return (
        <div className="simpeg-status-chart px-0 py-1" role="img" aria-label="Status kepegawaian berdasarkan jumlah pegawai">
            <ChartContainer config={config} className="h-[19rem] w-full">
                <BarChart accessibilityLayer data={data} margin={{ top: 18, right: 8, left: 8, bottom: 8 }}>
                    <CartesianGrid vertical={false} stroke="var(--simpeg-border)" strokeDasharray="3 3" />
                    <XAxis dataKey="label" tickLine={false} axisLine={false} tickMargin={10} />
                    <ChartTooltip
                        cursor={{ fill: 'var(--simpeg-input)', opacity: 0.65 }}
                        content={(
                            <ChartTooltipContent
                                formatter={(value) => <span className="simpeg-text font-medium tabular-nums">{number(value)} pegawai</span>}
                            />
                        )}
                    />
                    <Bar dataKey="value" fill="var(--color-value)" radius={[12, 12, 0, 0]} maxBarSize={64}>
                        {data.map((entry) => <Cell key={entry.label} fill={entry.fill} />)}
                    </Bar>
                </BarChart>
            </ChartContainer>
        </div>
    );
}

function TrendChart({ data }) {
    const values = asList(data?.values);
    const labels = asList(data?.labels);

    if (!hasValues(values)) {
        return <EmptyData message={`Belum ada pelanggaran tercatat pada tahun ${data?.year || ''}.`} />;
    }

    const width = 720;
    const height = 260;
    const padding = { top: 18, right: 16, bottom: 36, left: 36 };
    const maximum = Math.max(...values.map((value) => Number(value) || 0), 1);
    const plotWidth = width - padding.left - padding.right;
    const plotHeight = height - padding.top - padding.bottom;
    const points = values.map((value, index) => {
        const x = padding.left + (labels.length <= 1 ? plotWidth / 2 : (index / (labels.length - 1)) * plotWidth);
        const y = padding.top + plotHeight - ((Number(value) || 0) / maximum) * plotHeight;
        return { x, y, value: Number(value) || 0 };
    });
    const path = points.map(({ x, y }, index) => `${index === 0 ? 'M' : 'L'} ${x} ${y}`).join(' ');

    return (
        <div>
            <svg viewBox={`0 0 ${width} ${height}`} className="h-auto w-full" role="img" aria-label={`Tren pelanggaran tahun ${data?.year || ''}`}>
                {[0, 0.5, 1].map((ratio) => {
                    const y = padding.top + plotHeight * ratio;
                    const gridValue = Math.round(maximum * (1 - ratio));
                    return (
                        <g key={ratio}>
                            <line x1={padding.left} x2={width - padding.right} y1={y} y2={y} stroke="var(--simpeg-text)" strokeOpacity="0.16" strokeDasharray="4 4" />
                            <text x={padding.left - 8} y={y + 4} textAnchor="end" className="fill-[#131822]/45 text-[10px]">{gridValue}</text>
                        </g>
                    );
                })}
                <path d={path} fill="none" stroke="var(--simpeg-chart-1)" strokeWidth="3" strokeLinejoin="round" strokeLinecap="round" />
                {points.map((point, index) => (
                    <g key={labels[index]}>
                        <circle cx={point.x} cy={point.y} r="5" fill="var(--simpeg-surface)" stroke="var(--simpeg-chart-1)" strokeWidth="3">
                            <title>{`${labels[index]}: ${number(point.value)} kasus`}</title>
                        </circle>
                        <text x={point.x} y={height - 12} textAnchor="middle" className="fill-[#131822]/45 text-[10px]">{labels[index]}</text>
                    </g>
                ))}
            </svg>
            <p className="mt-2 text-center text-xs text-[#131822]/45">Data pelanggaran per bulan, {data?.year}</p>
        </div>
    );
}

function SearchableTable({ rows, columns, searchPlaceholder, emptyMessage, inputId }) {
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);
    const pageSize = 5;
    const normalizedRows = useMemo(() => asList(rows), [rows]);

    const filteredRows = useMemo(() => {
        const query = search.trim().toLowerCase();
        if (!query) {
            return normalizedRows;
        }

        return normalizedRows.filter((row) => columns.some((column) => String(row[column.key] ?? '').toLowerCase().includes(query)));
    }, [columns, normalizedRows, search]);

    const pageCount = Math.max(Math.ceil(filteredRows.length / pageSize), 1);
    const currentPage = Math.min(page, pageCount);
    const visibleRows = filteredRows.slice((currentPage - 1) * pageSize, currentPage * pageSize);

    useEffect(() => {
        setPage(1);
    }, [search]);

    if (normalizedRows.length === 0) {
        return <EmptyData message={emptyMessage} />;
    }

    return (
        <div>
            <label className="sr-only" htmlFor={inputId}>{searchPlaceholder}</label>
            <div className="relative mb-4 max-w-md">
                <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#131822]/40" strokeWidth={1.8} aria-hidden="true" />
                <Input id={inputId} type="search" value={search} onChange={(event) => setSearch(event.target.value)} placeholder={searchPlaceholder} className="pl-10" />
            </div>
            {visibleRows.length === 0 ? (
                <EmptyData message="Tidak ada data yang cocok dengan pencarian." />
            ) : (
                <div>
                    <div className="hidden rounded-xl border border-[#131822]/10 sm:block">
                        <Table className="simpeg-card-soft min-w-[42rem]">
                            <TableHeader>
                                <TableRow className="hover:bg-transparent">{columns.map((column) => <TableHead key={column.key} scope="col" className={column.align === 'right' ? 'text-right' : column.align === 'center' ? 'text-center' : ''}>{column.label}</TableHead>)}</TableRow>
                            </TableHeader>
                            <TableBody>
                                {visibleRows.map((row, index) => (
                                    <TableRow key={row.id_peg || row.kode_jabatan || `${row.nama}-${index}`}>
                                        {columns.map((column) => <TableCell key={column.key} className={column.align === 'right' ? 'text-right' : column.align === 'center' ? 'text-center' : ''}>{column.render ? column.render(row) : row[column.key]}</TableCell>)}
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>

                    <div className="space-y-3 sm:hidden">
                        {visibleRows.map((row, index) => (
                            <article key={row.id_peg || row.kode_jabatan || `${row.nama}-${index}`} className="simpeg-card-soft rounded-2xl border p-4 shadow-sm">
                                {columns.map((column, columnIndex) => (
                                    <div key={column.key} className={columnIndex === 0 ? 'border-b simpeg-border pb-3' : 'flex items-start justify-between gap-4 border-b simpeg-border py-3 last:border-0 last:pb-0'}>
                                        <span className="simpeg-muted shrink-0 text-xs">{column.label}</span>
                                        <div className={columnIndex === 0 ? 'simpeg-text mt-1 min-w-0 text-sm' : 'simpeg-text min-w-0 text-right text-sm'}>
                                            {column.render ? column.render(row) : row[column.key]}
                                        </div>
                                    </div>
                                ))}
                            </article>
                        ))}
                    </div>
                </div>
            )}
            <div className="mt-4 flex flex-col gap-3 text-sm text-[#131822]/55 sm:flex-row sm:items-center sm:justify-between">
                <span>Menampilkan {filteredRows.length === 0 ? 0 : (currentPage - 1) * pageSize + 1} sampai {Math.min(currentPage * pageSize, filteredRows.length)} dari {filteredRows.length} data</span>
                <div className="flex items-center gap-2">
                    <Button type="button" variant="outline" size="sm" onClick={() => setPage((value) => Math.max(value - 1, 1))} disabled={currentPage === 1}>
                        <ChevronLeft className="h-4 w-4" strokeWidth={1.8} aria-hidden="true" />
                        <span className="hidden sm:inline">Sebelumnya</span>
                        <span className="sm:hidden">Prev</span>
                    </Button>
                    <span aria-live="polite" className="whitespace-nowrap">{currentPage} / {pageCount}</span>
                    <Button type="button" variant="dark" size="sm" onClick={() => setPage((value) => Math.min(value + 1, pageCount))} disabled={currentPage === pageCount}>
                        <span className="hidden sm:inline">Berikutnya</span>
                        <span className="sm:hidden">Next</span>
                        <ChevronRight className="h-4 w-4" strokeWidth={1.8} aria-hidden="true" />
                    </Button>
                </div>
            </div>
        </div>
    );
}

function PositionAvailabilityTable({ title, description, rows, searchId }) {
    return (
        <ChartCard id={searchId} title={title} description={description} icon={BriefcaseBusiness}>
            <SearchableTable
                rows={rows}
                searchPlaceholder="Cari jabatan"
                inputId={`${searchId}-search`}
                emptyMessage="Belum ada referensi jabatan pada kelompok ini."
                columns={[
                    { key: 'nama_jabatan', label: 'Deskripsi Jabatan' },
                    { key: 'terisi', label: 'Terisi', align: 'center', render: (row) => <span className="font-medium text-[#131822]">{number(row.terisi)}</span> },
                    { key: 'kuota', label: 'Kuota', align: 'center', render: (row) => <span className="font-medium text-[#131822]/60">{number(row.kuota)}</span> },
                    { key: 'sisa', label: 'Sisa', align: 'center', render: (row) => <span className="font-medium text-[#131822]/60">{number(row.sisa)}</span> },
                    { key: 'status', label: 'Status', align: 'center', render: (row) => <Badge variant={row.status === 'Tersedia' ? 'success' : row.status === 'Kosong' ? 'danger' : 'warning'}>{row.status}</Badge> },
                ]}
            />
        </ChartCard>
    );
}

export function DashboardCharts({ charts = {}, retirement = [], executivePositions = [], structuralPositions = [], year }) {
    const positions = asList(charts.positions);

    return (
        <div className="space-y-6">
            <section className="grid min-w-0 gap-6 lg:grid-cols-12" aria-label="Komposisi pegawai">
                <ChartCard id="gender-chart-title" className="lg:col-span-5" title="Komposisi Gender" description="Pegawai aktif berdasarkan jenis kelamin yang tersimpan di data pegawai." icon={UsersRound}>
                    <DonutChart data={charts.gender || { labels: [], values: [], total: 0 }} />
                </ChartCard>
                <ChartCard id="education-chart-title" className="lg:col-span-7" title="Jenjang Pendidikan" description="Pendidikan tertinggi setiap pegawai aktif, berdasarkan riwayat pendidikan sistem." icon={GraduationCap}>
                    <HorizontalBars {...(charts.education || {})} emptyMessage="Belum ada data jenjang pendidikan pegawai aktif." />
                </ChartCard>
            </section>

            <section className="grid min-w-0 gap-6 lg:grid-cols-12" aria-label="Status dan tren kepegawaian">
                <ChartCard id="employment-chart-title" className="lg:col-span-6" title="Status Kepegawaian" description="Distribusi status kepegawaian pegawai aktif." icon={UsersRound}>
                    <VerticalBars {...(charts.employment || {})} emptyMessage="Belum ada data status kepegawaian pegawai aktif." />
                </ChartCard>
                <ChartCard id="violation-chart-title" className="lg:col-span-6" title="Tren Pelanggaran" description={`Jumlah pelanggaran per bulan pada tahun ${year}.`} icon={ShieldAlert}>
                    <TrendChart data={charts.violations || { labels: [], values: [], year }} />
                </ChartCard>
            </section>

            <ChartCard id="position-chart-title" title="Statistik Kepegawaian" description="Jumlah pegawai aktif berdasarkan jabatan aktif saat ini." className="lg:col-span-12" icon={BriefcaseBusiness}>
                {positions.length ? (
                    <div className="max-h-[30rem] overflow-y-auto pr-1">
                        <HorizontalBars labels={positions.map((item) => item.label)} values={positions.map((item) => item.value)} emptyMessage="Belum ada statistik jabatan pegawai aktif." colors={['var(--simpeg-chart-1)']} />
                    </div>
                ) : <EmptyData message="Belum ada statistik jabatan pegawai aktif." />}
            </ChartCard>

            <ChartCard id="retirement-title" title={`Daftar Pensiun ${year}`} description="Monitoring pegawai yang memasuki masa pensiun pada tahun berjalan." className="lg:col-span-12" icon={CalendarClock}>
                <SearchableTable
                    rows={retirement}
                    searchPlaceholder="Cari pegawai"
                    inputId="retirement-search"
                    emptyMessage={`Belum ada pegawai yang memasuki masa pensiun pada tahun ${year}.`}
                    columns={[
                        { key: 'nama', label: 'Pegawai', render: (row) => <div><strong className="block font-medium text-[#131822]">{row.nama || '-'}</strong><span className="text-xs text-[#131822]/45">ID: {row.id_peg}</span></div> },
                        { key: 'jabatan', label: 'Jabatan' },
                        { key: 'status', label: 'Status', render: (row) => <Badge variant={row.status === 'Segera Pensiun' ? 'warning' : 'success'}>{row.status}</Badge> },
                        { key: 'tgl_pensiun', label: 'Tgl Pensiun' },
                        { key: 'hari_tersisa', label: 'Sisa hari', align: 'right', render: (row) => row.hari_tersisa > 0 ? <span className="font-medium text-[#9A473E]">{number(row.hari_tersisa)} hari lagi</span> : <span className="text-[#131822]/45">Selesai</span> },
                    ]}
                />
            </ChartCard>

            <section className="grid min-w-0 gap-6 lg:grid-cols-12" aria-label="Ketersediaan jabatan">
                <div className="lg:col-span-6"><PositionAvailabilityTable title="Jabatan Eksekutif" description="Ketersediaan posisi dan kuota jabatan kelompok PE." rows={executivePositions} searchId="executive-positions-title" /></div>
                <div className="lg:col-span-6"><PositionAvailabilityTable title="Jabatan Struktural" description="Ketersediaan posisi dan kuota jabatan kelompok PS." rows={structuralPositions} searchId="structural-positions-title" /></div>
            </section>
        </div>
    );
}
