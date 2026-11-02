import { useMemo, useState } from 'react';
import { Check, ChevronDown, Search } from 'lucide-react';

export default function SearchableCombobox({
    id,
    name,
    label,
    value = '',
    options = [],
    onChange,
    optionValue = (option) => option.value ?? option,
    optionLabel = (option) => option.label ?? option,
    optionDescription,
    placeholder = 'Cari pilihan',
    emptyMessage = 'Pilihan tidak ditemukan.',
    required = false,
    disabled = false,
    error,
    description,
    className = '',
    searchable = true,
    maxOptions = 50,
}) {
    const [internalValue, setInternalValue] = useState(String(value ?? ''));
    const [query, setQuery] = useState('');
    const [isOpen, setIsOpen] = useState(false);
    const [activeIndex, setActiveIndex] = useState(0);
    const isControlled = onChange !== undefined;
    const selectedValue = String(isControlled ? value ?? '' : internalValue);
    const selected = options.find((option) => String(optionValue(option)) === selectedValue);
    const filteredOptions = useMemo(() => options.filter((option) => {
        const text = `${optionLabel(option)} ${optionValue(option)} ${optionDescription ? optionDescription(option) : ''}`.toLowerCase();
        return text.includes(query.trim().toLowerCase());
    }).slice(0, maxOptions), [options, optionLabel, optionValue, query, maxOptions]);

    function setSelectedValue(nextValue) {
        if (!isControlled) {
            setInternalValue(nextValue);
        }
        onChange?.(nextValue);
    }

    function openList() {
        setQuery('');
        setIsOpen(true);
        setActiveIndex(0);
    }

    function chooseOption(option) {
        const nextValue = String(optionValue(option));
        setSelectedValue(nextValue);
        setQuery(String(optionLabel(option)));
        setIsOpen(false);
        setActiveIndex(0);
    }

    function handleChange(event) {
        const nextQuery = event.target.value;
        setQuery(nextQuery);
        setIsOpen(true);
        setActiveIndex(0);
    }

    function handleKeyDown(event) {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setIsOpen(true);
            setActiveIndex((index) => Math.min(index + 1, Math.max(filteredOptions.length - 1, 0)));
        }

        if (event.key === 'ArrowUp') {
            event.preventDefault();
            setActiveIndex((index) => Math.max(index - 1, 0));
        }

        if (event.key === 'Enter' && isOpen && filteredOptions[activeIndex]) {
            event.preventDefault();
            chooseOption(filteredOptions[activeIndex]);
        }

        if (event.key === 'Escape') {
            setIsOpen(false);
            setQuery(selected ? String(optionLabel(selected)) : '');
        }
    }

    return (
        <div className={className}>
            {label && <label htmlFor={id} className="simpeg-muted mb-2 block text-xs font-bold uppercase tracking-wide">{label}{required && <span className="text-red-600"> *</span>}</label>}
            <div className="relative">
                {searchable ? <>
                    <Search aria-hidden="true" className="simpeg-subtle pointer-events-none absolute left-3 top-1/2 z-10 h-4 w-4 -translate-y-1/2" />
                    <input
                        id={id}
                        role="combobox"
                        aria-autocomplete="list"
                        aria-controls={`${id}-options`}
                        aria-expanded={isOpen}
                        aria-invalid={Boolean(error)}
                        autoComplete="off"
                        disabled={disabled}
                        required={required}
                        value={isOpen ? query : selected ? String(optionLabel(selected)) : ''}
                        onFocus={openList}
                        onChange={handleChange}
                        onKeyDown={handleKeyDown}
                        onBlur={() => window.setTimeout(() => setIsOpen(false), 150)}
                        placeholder={placeholder}
                        className="simpeg-input h-11 w-full rounded-xl border pl-10 pr-10 text-sm outline-none transition focus:border-[#F5CE59] focus:ring-4 focus:ring-[#F5CE59]/20 disabled:cursor-not-allowed disabled:opacity-60"
                    />
                    <button type="button" aria-label={`Buka daftar ${label || 'pilihan'}`} disabled={disabled} onMouseDown={(event) => event.preventDefault()} onClick={() => (isOpen ? setIsOpen(false) : openList())} className="simpeg-utility absolute right-1 top-1/2 inline-flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-lg border-0 shadow-none focus:outline-none focus:ring-2 focus:ring-[#F5CE59]/30 disabled:cursor-not-allowed disabled:opacity-50">
                        <ChevronDown aria-hidden="true" className={`h-4 w-4 transition-transform ${isOpen ? 'rotate-180' : ''}`} />
                    </button>
                </> : <button
                    id={id}
                    type="button"
                    role="combobox"
                    aria-controls={`${id}-options`}
                    aria-expanded={isOpen}
                    aria-invalid={Boolean(error)}
                    disabled={disabled}
                    onClick={() => (isOpen ? setIsOpen(false) : openList())}
                    onKeyDown={(event) => {
                        if (event.key === 'Enter' || event.key === ' ') {
                            event.preventDefault();
                            setIsOpen((open) => !open);
                        }
                        if (event.key === 'Escape') {
                            setIsOpen(false);
                        }
                    }}
                    onBlur={() => window.setTimeout(() => setIsOpen(false), 150)}
                    className="simpeg-input flex h-11 w-full items-center justify-between rounded-xl border px-3 text-left text-sm outline-none transition focus:border-[#F5CE59] focus:ring-4 focus:ring-[#F5CE59]/20 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    <span className={`truncate ${selected ? 'simpeg-text' : 'simpeg-subtle'}`}>{selected ? String(optionLabel(selected)) : placeholder}</span>
                    <ChevronDown aria-hidden="true" className={`simpeg-subtle h-4 w-4 shrink-0 transition-transform ${isOpen ? 'rotate-180' : ''}`} />
                </button>}
                {isOpen && <div id={`${id}-options`} role="listbox" className="simpeg-combobox-menu absolute z-30 mt-2 max-h-64 w-full overflow-y-auto rounded-xl border p-1 shadow-xl" onMouseDown={(event) => event.preventDefault()}>
                    {filteredOptions.length > 0 ? filteredOptions.map((option, index) => {
                        const optionId = String(optionValue(option));
                        const optionName = String(optionLabel(option));

                        return <button key={optionId} type="button" role="option" aria-selected={optionId === selectedValue} onClick={() => chooseOption(option)} className={`simpeg-combobox-option flex w-full items-start gap-3 rounded-lg px-3 py-2.5 text-left text-sm transition ${index === activeIndex ? 'simpeg-combobox-option-active' : ''}`}>
                            <span className="min-w-0 flex-1"><span className="simpeg-text block truncate font-semibold">{optionName}</span>{optionDescription && <span className="simpeg-muted mt-0.5 block truncate text-xs">{optionDescription(option)}</span>}</span>
                            {optionId === selectedValue && <Check aria-hidden="true" className="simpeg-text mt-0.5 h-4 w-4 shrink-0" />}
                        </button>;
                    }) : <p className="simpeg-muted px-3 py-3 text-sm">{emptyMessage}</p>}
                </div>}
            </div>
            {name && <input type="hidden" name={name} value={selectedValue} disabled={disabled} />}
            {description && <p className="mt-1 text-xs text-slate-500">{description}</p>}
            {error && <p className="mt-1 text-xs text-red-600" role="alert">{error}</p>}
        </div>
    );
}

export function EmployeeCombobox({ employees = [], value = '', onChange, disabled = false, error, required = true, label = 'Pegawai', className = '', id = 'id_peg', placeholder = 'Cari nama, NIK, atau ID pegawai' }) {
    return <SearchableCombobox
        id={id}
        name={id}
        label={label}
        value={value}
        options={employees}
        optionValue={(employee) => employee.id_peg}
        optionLabel={(employee) => employee.nama || 'Tanpa nama'}
        optionDescription={(employee) => `${employee.nik ? `NIK: ${employee.nik} · ` : ''}ID: ${employee.id_peg}`}
        placeholder={placeholder}
        emptyMessage="Pegawai tidak ditemukan."
        required={required}
        onChange={onChange}
        disabled={disabled}
        error={error}
        className={className}
    />;
}
