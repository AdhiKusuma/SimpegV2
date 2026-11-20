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
    SlidersHorizontal,
    TrendingUp,
    UsersRound,
} from 'lucide-react';
import { Badge } from '../Components/ui/badge';
import { Button } from '../Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '../Components/ui/card';
import SelectCombobox from './SelectCombobox';
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

function ChartCard({ id, title, description, children, className = '', icon: Icon = Activity, meta = null, actions = null }) {
    return (
        <Card className={`simpeg-dashboard-card simpeg-dashboard-chart-card min-w-0 border shadow-sm ${className}`} aria-labelledby={id}>
            <CardHeader className="simpeg-dashboard-card-header flex-row items-start gap-3 border-b simpeg-border">
                <span className="simpeg-dashboard-chart-icon flex h-10 w-10 shrink-0 items-center justify-center rounded-xl">
                    <Icon className="h-5 w-5" strokeWidth={1.8} aria-hidden="true" />
                </span>
                <div className="min-w-0 flex-1">
                    <div className="flex min-w-0 items-start justify-between gap-3">
                        <CardTitle as="h2" id={id}>{title}</CardTitle>
                        {(actions || meta) && (
                            <div className="flex shrink-0 items-start gap-2">
                                {actions}
                                {meta && <span className="simpeg-dashboard-chart-meta">{meta}</span>}
                            </div>
                        )}
                    </div>
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
                    <text x="100" y="96" textAnchor="middle" fill="var(--simpeg-text)" className="text-[22px] font-medium">{number(total)}</text>
                    <text x="100" y="116" textAnchor="middle" fill="var(--simpeg-muted)" className="text-[10px]">Total</text>
                </svg>
            </div>
            <div className="grid gap-2">
                {labels.map((label, index) => (
                    <div key={label} className="simpeg-dashboard-chart-legend grid min-w-0 grid-cols-[minmax(0,1fr)_auto] items-center gap-3 rounded-xl border px-3 py-2.5">
                        <span className="simpeg-text flex min-w-0 items-center gap-3 text-sm">
                            <span className="h-3 w-3 shrink-0 rounded-sm" style={{ backgroundColor: chartColors[index] || 'var(--simpeg-chart-3)' }} aria-hidden="true" />
                            <span className="min-w-0 truncate" title={label}>{label}</span>
                        </span>
                        <span className="simpeg-text whitespace-nowrap text-right text-sm font-medium tabular-nums">
                            {number(values[index])}
                            <span className="simpeg-muted ml-1 text-xs font-normal">pegawai</span>
                        </span>
                    </div>
                ))}
            </div>
        </div>
    );
}

function HorizontalBars({ labels = [], values = [], emptyMessage, colors = chartColors, compact = false, className = '' }) {
    const safeLabels = asList(labels);
    const safeValues = asList(values);

    if (!hasValues(safeValues)) {
        return <EmptyData message={emptyMessage} />;
    }

    const maximum = Math.max(...safeValues.map((value) => Number(value) || 0), 1);

    return (
        <div className={`simpeg-dashboard-bars ${compact ? 'simpeg-dashboard-bars--compact' : ''} ${className}`.trim()}>
            {safeLabels.map((label, index) => {
                const value = Number(safeValues[index]) || 0;
                const width = value > 0 ? Math.max((value / maximum) * 100, 2) : 0;

                return (
                    <div key={label} className="simpeg-dashboard-bar-row grid grid-cols-[minmax(5rem,8rem)_minmax(0,1fr)_auto] items-center gap-3 sm:grid-cols-[minmax(7rem,12rem)_minmax(0,1fr)_auto]">
                        <span className="simpeg-dashboard-bar-label simpeg-muted break-words text-xs leading-5" title={label}>{label}</span>
                        <div className="simpeg-dashboard-bar-track simpeg-track h-3 rounded-full" aria-hidden="true">
                            <div className="simpeg-dashboard-bar-fill h-3 rounded-full" style={{ width: `${width}%`, backgroundColor: colors[index] || colors[0] || chartColors[index] || 'var(--simpeg-chart-1)' }} />
                        </div>
                        <span className="simpeg-dashboard-bar-value simpeg-text min-w-8 text-right text-sm font-medium">{number(value)}</span>
                    </div>
                );
            })}
        </div>
    );
}

