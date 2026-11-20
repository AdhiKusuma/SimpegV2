import { useId, useRef } from 'react';
import { Check, ChevronDown } from 'lucide-react';

export default function PageSizeSelect({ value, onChange, options = [10, 20, 25, 50], label = 'Jumlah data per halaman', visibleLabel = 'Tampilkan' }) {
    const sizes = [...new Set([...options, Number(value)].filter((size) => Number.isInteger(size) && size > 0))].sort((a, b) => a - b);
    const id = useId();
    const menuRef = useRef(null);
    return (
        <div className="simpeg-page-size">
            <span id={`${id}-label`} title={label}>{visibleLabel}</span>
            <details className="simpeg-page-size__control" ref={menuRef}>
                <summary aria-labelledby={`${id}-label`}>{value}<ChevronDown size={16} aria-hidden="true" /></summary>
                <div className="simpeg-page-size__options" role="group" aria-label={label}>
                    {sizes.map((size) => <button key={size} type="button" className={Number(value) === size ? 'is-selected' : ''} aria-pressed={Number(value) === size} onClick={() => { menuRef.current.open = false; onChange(size); }}>{size}{Number(value) === size && <Check size={16} aria-hidden="true" />}</button>)}
                </div>
            </details>
        </div>
    );
}
