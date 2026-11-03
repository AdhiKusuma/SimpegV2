import { useEffect, useRef, useState } from 'react';

const defaultFormatter = new Intl.NumberFormat('id-ID');

export function NumberTicker({ value = 0, duration = 650, className = '' }) {
    const target = Number(value) || 0;
    const [displayValue, setDisplayValue] = useState(target);
    const previousValue = useRef(target);

    useEffect(() => {
        const startValue = previousValue.current;
        const difference = target - startValue;
        previousValue.current = target;

        if (!difference || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            setDisplayValue(target);
            return undefined;
        }

        let frame;
        const startTime = performance.now();

        const tick = (time) => {
            const progress = Math.min((time - startTime) / duration, 1);
            const eased = 1 - ((1 - progress) ** 3);
            setDisplayValue(Math.round(startValue + difference * eased));

            if (progress < 1) {
                frame = window.requestAnimationFrame(tick);
            }
        };

        frame = window.requestAnimationFrame(tick);

        return () => window.cancelAnimationFrame(frame);
    }, [duration, target]);

    return <span className={className} aria-label={defaultFormatter.format(target)}>{defaultFormatter.format(displayValue)}</span>;
}

