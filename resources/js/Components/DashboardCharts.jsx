import { useEffect, useMemo, useRef, useState } from 'react';
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
    X,
    ZoomIn,
} from 'lucide-react';
import { Badge } from '../Components/ui/badge';
import { Button } from '../Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '../Components/ui/card';
import SelectCombobox from './SelectCombobox';
import PageSizeSelect from './PageSizeSelect';
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

const dashboardYearOptions = (() => {
    const latestYear = new Date().getFullYear();

    return Array.from({ length: Math.max(latestYear - 1999, 1) }, (_, index) => {
        const optionYear = latestYear - index;

        return { value: String(optionYear), label: String(optionYear) };
    });
})();

function number(value) {
    return new Intl.NumberFormat('id-ID').format(Number(value) || 0);
}

function percentage(value, total) {
    const safeTotal = Number(total) || 0;
    const safeValue = Number(value) || 0;

    if (safeTotal === 0) {
        return '0%';
    }

    return new Intl.NumberFormat('id-ID', {
        maximumFractionDigits: 1,
        minimumFractionDigits: 1,
        style: 'percent',
    }).format(safeValue / safeTotal);
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

function activatePointOnKeyDown(event, onActivate) {
    if (event.key !== 'Enter' && event.key !== ' ') {
        return;
    }

    event.preventDefault();
    onActivate();
}

function ChartYearFilter({ id, value, onChange, disabled = false, options = dashboardYearOptions }) {
    return (
        <SelectCombobox
            id={`${id}-year`}
            label="Tahun"
            value={String(value)}
            options={options}
            optionValue={(option) => option.value}
            optionLabel={(option) => option.label}
            placeholder="Pilih tahun"
            emptyMessage="Tahun tidak ditemukan."
            onChange={onChange}
            disabled={disabled}
            className="simpeg-dashboard-chart-year-filter"
        />
    );
}

function ChartFilterButton({ id, open, onClick, disabled = false }) {
    return (
        <button
            type="button"
            className="simpeg-dashboard-chart-filter-trigger simpeg-utility"
            aria-expanded={open}
            aria-controls={`${id}-filter-panel`}
            aria-label={open ? 'Tutup filter chart' : 'Buka filter chart'}
            onClick={onClick}
            disabled={disabled}
        >
            <SlidersHorizontal className="h-4 w-4" strokeWidth={1.8} aria-hidden="true" />
            <span>Filter</span>
        </button>
    );
}

function ChartFilterPanel({ id, label, children }) {
    return (
        <div id={`${id}-filter-panel`} className="simpeg-dashboard-chart-filter-panel" role="group" aria-label={label}>
            {children}
        </div>
    );
}

function useYearFilteredData({ data, unit, endpoint }) {
    const initialYear = String(data?.year || new Date().getFullYear());
    const [selectedYear, setSelectedYear] = useState(initialYear);
    const [result, setResult] = useState(data);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');

    useEffect(() => {
        setSelectedYear(initialYear);
        setResult(data);
        setError('');
    }, [data, initialYear]);

    useEffect(() => {
        if (selectedYear === initialYear) {
            setResult(data);
            setLoading(false);
            return undefined;
        }

        const controller = new AbortController();
        const params = new URLSearchParams({ year: selectedYear });
        if (unit) params.set('unit_kerja', unit);

        setLoading(true);
        setError('');
        fetch(`/dashboard/chart/${endpoint}?${params.toString()}`, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        })
            .then(async (response) => {
                if (!response.ok) throw new Error('Data chart tidak dapat dimuat.');
                return response.json();
            })
            .then((payload) => setResult(payload))
            .catch((fetchError) => {
                if (fetchError.name !== 'AbortError') setError(fetchError.message || 'Data chart tidak dapat dimuat.');
            })
            .finally(() => {
                if (!controller.signal.aborted) setLoading(false);
            });

        return () => controller.abort();
    }, [data, endpoint, initialYear, selectedYear, unit]);

    return { initialYear, selectedYear, setSelectedYear, result, loading, error };
}

