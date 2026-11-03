import { useEffect, useRef, useState } from 'react';
import { formatGroupedPayrollAmount, formatPayrollAmountWhileTyping, normalizePayrollAmount } from '../lib/payrollMoney';

function caretAfterDigits(value, digitCount) {
    if (digitCount <= 0) return 0;
    let seen = 0;
    for (let index = 0; index < value.length; index += 1) {
        if (/\d/.test(value[index])) seen += 1;
        if (seen === digitCount) return index + 1;
    }
    return value.length;
}

function caretAfterDecimal(value, decimalDigits) {
    const separatorIndex = value.indexOf(',');
    if (separatorIndex < 0) return value.length;
    if (decimalDigits <= 0) return separatorIndex + 1;
    return separatorIndex + 1 + Math.min(decimalDigits, value.slice(separatorIndex + 1).replace(/\D/g, '').length);
}

export default function PayrollAmountInput({ id, value, onChange, required = false, readOnly = false, error, descriptionId }) {
    const [focused, setFocused] = useState(false);
    const [draft, setDraft] = useState(formatGroupedPayrollAmount(value));
    const inputRef = useRef(null);

    useEffect(() => {
        if (!focused) setDraft(formatGroupedPayrollAmount(value));
    }, [focused, value]);

    function focus() {
        setFocused(true);
        setDraft(formatGroupedPayrollAmount(value));
    }

    function change(event) {
        const raw = event.target.value;
        const selectionStart = event.target.selectionStart ?? raw.length;
        const selectionEnd = event.target.selectionEnd ?? selectionStart;
        const parse = formatPayrollAmountWhileTyping(raw);
        const decimalIndex = parse.decimalSeparator ? raw.lastIndexOf(parse.decimalSeparator) : -1;
        const startDecimalDigits = decimalIndex >= 0 && selectionStart > decimalIndex
            ? raw.slice(decimalIndex + 1, selectionStart).replace(/\D/g, '').length
            : 0;
        const endDecimalDigits = decimalIndex >= 0 && selectionEnd > decimalIndex
            ? raw.slice(decimalIndex + 1, selectionEnd).replace(/\D/g, '').length
            : 0;
        const startDigits = raw.slice(0, selectionStart).replace(/\D/g, '').length;
        const endDigits = raw.slice(0, selectionEnd).replace(/\D/g, '').length;
        const startHadDecimal = decimalIndex >= 0 && selectionStart > decimalIndex;
        const endHadDecimal = decimalIndex >= 0 && selectionEnd > decimalIndex;

        setDraft(parse.display);
        onChange(parse.canonical);
        requestAnimationFrame(() => {
            if (!inputRef.current) return;
            const start = startHadDecimal
                ? caretAfterDecimal(parse.display, startDecimalDigits)
                : caretAfterDigits(parse.display, startDigits);
            const end = endHadDecimal
                ? caretAfterDecimal(parse.display, endDecimalDigits)
                : caretAfterDigits(parse.display, endDigits);
            inputRef.current.setSelectionRange(start, end);
        });
    }

    function blur() {
        const normalized = normalizePayrollAmount(draft);
        setDraft(formatGroupedPayrollAmount(normalized));
        onChange(normalized);
        setFocused(false);
    }

    return (
        <div className="simpeg-input flex h-11 min-w-0 items-center gap-2 rounded-xl border px-3 focus-within:ring-4 focus-within:ring-[var(--simpeg-focus-ring)]">
            <span aria-hidden="true" className="simpeg-muted shrink-0 text-sm font-semibold">Rp</span>
            <input
                ref={inputRef}
                id={id}
                type="text"
                inputMode="decimal"
                autoComplete="off"
                maxLength={32}
                required={required}
                readOnly={readOnly}
                value={focused ? draft : formatGroupedPayrollAmount(value)}
                onFocus={focus}
                onChange={change}
                onBlur={blur}
                aria-invalid={Boolean(error)}
                aria-describedby={[descriptionId, error ? `${id}-error` : null].filter(Boolean).join(' ') || undefined}
                className={`simpeg-text min-w-0 flex-1 bg-transparent text-sm outline-none${readOnly ? ' cursor-not-allowed' : ''}`}
                placeholder="0"
            />
        </div>
    );
}