function PositionBars({ positions = [] }) {
    const safePositions = asList(positions);
    const values = safePositions.map((item) => Number(item?.value) || 0);
    const maximum = Math.max(...values, 1);

    if (!hasValues(values)) {
        return <EmptyData message="Belum ada statistik jabatan pegawai aktif." />;
    }

    return (
        <div className="simpeg-dashboard-position-bars" role="list" aria-label="Statistik pegawai berdasarkan jabatan">
            {safePositions.map((item, index) => {
                const value = values[index];
                const width = value > 0 ? Math.max((value / maximum) * 100, 2) : 0;

                return (
                    <div key={`${item?.label || 'jabatan'}-${index}`} className="simpeg-dashboard-position-row" role="listitem">
                        <span className="simpeg-dashboard-position-label" title={item?.label || '-'}>{item?.label || '-'}</span>
                        <span className="simpeg-dashboard-position-track" aria-hidden="true">
                            <span className="simpeg-dashboard-position-fill" style={{ width: `${width}%` }} />
                        </span>
                        <strong className="simpeg-dashboard-position-value">{number(value)}</strong>
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
            <ChartContainer config={config} className="simpeg-status-chart-canvas h-[15rem] w-full">
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
                    <Bar dataKey="value" fill="var(--color-value)" radius={[12, 12, 0, 0]} maxBarSize={64} isAnimationActive={false}>
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
    const highestIndex = values.reduce((highest, value, index, list) => (
        Number(value) > Number(list[highest] ?? -Infinity) ? index : highest
    ), 0);
    const plotWidth = width - padding.left - padding.right;
    const plotHeight = height - padding.top - padding.bottom;
    const points = values.map((value, index) => {
        const x = padding.left + (labels.length <= 1 ? plotWidth / 2 : (index / (labels.length - 1)) * plotWidth);
        const y = padding.top + plotHeight - ((Number(value) || 0) / maximum) * plotHeight;
        return { x, y, value: Number(value) || 0 };
    });
    const path = points.map(({ x, y }, index) => `${index === 0 ? 'M' : 'L'} ${x} ${y}`).join(' ');

    return (
        <div className="simpeg-violation-trend">
            <div className="simpeg-violation-trend-plot-wrap">
                <svg
                    viewBox={`0 0 ${width} ${height}`}
                    className="simpeg-violation-trend-plot block w-full"
                    preserveAspectRatio="none"
                    role="img"
                    aria-label={`Tren pelanggaran tahun ${data?.year || ''}`}
                >
                    {[0, 0.5, 1].map((ratio) => {
                        const y = padding.top + plotHeight * ratio;
                        const gridValue = Math.round(maximum * (1 - ratio));
                        return (
                            <g key={ratio}>
                                <line x1={padding.left} x2={width - padding.right} y1={y} y2={y} stroke="var(--simpeg-chart-grid)" strokeDasharray="4 4" />
                                <text x={padding.left - 8} y={y + 4} textAnchor="end" fill="var(--simpeg-chart-label)" className="text-[10px]">{gridValue}</text>
                            </g>
                        );
                    })}
                    <path d={path} fill="none" stroke="var(--simpeg-chart-1)" strokeWidth="3" strokeLinejoin="round" strokeLinecap="round" />
                    {points.map((point, index) => (
                        <g key={labels[index]}>
                            <circle cx={point.x} cy={point.y} r="5" fill="var(--simpeg-surface)" stroke={index === highestIndex ? 'var(--simpeg-chart-highlight)' : 'var(--simpeg-chart-1)'} strokeWidth="3">
                                <title>{`${labels[index]}: ${number(point.value)} kasus`}</title>
                            </circle>
                            <text x={point.x} y={height - 12} textAnchor="middle" fill="var(--simpeg-chart-label)" className="text-[10px]">{labels[index]}</text>
                        </g>
                    ))}
                </svg>
            </div>
            <p className="simpeg-muted mt-2 text-center text-xs">Data pelanggaran per bulan, {data?.year}</p>
        </div>
    );
}

function CombinedEmployeeTrendPlot({ labels = [], series = [], ariaLabel }) {
    const safeLabels = asList(labels);
    const safeSeries = series.filter((item) => item && Array.isArray(item.values));
    const axis = safeSeries[0]?.axis || 'movement';
    const values = safeSeries.flatMap((item) => asList(item.values).map((value) => Number(value) || 0));

    if (!safeLabels.length || !values.some((value) => value > 0)) {
        return <EmptyData message="Belum ada tren pegawai untuk ditampilkan." />;
    }

    // Keep the chart's intrinsic height aligned with the violation trend
    // chart. The CSS presentation height then scales this viewBox cleanly
    // without compressing the vertical plot area.
    // Match the violation trend's SVG geometry so month labels keep the same
    // readable scale instead of being horizontally compressed by a wider
    // viewBox.
    const width = 720;
    const height = 260;
    const padding = { top: 18, right: 16, bottom: 36, left: 36 };
    const plotWidth = width - padding.left - padding.right;
    const plotHeight = height - padding.top - padding.bottom;
    const maximum = Math.max(...values, 1);
    const xFor = (index) => padding.left + (safeLabels.length <= 1 ? plotWidth / 2 : (index / (safeLabels.length - 1)) * plotWidth);
    const yFor = (value) => padding.top + plotHeight - ((Number(value) || 0) / maximum) * plotHeight;

    return (
        <div className="simpeg-employee-trend-plot-wrap">
            <svg viewBox={`0 0 ${width} ${height}`} className="simpeg-employee-trend-plot block w-full" preserveAspectRatio="none" role="img" aria-label={ariaLabel}>
                <text x={padding.left} y="12" fill="var(--simpeg-muted)" className="text-[10px]">{axis === 'total' ? 'Jumlah pegawai' : 'Pegawai per bulan'}</text>
                {[0, 0.5, 1].map((ratio) => {
                    const y = padding.top + plotHeight * ratio;
                    const value = Math.round(maximum * (1 - ratio));

                    return (
                        <g key={ratio}>
                            <line x1={padding.left} x2={width - padding.right} y1={y} y2={y} stroke="var(--simpeg-border)" strokeOpacity="0.8" strokeDasharray="4 4" />
                            <text x={padding.left - 8} y={y + 4} textAnchor="end" fill="var(--simpeg-muted)" className="text-[10px]">{value}</text>
                        </g>
                    );
                })}
                {safeSeries.map((item, seriesIndex) => {
                    const values = asList(item.values);
                    const points = safeLabels.map((_, index) => ({
                        x: xFor(index),
                        y: yFor(values[index]),
                        value: Number(values[index]) || 0,
                    }));
                    const path = points.map(({ x, y }, index) => `${index === 0 ? 'M' : 'L'} ${x} ${y}`).join(' ');
                    const baseline = padding.top + plotHeight;
                    const areaPath = `${path} L ${points[points.length - 1].x} ${baseline} L ${points[0].x} ${baseline} Z`;
                    const color = item.color || chartColors[seriesIndex] || 'var(--simpeg-chart-1)';
                    const fillOpacity = item.key === 'incoming' ? '0.12' : item.axis === 'total' ? '0.04' : '0';

                    return (
                        <g key={item.key || item.label || seriesIndex}>
                            <path d={areaPath} fill={color} fillOpacity={fillOpacity} stroke="none" />
                            <path d={path} fill="none" stroke={color} strokeWidth="3" strokeDasharray={item.dash || undefined} strokeLinejoin="round" strokeLinecap="round" />
                            {points.map((point, index) => (
                                <circle key={`${item.key || seriesIndex}-${safeLabels[index] || index}`} cx={point.x} cy={point.y} r="4.5" fill="var(--simpeg-card)" stroke={color} strokeWidth="2.5">
                                    <title>{`${item.label || item.key || 'Data'} · ${safeLabels[index] || '-'}: ${number(point.value)}`}</title>
                                </circle>
                            ))}
                        </g>
                    );
                })}
                {safeLabels.map((label, index) => (
                    <text key={label || index} x={xFor(index)} y={height - 12} textAnchor="middle" fill="var(--simpeg-muted)" className="text-[10px]">{label}</text>
                ))}
            </svg>
        </div>
    );
}

function TrendLegend({ series = [] }) {
    return (
        <div className="simpeg-dashboard-employee-trend-legend mb-3 flex flex-wrap items-center gap-x-4 gap-y-1">
            {series.map((item, index) => (
                <span key={item.key || item.label || index} className="simpeg-muted inline-flex items-center gap-2 text-xs">
                    <span className="h-2.5 w-2.5 rounded-full" style={{ backgroundColor: item.color || chartColors[index] || 'var(--simpeg-chart-1)' }} aria-hidden="true" />
                    {item.label || item.key}
                </span>
            ))}
        </div>
    );
}

function EmployeeTrendChart({ data = {}, unit }) {
    const [selectedYear, setSelectedYear] = useState(String(data?.year || new Date().getFullYear()));
    const [metric, setMetric] = useState('movement');
    const [filterOpen, setFilterOpen] = useState(false);
    const [trend, setTrend] = useState(data);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');

    useEffect(() => {
        setSelectedYear(String(data?.year || new Date().getFullYear()));
        setTrend(data);
        setError('');
    }, [data]);

    useEffect(() => {
        const initialYear = String(data?.year || '');
        if (!selectedYear || selectedYear === initialYear) {
            return undefined;
        }

        const controller = new AbortController();
        const params = new URLSearchParams({ year: selectedYear });
        if (unit) params.set('unit_kerja', unit);

        setLoading(true);
        setError('');
        fetch(`/dashboard/employee-trend?${params.toString()}`, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        })
            .then(async (response) => {
                if (!response.ok) throw new Error('Tren pegawai tidak dapat dimuat.');
                return response.json();
            })
            .then((result) => setTrend(result))
            .catch((fetchError) => {
                if (fetchError.name !== 'AbortError') setError(fetchError.message || 'Tren pegawai tidak dapat dimuat.');
            })
            .finally(() => {
                if (!controller.signal.aborted) setLoading(false);
            });

        return () => controller.abort();
    }, [data?.year, selectedYear, unit]);

    const years = useMemo(() => Array.from(new Set(asList(data?.years).concat(asList(trend?.years)).map(Number).filter(Boolean))).sort((a, b) => b - a).map(String), [data?.years, trend?.years]);
    const yearOptions = years.map((item) => ({ value: item, label: item }));
    const labels = asList(trend?.labels);
    const allTrendSeries = [
        { key: 'incoming', label: 'Pegawai masuk', axis: 'movement', values: asList(trend?.incoming), color: 'var(--simpeg-trend-incoming)' },
        { key: 'outgoing', label: 'Pegawai keluar', axis: 'movement', values: asList(trend?.outgoing), color: 'var(--simpeg-trend-outgoing)' },
        { key: 'active', label: 'Aktif', axis: 'total', values: asList(trend?.active), color: 'var(--simpeg-trend-active)' },
        { key: 'inactive', label: 'Nonaktif', axis: 'total', values: asList(trend?.inactive), color: 'var(--simpeg-trend-inactive)', dash: '6 4' },
    ];
    const trendSeries = metric === 'status' ? allTrendSeries.slice(2) : allTrendSeries.slice(0, 2);
    const metricOptions = [
        { value: 'movement', label: 'Masuk & keluar' },
        { value: 'status', label: 'Aktif & nonaktif' },
    ];

    const trendControls = (
        <div className="simpeg-employee-trend-filter-controls">
            <SelectCombobox
                id="employee-trend-metric"
                label="Tampilan"
                value={metric}
                options={metricOptions}
                optionValue={(option) => option.value}
                optionLabel={(option) => option.label}
                placeholder="Pilih tampilan"
                emptyMessage="Tampilan tidak ditemukan."
                onChange={setMetric}
            />
            <SelectCombobox
                id="employee-trend-year"
                label="Tahun"
                value={selectedYear}
                options={yearOptions}
                optionValue={(option) => option.value}
                optionLabel={(option) => option.label}
                placeholder="Pilih tahun"
                emptyMessage="Tahun tidak ditemukan."
                onChange={setSelectedYear}
            />
        </div>
    );

    return (
        <ChartCard
            id="employee-trend-chart-title"
            className="simpeg-dashboard-summary-chart-card simpeg-dashboard-employee-trend-card lg:col-span-6"
            title="Tren Pegawai"
            description={metric === 'status'
                ? 'Pegawai aktif & nonaktif per bulan.'
                : 'Pegawai masuk & keluar per bulan.'}
            icon={TrendingUp}
        >
            <div className="simpeg-employee-trend relative" aria-busy={loading}>
                {loading && <span className="simpeg-muted absolute right-0 top-0 text-xs" role="status">Memuat…</span>}
                {error && <p className="mb-3 text-sm text-red-600 dark:text-red-400" role="alert">{error}</p>}
                <div className="simpeg-dashboard-employee-trend-filter">
                    <button
                        type="button"
                        className="simpeg-dashboard-employee-trend-filter-trigger simpeg-utility"
                        aria-expanded={filterOpen}
                        aria-controls="employee-trend-filter-panel"
                        onClick={() => setFilterOpen((current) => !current)}
                    >
                        <SlidersHorizontal className="h-5 w-5" strokeWidth={1.8} aria-hidden="true" />
                        <span>Filter</span>
                    </button>
                    {filterOpen && (
                        <div id="employee-trend-filter-panel" className="simpeg-dashboard-employee-trend-filter-panel" role="group" aria-label="Filter tren pegawai">
                            {trendControls}
                        </div>
                    )}
                </div>
                <TrendLegend series={trendSeries} />
                <CombinedEmployeeTrendPlot
                    labels={labels}
                    series={trendSeries}
                    ariaLabel={`Tren ${metric === 'status' ? 'pegawai aktif dan nonaktif' : 'pegawai masuk dan keluar'} tahun ${trend?.year || ''}`}
                />
            </div>
        </ChartCard>
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
                <Search className="simpeg-subtle pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2" strokeWidth={1.8} aria-hidden="true" />
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
            <div className="simpeg-muted mt-4 flex flex-col gap-3 text-sm sm:flex-row sm:items-center sm:justify-between">
                <span>Menampilkan {filteredRows.length === 0 ? 0 : (currentPage - 1) * pageSize + 1} sampai {Math.min(currentPage * pageSize, filteredRows.length)} dari {filteredRows.length} data</span>
                <div className="flex items-center gap-2">
                    <Button type="button" variant="outline" size="sm" onClick={() => setPage((value) => Math.max(value - 1, 1))} disabled={currentPage === 1}>
                        <ChevronLeft className="h-4 w-4" strokeWidth={1.8} aria-hidden="true" />
                        <span className="hidden sm:inline">Sebelumnya</span>
                        <span className="sm:hidden">Prev</span>
                    </Button>
                    <span aria-live="polite" className="whitespace-nowrap">{currentPage} / {pageCount}</span>
                    <Button type="button" variant="dark" size="sm" className="simpeg-primary" onClick={() => setPage((value) => Math.min(value + 1, pageCount))} disabled={currentPage === pageCount}>
                        <span className="hidden sm:inline">Berikutnya</span>
                        <span className="sm:hidden">Next</span>
                        <ChevronRight className="h-4 w-4" strokeWidth={1.8} aria-hidden="true" />
                    </Button>
                </div>
            </div>
        </div>
    );
}

function PositionSummaryCard({ title, rows }) {
    const summary = useMemo(() => asList(rows).reduce((totals, row) => ({
        positions: totals.positions + 1,
        filled: totals.filled + (Number(row.terisi) || 0),
        quota: totals.quota + (Number(row.kuota) || 0),
        remaining: totals.remaining + (Number(row.sisa) || 0),
    }), { positions: 0, filled: 0, quota: 0, remaining: 0 }), [rows]);

    return (
        <Card className="simpeg-dashboard-position-summary-card min-w-0 border shadow-sm">
            <CardContent className="simpeg-dashboard-position-summary-content">
                <div className="simpeg-dashboard-position-summary-identity">
                    <span className="simpeg-dashboard-chart-icon flex h-10 w-10 shrink-0 items-center justify-center rounded-xl">
                        <BriefcaseBusiness className="h-5 w-5" strokeWidth={1.8} aria-hidden="true" />
                    </span>
                    <div className="simpeg-dashboard-position-summary-copy">
                        <h3 className="simpeg-text" title={title}>{title}</h3>
                        <span className="simpeg-dashboard-chart-meta">{number(summary.positions)} jabatan terdaftar</span>
                    </div>
                </div>
                <div className="simpeg-dashboard-position-summary-metrics">
                    {[
                        ['Terisi', summary.filled],
                        ['Kuota', summary.quota],
                        ['Sisa', summary.remaining],
                    ].map(([label, value]) => (
                        <div key={label} className="simpeg-dashboard-position-summary-metric">
                            <span className="simpeg-muted">{label}</span>
                            <strong className="simpeg-text">{number(value)}</strong>
                        </div>
                    ))}
                </div>
            </CardContent>
        </Card>
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
                    { key: 'terisi', label: 'Terisi', align: 'center', render: (row) => <span className="simpeg-text font-medium">{number(row.terisi)}</span> },
                    { key: 'kuota', label: 'Kuota', align: 'center', render: (row) => <span className="simpeg-text font-medium">{number(row.kuota)}</span> },
                    { key: 'sisa', label: 'Sisa', align: 'center', render: (row) => <span className="simpeg-text font-medium">{number(row.sisa)}</span> },
                    { key: 'status', label: 'Status', align: 'center', render: (row) => <Badge variant={row.status === 'Tersedia' ? 'success' : row.status === 'Kosong' ? 'danger' : 'warning'}>{row.status}</Badge> },
                ]}
            />
        </ChartCard>
    );
}

export function DashboardCharts({ charts = {}, retirement = [], executivePositions = [], structuralPositions = [], year, unit, tenureSummary = null }) {
    const positions = asList(charts.positions);
    const employeeTrend = charts.employeeTrend || { year, years: [], labels: [], incoming: [], outgoing: [], active: [], inactive: [] };

    return (
        <div className="space-y-4">
            <section className="grid min-w-0 gap-4 lg:grid-cols-12" aria-label="Ringkasan status kepegawaian">
                <ChartCard id="employment-chart-title" className="simpeg-dashboard-summary-chart-card lg:col-span-6" title="Status Kepegawaian" description="Distribusi status kepegawaian pegawai aktif." icon={UsersRound}>
                    <VerticalBars {...(charts.employment || {})} emptyMessage="Belum ada data status kepegawaian pegawai aktif." />
                </ChartCard>
                <ChartCard id="violation-chart-title" className="simpeg-dashboard-summary-chart-card lg:col-span-6" title="Tren Pelanggaran" description={`Jumlah pelanggaran per bulan pada tahun ${year}.`} icon={ShieldAlert}>
                    <TrendChart data={charts.violations || { labels: [], values: [], year }} />
                </ChartCard>
            </section>

            {tenureSummary}

            <section className="grid min-w-0 gap-4 lg:grid-cols-12" aria-label="Tren dan komposisi pegawai">
                <EmployeeTrendChart data={employeeTrend} unit={unit} />
                <ChartCard id="gender-chart-title" className="simpeg-dashboard-summary-chart-card simpeg-dashboard-gender-card lg:col-span-6" title="Komposisi Gender" description="Pegawai aktif berdasarkan jenis kelamin yang tersimpan di data pegawai." icon={UsersRound}>
                    <DonutChart data={charts.gender || { labels: [], values: [], total: 0 }} />
                </ChartCard>
            </section>

            <section className="grid min-w-0 gap-4 lg:grid-cols-12" aria-label="Pendidikan dan statistik kepegawaian">
                <ChartCard id="education-chart-title" className="simpeg-dashboard-summary-chart-card simpeg-dashboard-education-card lg:col-span-6" title="Jenjang Pendidikan" description="Pendidikan tertinggi setiap pegawai aktif, berdasarkan riwayat pendidikan sistem." icon={GraduationCap}>
                    <HorizontalBars {...(charts.education || {})} compact emptyMessage="Belum ada data jenjang pendidikan pegawai aktif." />
                </ChartCard>
                <ChartCard id="position-chart-title" title="Statistik Kepegawaian" description="Jumlah pegawai aktif berdasarkan jabatan aktif saat ini." meta={positions.length ? `${number(positions.length)} jabatan` : null} className="simpeg-dashboard-summary-chart-card simpeg-dashboard-position-card lg:col-span-6" icon={BriefcaseBusiness}>
                    {positions.length ? (
                        <div className="simpeg-dashboard-position-scroll">
                            <PositionBars positions={positions} />
                        </div>
                    ) : <EmptyData message="Belum ada statistik jabatan pegawai aktif." />}
                </ChartCard>
            </section>

            <ChartCard id="retirement-title" title={`Daftar Pensiun ${year}`} description="Monitoring pegawai yang memasuki masa pensiun pada tahun berjalan." className="lg:col-span-12" icon={CalendarClock}>
                <SearchableTable
                    rows={retirement}
                    searchPlaceholder="Cari pegawai"
                    inputId="retirement-search"
                    emptyMessage={`Belum ada pegawai yang memasuki masa pensiun pada tahun ${year}.`}
                    columns={[
                        { key: 'nama', label: 'Pegawai', render: (row) => <div><strong className="simpeg-text block font-medium">{row.nama || '-'}</strong><span className="simpeg-muted text-xs">ID: {row.id_peg}</span></div> },
                        { key: 'jabatan', label: 'Jabatan' },
                        { key: 'status', label: 'Status', render: (row) => <Badge variant={row.status === 'Segera Pensiun' ? 'warning' : 'success'}>{row.status}</Badge> },
                        { key: 'tgl_pensiun', label: 'Tgl Pensiun' },
                        { key: 'hari_tersisa', label: 'Sisa hari', align: 'right', render: (row) => row.hari_tersisa > 0 ? <span className="font-medium text-amber-700">{number(row.hari_tersisa)} hari lagi</span> : <span className="simpeg-muted">Selesai</span> },
                    ]}
                />
            </ChartCard>

            <section aria-label="Ketersediaan jabatan">
                <div className="mb-4 grid min-w-0 gap-4 xl:grid-cols-2">
                    <PositionSummaryCard title="Jabatan Eksekutif" rows={executivePositions} />
                    <PositionSummaryCard title="Jabatan Struktural" rows={structuralPositions} />
                </div>
                <div className="grid min-w-0 gap-6">
                    <PositionAvailabilityTable title="Jabatan Eksekutif" description="Ketersediaan posisi dan kuota jabatan kelompok PE." rows={executivePositions} searchId="executive-positions-title" />
                    <PositionAvailabilityTable title="Jabatan Struktural" description="Ketersediaan posisi dan kuota jabatan kelompok PS." rows={structuralPositions} searchId="structural-positions-title" />
                </div>
            </section>
        </div>
    );
}