function ChartCard({ id, title, description, children, className = '', icon: Icon = Activity, meta = null, actions = null, preview = false }) {
    const [previewOpen, setPreviewOpen] = useState(false);
    const previewDialog = useRef(null);

    useEffect(() => {
        const dialog = previewDialog.current;
        if (!dialog) return;
        if (previewOpen && !dialog.open) dialog.showModal();
        if (!previewOpen && dialog.open) dialog.close();
    }, [previewOpen]);

    return (
        <>
            <Card className={`simpeg-dashboard-card simpeg-dashboard-chart-card min-w-0 border shadow-sm ${className}`} aria-labelledby={id}>
                <CardHeader className="simpeg-dashboard-card-header flex-row items-start gap-3 border-b simpeg-border">
                    <span className="simpeg-dashboard-chart-icon flex h-10 w-10 shrink-0 items-center justify-center rounded-xl">
                        <Icon className="h-5 w-5" strokeWidth={1.8} aria-hidden="true" />
                    </span>
                    <div className="min-w-0 flex-1">
                        <div className="flex min-w-0 items-start justify-between gap-3">
                            <CardTitle as="h2" id={id}>{title}</CardTitle>
                            {(preview || actions || meta) && (
                                <div className="simpeg-dashboard-chart-header-actions flex shrink-0 items-start gap-2">
                                    {actions}
                                    {preview && <button type="button" className="simpeg-dashboard-chart-preview-button" title={`Perbesar ${title}`} aria-label={`Perbesar ${title}`} onClick={() => setPreviewOpen(true)}><ZoomIn className="h-4 w-4" strokeWidth={1.8} aria-hidden="true" /></button>}
                                    {meta && <span className="simpeg-dashboard-chart-meta">{meta}</span>}
                                </div>
                            )}
                        </div>
                        {description && <CardDescription className="mt-1">{description}</CardDescription>}
                    </div>
                </CardHeader>
                <CardContent className="simpeg-dashboard-card-content">{children}</CardContent>
            </Card>
            {preview && <dialog
                ref={previewDialog}
                className="simpeg-chart-preview-dialog"
                aria-labelledby={`${id}-preview-title`}
                aria-describedby={`${id}-preview-description`}
                onClose={() => setPreviewOpen(false)}
                onClick={(event) => {
                    if (event.target === event.currentTarget) setPreviewOpen(false);
                }}
            >
                <div className="simpeg-chart-preview-dialog__inner">
                    <header className="simpeg-chart-preview-dialog__header">
                        <div className="flex min-w-0 items-start gap-3">
                            <span className="simpeg-dashboard-chart-icon flex h-10 w-10 shrink-0 items-center justify-center rounded-xl"><Icon className="h-5 w-5" strokeWidth={1.8} aria-hidden="true" /></span>
                            <div className="min-w-0">
                                <h2 id={`${id}-preview-title`} className="simpeg-text text-lg font-bold">{title}</h2>
                                {description && <p id={`${id}-preview-description`} className="simpeg-muted mt-1 text-sm">{description}</p>}
                            </div>
                        </div>
                        <button type="button" className="simpeg-utility inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border" title="Tutup preview" aria-label="Tutup preview" onClick={() => setPreviewOpen(false)}><X className="h-4 w-4" strokeWidth={1.8} aria-hidden="true" /></button>
                    </header>
                    <div className="simpeg-chart-preview-dialog__content">{children}</div>
                </div>
            </dialog>}
        </>
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
    const ringStrokeWidth = 32;
    const labelRadius = 70;
    const labelTextLength = 26;
    const circumference = 2 * Math.PI * radius;
    let offset = 0;

    const segments = values.map((value, index) => {
        const length = total > 0 ? (Number(value) / total) * circumference : 0;
        const midpoint = offset + length / 2;
        const angle = total > 0 ? (midpoint / circumference) * 2 * Math.PI : 0;
        const segment = {
            color: chartColors[index] || 'var(--simpeg-chart-3)',
            labelColor: 'white',
            labelOutlineColor: 'var(--simpeg-chart-1)',
            labelX: 100 + labelRadius * Math.sin(angle),
            labelY: 100 - labelRadius * Math.cos(angle),
            length,
            offset,
        };
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
                    <circle cx="100" cy="100" r={radius} fill="none" stroke="var(--simpeg-text)" strokeOpacity="0.12" strokeWidth={ringStrokeWidth} />
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
                            strokeWidth={ringStrokeWidth}
                            transform="rotate(-90 100 100)"
                        >
                            <title>{`${labels[index] || '-'}: ${percentage(values[index], total)} (${number(values[index])} pegawai)`}</title>
                        </circle>
                    ))}
                    {segments.map((segment, index) => (
                        <text
                            key={`${labels[index] || index}-percentage`}
                            x={segment.labelX}
                            y={segment.labelY}
                            textAnchor="middle"
                            dominantBaseline="middle"
                            fill={segment.labelColor}
                            stroke={segment.labelOutlineColor}
                            strokeWidth="2"
                            paintOrder="stroke"
                            textLength={labelTextLength}
                            lengthAdjust="spacingAndGlyphs"
                            className="pointer-events-none text-[9px] font-extrabold"
                        >
                            {percentage(values[index], total)}
                        </text>
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
                        <span className="simpeg-text whitespace-nowrap text-right text-sm tabular-nums">
                            <span className="block font-semibold">{percentage(values[index], total)}</span>
                            <span className="simpeg-muted block text-xs font-normal">{number(values[index])} pegawai</span>
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
    const [darkMode, setDarkMode] = useState(() => typeof document !== 'undefined' && document.documentElement.classList.contains('dark'));
    const safeLabels = asList(labels);
    const safeValues = asList(values);

    useEffect(() => {
        if (typeof document === 'undefined') return undefined;

        const observer = new MutationObserver(() => setDarkMode(document.documentElement.classList.contains('dark')));
        observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
        return () => observer.disconnect();
    }, []);

    if (!hasValues(safeValues)) {
        return <EmptyData message={emptyMessage} />;
    }

    const data = safeLabels.map((label, index) => ({
        label,
        value: Number(safeValues[index]) || 0,
        fill: darkMode && index === 0
            ? 'url(#dashboard-status-bar-gradient)'
            : `var(--simpeg-status-bar-${(index % 4) + 1})`,
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
                    <defs>
                        <linearGradient id="dashboard-status-bar-gradient" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stopColor="#2F7BFF" />
                            <stop offset="100%" stopColor="#1257D6" />
                        </linearGradient>
                    </defs>
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
                    <Bar dataKey="value" fill="var(--color-value)" radius={darkMode ? [8, 8, 0, 0] : [12, 12, 0, 0]} maxBarSize={64} isAnimationActive={false}>
                        {data.map((entry) => <Cell key={entry.label} fill={entry.fill} />)}
                    </Bar>
                </BarChart>
            </ChartContainer>
        </div>
    );
}

function TrendChart({ data, displayYear }) {
    const values = asList(data?.values);
    const labels = asList(data?.labels);
    const [selectedIndex, setSelectedIndex] = useState(null);
    const chartYear = displayYear || data?.year || '';

    useEffect(() => {
        setSelectedIndex(null);
    }, [data]);

    if (!hasValues(values)) {
        return <EmptyData message={`Belum ada pelanggaran tercatat pada tahun ${chartYear}.`} />;
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

    const selectedValue = selectedIndex === null ? null : Number(values[selectedIndex]) || 0;
    const selectedPoint = selectedIndex === null ? null : points[selectedIndex];
    const calloutWidth = 100;
    const calloutHeight = 30;
    const calloutX = selectedPoint ? Math.max(4, Math.min(selectedPoint.x - calloutWidth / 2, width - calloutWidth - 4)) : 0;
    const calloutY = selectedPoint ? Math.max(4, selectedPoint.y - calloutHeight - 12) : 0;

    return (
        <div className="simpeg-violation-trend">
            <div className="simpeg-violation-trend-plot-wrap">
                <svg
                    viewBox={`0 0 ${width} ${height}`}
                    className="simpeg-violation-trend-plot block w-full"
                    preserveAspectRatio="none"
                    role="img"
                    aria-label={`Tren pelanggaran tahun ${chartYear}`}
                >
                    {[0, 0.5, 1].map((ratio) => {
                        const y = padding.top + plotHeight * ratio;
                        const gridValue = Math.round(maximum * (1 - ratio));
                        return (
                            <g key={ratio}>
                                <line x1={padding.left} x2={width - padding.right} y1={y} y2={y} stroke="var(--simpeg-chart-grid)" strokeDasharray="4 4" />
                                <text x={padding.left - 8} y={y + 4} textAnchor="end" fill="var(--simpeg-text)" className="simpeg-chart-axis-label text-[10px]">{gridValue}</text>
                            </g>
                        );
                    })}
                    <path d={path} fill="none" stroke="var(--simpeg-chart-1)" strokeWidth="3" strokeLinejoin="round" strokeLinecap="round" />
                    {points.map((point, index) => (
                        <g key={labels[index]}>
                            <circle
                                cx={point.x}
                                cy={point.y}
                                r={selectedIndex === index ? '7' : '5'}
                                fill="var(--simpeg-surface)"
                                stroke={index === highestIndex ? 'var(--simpeg-chart-highlight)' : 'var(--simpeg-chart-1)'}
                                strokeWidth={selectedIndex === index ? '4' : '3'}
                                className="simpeg-chart-point"
                                role="button"
                                tabIndex={0}
                                aria-label={`${labels[index] || '-'}: ${number(point.value)} kasus`}
                                onClick={() => setSelectedIndex(index)}
                                onKeyDown={(event) => activatePointOnKeyDown(event, () => setSelectedIndex(index))}
                            >
                                <title>{`${labels[index]}: ${number(point.value)} kasus`}</title>
                            </circle>
                            <text x={point.x} y={height - 12} textAnchor="middle" fill="var(--simpeg-text)" className="simpeg-chart-axis-label text-[10px]">{labels[index]}</text>
                        </g>
                    ))}
                    {selectedPoint && (
                        <g className="simpeg-chart-point-callout" pointerEvents="none">
                            <path d={`M ${selectedPoint.x - 5} ${calloutY + calloutHeight} L ${selectedPoint.x} ${calloutY + calloutHeight + 6} L ${selectedPoint.x + 5} ${calloutY + calloutHeight} Z`} />
                            <rect x={calloutX} y={calloutY} width={calloutWidth} height={calloutHeight} rx="8" />
                            <text x={calloutX + calloutWidth / 2} y={calloutY + 19} textAnchor="middle">{number(selectedValue)} kasus</text>
                        </g>
                    )}
                </svg>
            </div>
            <p className="simpeg-muted mt-2 text-center text-xs">Data pelanggaran per bulan, {chartYear}</p>
        </div>
    );
}

function ViolationChart({ data, unit }) {
    const { selectedYear, setSelectedYear, result, loading, error } = useYearFilteredData({
        data,
        unit,
        endpoint: 'violations',
    });
    const [filterOpen, setFilterOpen] = useState(false);

    return (
        <ChartCard
            id="violation-chart-title"
            preview
            className="simpeg-dashboard-summary-chart-card simpeg-dashboard-violation-chart-card lg:col-span-6"
            title="Tren Pelanggaran"
            description={`Jumlah pelanggaran per bulan pada tahun ${selectedYear}.`}
            icon={ShieldAlert}
            actions={<ChartFilterButton id="violation" open={filterOpen} onClick={() => setFilterOpen((current) => !current)} disabled={loading} />}
        >
            <div className="simpeg-dashboard-chart-filter-content">
                {filterOpen && (
                    <ChartFilterPanel id="violation" label="Filter tren pelanggaran">
                        <ChartYearFilter id="violation" value={selectedYear} onChange={setSelectedYear} disabled={loading} />
                    </ChartFilterPanel>
                )}
            </div>
            <div className="relative" aria-busy={loading}>
                {loading && <span className="simpeg-muted absolute right-0 top-0 text-xs" role="status">Memuat…</span>}
                {error && <p className="mb-3 text-sm text-red-600 dark:text-red-400" role="alert">{error}</p>}
                <TrendChart data={result} displayYear={selectedYear} />
            </div>
        </ChartCard>
    );
}

function CombinedEmployeeTrendPlot({ labels = [], series = [], ariaLabel }) {
    const safeLabels = asList(labels);
    const safeSeries = series.filter((item) => item && Array.isArray(item.values));
    const [selectedPoint, setSelectedPoint] = useState(null);

    useEffect(() => {
        setSelectedPoint(null);
    }, [ariaLabel]);
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

    const selectedSeries = selectedPoint ? safeSeries[selectedPoint.seriesIndex] : null;
    const selectedSeriesValues = selectedSeries ? asList(selectedSeries.values) : [];
    const selectedValue = selectedPoint ? Number(selectedSeriesValues[selectedPoint.index]) || 0 : null;
    const selectedLabel = selectedPoint ? safeLabels[selectedPoint.index] || '-' : '';
    const selectedPointGeometry = selectedPoint && selectedSeries
        ? { x: xFor(selectedPoint.index), y: yFor(selectedValue) }
        : null;
    const calloutWidth = 132;
    const calloutHeight = 38;
    const calloutX = selectedPointGeometry ? Math.max(4, Math.min(selectedPointGeometry.x - calloutWidth / 2, width - calloutWidth - 4)) : 0;
    const calloutY = selectedPointGeometry ? Math.max(4, selectedPointGeometry.y - calloutHeight - 12) : 0;

    return (
        <div>
            <div className="simpeg-employee-trend-plot-wrap">
            <svg viewBox={`0 0 ${width} ${height}`} className="simpeg-employee-trend-plot block w-full" preserveAspectRatio="none" role="img" aria-label={ariaLabel}>
                <text x={padding.left} y="12" fill="var(--simpeg-muted)" className="text-[10px]">{axis === 'total' ? 'Jumlah pegawai' : 'Pegawai per bulan'}</text>
                {[0, 0.5, 1].map((ratio) => {
                    const y = padding.top + plotHeight * ratio;
                    const value = Math.round(maximum * (1 - ratio));

                    return (
                        <g key={ratio}>
                            <line x1={padding.left} x2={width - padding.right} y1={y} y2={y} stroke="var(--simpeg-border)" strokeOpacity="0.8" strokeDasharray="4 4" />
                            <text x={padding.left - 8} y={y + 4} textAnchor="end" fill="var(--simpeg-text)" className="simpeg-chart-axis-label text-[10px]">{value}</text>
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
                                <circle
                                    key={`${item.key || seriesIndex}-${safeLabels[index] || index}`}
                                    cx={point.x}
                                    cy={point.y}
                                    r={selectedPoint?.seriesIndex === seriesIndex && selectedPoint?.index === index ? '6.5' : '4.5'}
                                    fill="var(--simpeg-card)"
                                    stroke={color}
                                    strokeWidth={selectedPoint?.seriesIndex === seriesIndex && selectedPoint?.index === index ? '4' : '2.5'}
                                    className="simpeg-chart-point"
                                    role="button"
                                    tabIndex={0}
                                    aria-label={`${item.label || item.key || 'Data'}, ${safeLabels[index] || '-'}: ${number(point.value)}`}
                                    onClick={() => setSelectedPoint({ seriesIndex, index })}
                                    onKeyDown={(event) => activatePointOnKeyDown(event, () => setSelectedPoint({ seriesIndex, index }))}
                                >
                                    <title>{`${item.label || item.key || 'Data'} · ${safeLabels[index] || '-'}: ${number(point.value)}`}</title>
                                </circle>
                            ))}
                        </g>
                    );
                })}
                {safeLabels.map((label, index) => (
                    <text key={label || index} x={xFor(index)} y={height - 12} textAnchor="middle" fill="var(--simpeg-text)" className="simpeg-chart-axis-label text-[10px]">{label}</text>
                ))}
                {selectedPointGeometry && selectedSeries && (
                    <g className="simpeg-chart-point-callout" pointerEvents="none">
                        <path d={`M ${selectedPointGeometry.x - 5} ${calloutY + calloutHeight} L ${selectedPointGeometry.x} ${calloutY + calloutHeight + 6} L ${selectedPointGeometry.x + 5} ${calloutY + calloutHeight} Z`} />
                        <rect x={calloutX} y={calloutY} width={calloutWidth} height={calloutHeight} rx="8" />
                        <text x={calloutX + calloutWidth / 2} y={calloutY + 16} textAnchor="middle" className="simpeg-chart-point-callout-value">{number(selectedValue)} pegawai</text>
                        <text x={calloutX + calloutWidth / 2} y={calloutY + 30} textAnchor="middle" className="simpeg-chart-point-callout-label">{selectedSeries.label || selectedSeries.key} · {selectedLabel}</text>
                    </g>
                )}
            </svg>
            </div>
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
            <ChartYearFilter id="employee-trend" value={selectedYear} options={yearOptions} onChange={setSelectedYear} disabled={loading} />
        </div>
    );

    return (
        <ChartCard
            id="employee-trend-chart-title"
            preview
            className="simpeg-dashboard-summary-chart-card simpeg-dashboard-employee-trend-card lg:col-span-6"
            title="Tren Pegawai"
            description={metric === 'status'
                ? 'Pegawai aktif & nonaktif per bulan.'
                : 'Pegawai masuk & keluar per bulan.'}
            icon={TrendingUp}
            actions={<ChartFilterButton id="employee-trend" open={filterOpen} onClick={() => setFilterOpen((current) => !current)} disabled={loading} />}
        >
            <div className="simpeg-employee-trend relative" aria-busy={loading}>
                {loading && <span className="sr-only" role="status">Memuat data tren pegawai…</span>}
                {error && <p className="mb-3 text-sm text-red-600 dark:text-red-400" role="alert">{error}</p>}
                <div className="simpeg-dashboard-chart-filter-content">
                    {filterOpen && <ChartFilterPanel id="employee-trend" label="Filter tren pegawai">{trendControls}</ChartFilterPanel>}
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

function SearchableTable({ rows, columns, searchPlaceholder, emptyMessage, inputId, pageSize: initialPageSize = 20, tableClassName = 'min-w-[42rem]' }) {
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);
    const [pageSize, setPageSize] = useState(initialPageSize);
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
            <div className="mb-4 flex flex-wrap items-start justify-between gap-3">
                <div className="relative w-full max-w-md">
                    <Search className="simpeg-subtle pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2" strokeWidth={1.8} aria-hidden="true" />
                    <Input id={inputId} type="search" value={search} onChange={(event) => setSearch(event.target.value)} placeholder={searchPlaceholder} className="pl-10" />
                </div>
                <PageSizeSelect value={pageSize} onChange={(size) => { setPageSize(size); setPage(1); }} label={`${searchPlaceholder}: jumlah data per halaman`} />
            </div>
            {visibleRows.length === 0 ? (
                <EmptyData message="Tidak ada data yang cocok dengan pencarian." />
            ) : (
                <div>
                    <div className="simpeg-dashboard-availability-table-scroll hidden overflow-x-auto sm:block">
                        <Table className={`simpeg-dashboard-availability-table ${tableClassName}`}>
                            <TableHeader>
                                <TableRow className="hover:bg-transparent">{columns.map((column) => <TableHead key={column.key} scope="col" className={[column.className, column.align === 'right' ? 'text-right' : column.align === 'center' ? 'text-center' : ''].filter(Boolean).join(' ')}>{column.label}</TableHead>)}</TableRow>
                            </TableHeader>
                            <TableBody>
                                {visibleRows.map((row, index) => (
                                    <TableRow key={row.id_peg || row.kode_jabatan || `${row.nama}-${index}`}>
                                        {columns.map((column) => <TableCell key={column.key} className={[column.className, column.align === 'right' ? 'text-right' : column.align === 'center' ? 'text-center' : ''].filter(Boolean).join(' ')}>{column.render ? column.render(row) : row[column.key]}</TableCell>)}
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
            <div className={`simpeg-muted mt-4 flex flex-col gap-3 text-sm sm:flex-row sm:items-center ${pageCount > 1 ? 'sm:justify-between' : ''}`}>
                <span>Menampilkan {filteredRows.length === 0 ? 0 : (currentPage - 1) * pageSize + 1} sampai {Math.min(currentPage * pageSize, filteredRows.length)} dari {filteredRows.length} data</span>
                {pageCount > 1 && (
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
                )}
            </div>
        </div>
    );
}

function RetirementChart({ data, unit }) {
    const { selectedYear, setSelectedYear, result, loading, error } = useYearFilteredData({
        data,
        unit,
        endpoint: 'retirement',
    });
    const rows = asList(result?.rows);
    const [filterOpen, setFilterOpen] = useState(false);

    return (
        <ChartCard
            id="retirement-title"
            title={`Daftar Pensiun ${selectedYear}`}
            description="Monitoring pegawai yang memasuki masa pensiun pada tahun yang dipilih."
            className="lg:col-span-12"
            icon={CalendarClock}
            actions={<ChartFilterButton id="retirement" open={filterOpen} onClick={() => setFilterOpen((current) => !current)} disabled={loading} />}
        >
            <div className="simpeg-dashboard-chart-filter-content">
                {filterOpen && (
                    <ChartFilterPanel id="retirement" label="Filter daftar pensiun">
                        <ChartYearFilter id="retirement" value={selectedYear} onChange={setSelectedYear} disabled={loading} />
                    </ChartFilterPanel>
                )}
            </div>
            <div aria-busy={loading}>
                {loading && <p className="simpeg-muted mb-3 text-xs" role="status">Memuat data pensiun…</p>}
                {error && <p className="mb-3 text-sm text-red-600 dark:text-red-400" role="alert">{error}</p>}
                <SearchableTable
                    rows={rows}
                    searchPlaceholder="Cari pegawai"
                    inputId="retirement-search"
                    emptyMessage={`Belum ada pegawai yang memasuki masa pensiun pada tahun ${selectedYear}.`}
                    columns={[
                        { key: 'nama', label: 'Pegawai', render: (row) => <div><strong className="simpeg-text block font-medium">{row.nama || '-'}</strong><span className="simpeg-muted text-xs">ID: {row.id_peg}</span></div> },
                        { key: 'jabatan', label: 'Jabatan' },
                        { key: 'status', label: 'Status', render: (row) => <Badge variant={row.status === 'Segera Pensiun' ? 'warning' : 'success'}>{row.status}</Badge> },
                        { key: 'tgl_pensiun', label: 'Tgl Pensiun' },
                        { key: 'hari_tersisa', label: 'Sisa hari', align: 'right', render: (row) => row.hari_tersisa > 0 ? <span className="font-medium text-amber-700">{number(row.hari_tersisa)} hari lagi</span> : <span className="simpeg-muted">Selesai</span> },
                    ]}
                />
            </div>
        </ChartCard>
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
        <ChartCard id={searchId} title={title} description={description} icon={BriefcaseBusiness} meta={`${number(asList(rows).length)} jabatan`} preview className="simpeg-dashboard-availability-card">
            <SearchableTable
                rows={rows}
                searchPlaceholder="Cari jabatan"
                inputId={`${searchId}-search`}
                pageSize={25}
                emptyMessage="Belum ada referensi jabatan pada kelompok ini."
                tableClassName="w-full min-w-0 table-fixed"
                columns={[
                    { key: 'nama_jabatan', label: 'Deskripsi Jabatan', className: 'w-[46%] break-words' },
                    { key: 'terisi', label: 'Terisi', className: 'w-[11%]', align: 'center', render: (row) => <span className="simpeg-text font-medium">{number(row.terisi)}</span> },
                    { key: 'kuota', label: 'Kuota', className: 'w-[11%]', align: 'center', render: (row) => <span className="simpeg-text font-medium">{number(row.kuota)}</span> },
                    { key: 'sisa', label: 'Sisa', className: 'w-[11%]', align: 'center', render: (row) => <span className="simpeg-text font-medium">{number(row.sisa)}</span> },
                    { key: 'status', label: 'Status', className: 'w-[21%] whitespace-normal', align: 'center', render: (row) => <Badge variant={row.status === 'Tersedia' ? 'success' : row.status === 'Kosong' ? 'danger' : 'warning'}>{row.status}</Badge> },
                ]}
            />
        </ChartCard>
    );
}

export function DashboardCharts({ charts = {}, retirement = [], executivePositions = [], structuralPositions = [], year, unit, tenureSummary = null }) {
    const positions = asList(charts.positions);
    const employeeTrend = charts.employeeTrend || { year, years: [], labels: [], incoming: [], outgoing: [], active: [], inactive: [] };
    const violations = charts.violations || { year, labels: [], values: [] };
    const retirementData = useMemo(() => ({ year, rows: retirement }), [retirement, year]);

    return (
        <div className="space-y-4">
            <section className="grid min-w-0 gap-4 lg:grid-cols-12" aria-label="Ringkasan status kepegawaian">
                <ChartCard id="employment-chart-title" preview className="simpeg-dashboard-summary-chart-card simpeg-dashboard-status-chart-card lg:col-span-6" title="Status Kepegawaian" description="Distribusi status kepegawaian pegawai aktif." icon={UsersRound}>
                    <VerticalBars {...(charts.employment || {})} emptyMessage="Belum ada data status kepegawaian pegawai aktif." />
                </ChartCard>
                <ViolationChart data={violations} unit={unit} />
            </section>

            {tenureSummary}

            <section className="grid min-w-0 gap-4 lg:grid-cols-12" aria-label="Tren dan komposisi pegawai">
                <EmployeeTrendChart data={employeeTrend} unit={unit} />
                <ChartCard id="gender-chart-title" preview className="simpeg-dashboard-summary-chart-card simpeg-dashboard-gender-card lg:col-span-6" title="Komposisi Gender" description="Pegawai aktif berdasarkan jenis kelamin yang tersimpan di data pegawai." icon={UsersRound}>
                    <DonutChart data={charts.gender || { labels: [], values: [], total: 0 }} />
                </ChartCard>
            </section>

            <section className="grid min-w-0 gap-4 lg:grid-cols-12" aria-label="Pendidikan dan statistik kepegawaian">
                <ChartCard id="education-chart-title" preview className="simpeg-dashboard-summary-chart-card simpeg-dashboard-education-card lg:col-span-6" title="Jenjang Pendidikan" description="Pendidikan tertinggi setiap pegawai aktif, berdasarkan riwayat pendidikan sistem." icon={GraduationCap}>
                    <HorizontalBars {...(charts.education || {})} compact emptyMessage="Belum ada data jenjang pendidikan pegawai aktif." />
                </ChartCard>
                <ChartCard id="position-chart-title" preview title="Statistik Kepegawaian" description="Jumlah pegawai aktif berdasarkan jabatan aktif saat ini." meta={positions.length ? `${number(positions.length)} jabatan` : null} className="simpeg-dashboard-summary-chart-card simpeg-dashboard-position-card lg:col-span-6" icon={BriefcaseBusiness}>
                    {positions.length ? (
                        <div className="simpeg-dashboard-position-scroll">
                            <PositionBars positions={positions} />
                        </div>
                    ) : <EmptyData message="Belum ada statistik jabatan pegawai aktif." />}
                </ChartCard>
            </section>

            <RetirementChart data={retirementData} unit={unit} />

            <section aria-label="Ketersediaan jabatan">
                <div className="mb-4 grid min-w-0 gap-4 xl:grid-cols-2">
                    <PositionSummaryCard title="Jabatan Eksekutif" rows={executivePositions} />
                    <PositionSummaryCard title="Jabatan Struktural" rows={structuralPositions} />
                </div>
                <div className="grid min-w-0 gap-6 xl:grid-cols-2">
                    <PositionAvailabilityTable title="Jabatan Eksekutif" description="Ketersediaan posisi dan kuota jabatan kelompok PE." rows={executivePositions} searchId="executive-positions-title" />
                    <PositionAvailabilityTable title="Jabatan Struktural" description="Ketersediaan posisi dan kuota jabatan kelompok PS." rows={structuralPositions} searchId="structural-positions-title" />
                </div>
            </section>
        </div>
    );
}
