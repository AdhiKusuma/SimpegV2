import * as React from 'react';
import * as RechartsPrimitive from 'recharts';
import { cn } from '../../lib/utils';

const THEMES = { light: '', dark: '.dark' };
const INITIAL_DIMENSION = { width: 320, height: 200 };

const ChartContext = React.createContext(null);

function useChart() {
    const context = React.useContext(ChartContext);

    if (!context) {
        throw new Error('useChart must be used within a ChartContainer.');
    }

    return context;
}

function ChartContainer({ id, className, children, config, initialDimension = INITIAL_DIMENSION, ...props }) {
    const uniqueId = React.useId();
    const chartId = `chart-${id || uniqueId.replace(/:/g, '')}`;

    return (
        <ChartContext.Provider value={{ config }}>
            <div
                data-slot="chart"
                data-chart={chartId}
                className={cn('simpeg-chart-container flex justify-center text-xs', className)}
                {...props}
            >
                <ChartStyle id={chartId} config={config} />
                <RechartsPrimitive.ResponsiveContainer initialDimension={initialDimension}>
                    {children}
                </RechartsPrimitive.ResponsiveContainer>
            </div>
        </ChartContext.Provider>
    );
}

function ChartStyle({ id, config }) {
    const colorConfig = Object.entries(config || {}).filter(([, itemConfig]) => itemConfig?.theme || itemConfig?.color);

    if (!colorConfig.length) {
        return null;
    }

    return (
        <style
            dangerouslySetInnerHTML={{
                __html: Object.entries(THEMES)
                    .map(([theme, prefix]) => `${prefix} [data-chart=${id}] {\n${colorConfig
                        .map(([key, itemConfig]) => {
                            const color = itemConfig.theme?.[theme] || itemConfig.color;
                            return color ? `  --color-${key}: ${color};` : null;
                        })
                        .filter(Boolean)
                        .join('\n')}\n}`)
                    .join('\n'),
            }}
        />
    );
}

const ChartTooltip = RechartsPrimitive.Tooltip;

function ChartTooltipContent({
    active,
    payload,
    className,
    indicator = 'dot',
    hideLabel = false,
    hideIndicator = false,
    label,
    labelFormatter,
    labelClassName,
    formatter,
    color,
    nameKey,
    labelKey,
}) {
    const { config } = useChart();
    const tooltipLabel = React.useMemo(() => {
        if (hideLabel || !payload?.length) {
            return null;
        }

        const [item] = payload;
        const key = `${labelKey || item?.dataKey || item?.name || 'value'}`;
        const itemConfig = getPayloadConfigFromPayload(config, item, key);
        const value = !labelKey && typeof label === 'string' ? (config?.[label]?.label || label) : itemConfig?.label;

        if (!value) {
            return null;
        }

        return labelFormatter ? (
            <div className={cn('font-medium', labelClassName)}>{labelFormatter(value, payload)}</div>
        ) : (
            <div className={cn('font-medium', labelClassName)}>{value}</div>
        );
    }, [config, hideLabel, label, labelFormatter, labelClassName, labelKey, payload]);

    if (!active || !payload?.length) {
        return null;
    }

    const nestLabel = payload.length === 1 && indicator !== 'dot';

    return (
        <div className={cn('simpeg-chart-tooltip grid min-w-32 items-start gap-1.5 rounded-lg border px-2.5 py-1.5 text-xs shadow-lg', className)}>
            {!nestLabel ? tooltipLabel : null}
            <div className="grid gap-1.5">
                {payload
                    .filter((item) => item.type !== 'none')
                    .map((item, index) => {
                        const key = `${nameKey || item.name || item.dataKey || 'value'}`;
                        const itemConfig = getPayloadConfigFromPayload(config, item, key);
                        const indicatorColor = color || item.payload?.fill || item.color;

                        return (
                            <div key={index} className={cn('flex w-full flex-wrap items-center gap-2', indicator === 'dot' && 'items-center')}>
                                {formatter && item?.value !== undefined ? (
                                    formatter(item.value, item.name, item, index, item.payload)
                                ) : (
                                    <>
                                        {!hideIndicator && (
                                            <span
                                                className={cn(
                                                    'shrink-0 rounded-[2px]',
                                                    indicator === 'dot' && 'h-2.5 w-2.5',
                                                    indicator === 'line' && 'h-4 w-1',
                                                    indicator === 'dashed' && 'h-0 w-3 border-t border-dashed',
                                                )}
                                                style={{ backgroundColor: indicator === 'dashed' ? 'transparent' : indicatorColor, borderColor: indicatorColor }}
                                            />
                                        )}
                                        <div className={cn('flex flex-1 justify-between gap-4 leading-none', nestLabel ? 'items-end' : 'items-center')}>
                                            <div className="grid gap-1.5">
                                                {nestLabel ? tooltipLabel : null}
                                                <span className="simpeg-muted">{itemConfig?.label || item.name}</span>
                                            </div>
                                            {item.value != null && <span className="simpeg-text font-medium tabular-nums">{String(item.value)}</span>}
                                        </div>
                                    </>
                                )}
                            </div>
                        );
                    })}
            </div>
        </div>
    );
}

function getPayloadConfigFromPayload(config, payload, key) {
    if (!payload || typeof payload !== 'object') {
        return undefined;
    }

    const payloadData = payload.payload && typeof payload.payload === 'object' ? payload.payload : undefined;
    const configLabelKey = typeof payload[key] === 'string' ? payload[key] : payloadData && typeof payloadData[key] === 'string' ? payloadData[key] : key;

    return config?.[configLabelKey] || config?.[key];
}

export { ChartContainer, ChartTooltip, ChartTooltipContent, ChartStyle };
