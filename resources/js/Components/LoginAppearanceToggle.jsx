import { Check, LayoutTemplate } from 'lucide-react';
import { useEffect, useState } from 'react';

export const LOGIN_APPEARANCE_STORAGE_KEY = 'simpeg-login-appearance';

export const LOGIN_APPEARANCE_OPTIONS = [
    {
        value: 'split',
        label: 'Panel informasi',
        description: 'Form login dan informasi akses tampil berdampingan.',
    },
    {
        value: 'focus',
        label: 'Fokus login',
        description: 'Form login terpusat seperti tampilan sebelumnya.',
    },
];

const LOGIN_APPEARANCE_VALUES = new Set(LOGIN_APPEARANCE_OPTIONS.map((option) => option.value));

export function resolveLoginAppearance() {
    if (typeof window === 'undefined') {
        return 'split';
    }

    const stored = window.localStorage.getItem(LOGIN_APPEARANCE_STORAGE_KEY);

    return LOGIN_APPEARANCE_VALUES.has(stored) ? stored : 'split';
}

export default function LoginAppearanceToggle({ appearance, onChange, className = '' }) {
    const [menuOpen, setMenuOpen] = useState(false);
    const activeOption = LOGIN_APPEARANCE_OPTIONS.find((option) => option.value === appearance) || LOGIN_APPEARANCE_OPTIONS[0];
    const label = `Pilih gaya login. Gaya aktif: ${activeOption.label}`;

    useEffect(() => {
        if (!menuOpen) {
            return undefined;
        }

        function closeOnOutsideClick(event) {
            if (!(event.target instanceof Element) || !event.target.closest('[data-login-appearance-picker]')) {
                setMenuOpen(false);
            }
        }

        function closeOnEscape(event) {
            if (event.key === 'Escape') {
                setMenuOpen(false);
            }
        }

        document.addEventListener('pointerdown', closeOnOutsideClick);
        document.addEventListener('keydown', closeOnEscape);

        return () => {
            document.removeEventListener('pointerdown', closeOnOutsideClick);
            document.removeEventListener('keydown', closeOnEscape);
        };
    }, [menuOpen]);

    function selectAppearance(nextAppearance) {
        if (!LOGIN_APPEARANCE_VALUES.has(nextAppearance)) {
            return;
        }

        window.localStorage.setItem(LOGIN_APPEARANCE_STORAGE_KEY, nextAppearance);
        onChange?.(nextAppearance);
        setMenuOpen(false);
    }

    return (
        <div className="relative" data-login-appearance-picker>
            <button
                type="button"
                onClick={() => setMenuOpen((open) => !open)}
                className={`simpeg-login-appearance-toggle simpeg-theme-toggle inline-flex min-h-11 min-w-11 items-center justify-center rounded-xl border transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--simpeg-hover-border)] ${className}`}
                aria-label={label}
                title={label}
                aria-expanded={menuOpen}
                aria-haspopup="menu"
            >
                <LayoutTemplate className="h-5 w-5" strokeWidth={1.8} aria-hidden="true" />
            </button>

            {menuOpen && (
                <div className="simpeg-login-appearance-menu simpeg-theme-menu absolute right-0 top-[calc(100%+0.5rem)] z-50 w-64 rounded-2xl border p-2 shadow-2xl" role="menu" aria-label="Pilih gaya halaman login">
                    <p className="px-3 pb-2 pt-1 text-xs font-bold uppercase tracking-[0.14em] text-[var(--simpeg-subtle)]">Gaya halaman login</p>
                    {LOGIN_APPEARANCE_OPTIONS.map((option) => {
                        const active = option.value === activeOption.value;

                        return (
                            <button
                                key={option.value}
                                type="button"
                                role="menuitemradio"
                                aria-checked={active}
                                onClick={() => selectAppearance(option.value)}
                                className={`simpeg-login-appearance-option flex w-full items-start gap-3 rounded-xl px-3 py-2.5 text-left transition ${active ? 'simpeg-login-appearance-option-active' : ''}`}
                            >
                                <span className="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-md border" aria-hidden="true">
                                    {active && <Check className="h-3.5 w-3.5" strokeWidth={2.2} />}
                                </span>
                                <span className="min-w-0 flex-1">
                                    <span className="block text-sm font-semibold">{option.label}</span>
                                    <span className="mt-0.5 block text-xs font-normal opacity-75">{option.description}</span>
                                </span>
                            </button>
                        );
                    })}
                </div>
            )}
        </div>
    );
}
