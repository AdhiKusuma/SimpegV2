function cleanAmount(value) {
    return String(value ?? '')
        .replace(/\bRp\.?\s*/gi, '')
        .replace(/[\s\u00a0]/g, '')
        .replace(/[^\d.,]/g, '');
}

export function normalizePayrollAmount(value) {
    const cleaned = cleanAmount(value);
    if (!cleaned) return '';

    const lastSeparator = Math.max(cleaned.lastIndexOf(','), cleaned.lastIndexOf('.'));
    const fractionLength = lastSeparator < 0 ? 0 : cleaned.length - lastSeparator - 1;
    const hasDecimal = fractionLength > 0 && fractionLength <= 2;
    const wholePart = hasDecimal ? cleaned.slice(0, lastSeparator) : cleaned;
    const fractionPart = hasDecimal ? cleaned.slice(lastSeparator + 1).replace(/\D/g, '') : '';
    const whole = wholePart.replace(/\D/g, '').replace(/^0+(?=\d)/, '') || '0';

    return hasDecimal && fractionPart ? `${whole}.${fractionPart}` : whole;
}

export function formatPayrollAmountWhileTyping(value) {
    const cleaned = cleanAmount(value);
    if (!cleaned) return { display: '', canonical: '', decimalSeparator: null };

    const commaIndex = cleaned.lastIndexOf(',');
    const dots = [...cleaned.matchAll(/\./g)].map((match) => match.index);
    let decimalIndex = commaIndex;
    let decimalSeparator = commaIndex >= 0 ? ',' : null;

    if (commaIndex < 0 && dots.length === 1) {
        const fractionLength = cleaned.length - dots[0] - 1;
        if (fractionLength > 0 && fractionLength <= 2) {
            decimalIndex = dots[0];
            decimalSeparator = '.';
        }
    }

    const wholeSource = decimalIndex >= 0 ? cleaned.slice(0, decimalIndex) : cleaned;
    const fractionSource = decimalIndex >= 0 ? cleaned.slice(decimalIndex + 1) : '';
    const whole = wholeSource.replace(/\D/g, '').replace(/^0+(?=\d)/, '') || '0';
    const fraction = fractionSource.replace(/\D/g, '').slice(0, 2);
    const groupedWhole = whole.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    const hasDecimal = decimalIndex >= 0;
    const display = `${groupedWhole}${hasDecimal ? `,${fraction}` : ''}`;
    const canonical = fraction ? `${whole}.${fraction}` : whole;

    return { display, canonical, decimalSeparator: hasDecimal ? decimalSeparator : null };
}

export function formatGroupedPayrollAmount(value) {
    const normalized = normalizePayrollAmount(value);
    if (!normalized) return '';

    const [whole = '0', fraction] = normalized.split('.');
    const groupedWhole = whole.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    const readableFraction = fraction?.replace(/0+$/, '');

    return readableFraction ? `${groupedWhole},${readableFraction}` : groupedWhole;
}

export function formatRupiah(value) {
    if (value === null || value === undefined || value === '') return 'Rp 0';

    return `Rp ${formatGroupedPayrollAmount(value) || '0'}`;
}

