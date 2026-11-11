import { Moon, Palette, Sun } from 'lucide-react';
import { useEffect, useState } from 'react';

export const THEME_STORAGE_KEY = 'simpeg-theme';

export const THEME_OPTIONS = [
    { value: 'light', label: 'Terang', swatches: ['#ffffff', '#d7dde5'] },
    { value: 'dark', label: 'Gelap', swatches: ['#111827', '#6366f1'] },
    { value: 'corporate', label: 'Korporat', swatches: ['#ffffff', '#4f46e5'] },
];

const THEME_VALUES = new Set(THEME_OPTIONS.map((option) => option.value));

export function resolveTheme() {
    if (typeof window === 'undefined') {
        return 'light';
    }

    const stored = window.localStorage.getItem(THEME_STORAGE_KEY);

    if (THEME_VALUES.has(stored)) {
        return stored;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

export function applyTheme(theme) {
    if (typeof document === 'undefined') {
        return;
    }

    const nextTheme = THEME_VALUES.has(theme) ? theme : 'light';

    document.documentElement.classList.toggle('dark', nextTheme === 'dark');
    document.documentElement.dataset.theme = nextTheme;
    document.documentElement.style.colorScheme = nextTheme === 'dark' ? 'dark' : 'light';
}

export default function ThemeToggle({ className = '' }) {
    const [theme, setTheme] = useState(() => resolveTheme());
    const [menuOpen, setMenuOpen] = useState(false);

    useEffect(() => {
        const initialTheme = resolveTheme();
        setTheme(initialTheme);
        applyTheme(initialTheme);
    }, []);

    useEffect(() => {
        if (!menuOpen) {
            return undefined;
        }

        function closeOnOutsideClick(event) {
            if (!(event.target instanceof Element) || !event.target.closest('[data-theme-picker]')) {
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

    function selectTheme(nextTheme) {
        setTheme(nextTheme);
        window.localStorage.setItem(THEME_STORAGE_KEY, nextTheme);
        applyTheme(nextTheme);
        setMenuOpen(false);
    }

    const activeOption = THEME_OPTIONS.find((option) => option.value === theme) || THEME_OPTIONS[0];
    const label = `Pilih tema. Tema aktif: ${activeOption.label}`;

    return (
        <div className="relative" data-theme-picker>
            <button
                type="button"
                onClick={() => setMenuOpen((open) => !open)}
                className={`simpeg-theme-toggle inline-flex min-h-11 min-w-11 items-center justify-center rounded-xl border transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--simpeg-hover-border)] ${className}`}
                aria-label={label}
                title={label}
                aria-expanded={menuOpen}
                aria-haspopup="menu"
            >
                {theme === 'dark' ? <Sun className="h-5 w-5" strokeWidth={1.8} aria-hidden="true" /> : theme === 'corporate' ? <Palette className="h-5 w-5" strokeWidth={1.8} aria-hidden="true" /> : <Moon className="h-5 w-5" strokeWidth={1.8} aria-hidden="true" />}
            </button>

            {menuOpen && <div className="simpeg-theme-menu absolute right-0 top-[calc(100%+0.5rem)] z-50 w-52 rounded-2xl border p-2 shadow-2xl" role="menu" aria-label="Pilih tema aplikasi">
                <p className="px-3 pb-2 pt-1 text-xs font-bold uppercase tracking-[0.14em] text-[var(--simpeg-subtle)]">Tema tampilan</p>
                {THEME_OPTIONS.map((option) => {
                    const active = option.value === theme;

                    return <button
                        key={option.value}
                        type="button"
                        role="menuitemradio"
                        aria-checked={active}
                        onClick={() => selectTheme(option.value)}
                        className={`simpeg-theme-option flex min-h-11 w-full items-center gap-3 rounded-xl px-3 text-left text-sm font-semibold transition ${active ? 'simpeg-theme-option-active' : ''}`}
                    >
                        <span className="flex h-4 w-4 shrink-0 overflow-hidden rounded-full border border-black/10" aria-hidden="true">
                            <span className="h-full w-1/2" style={{ backgroundColor: option.swatches[0] }} />
                            <span className="h-full w-1/2" style={{ backgroundColor: option.swatches[1] }} />
                        </span>
                        <span className="flex-1">{option.label}</span>
                        {active && <span className="text-xs" aria-hidden="true">✓</span>}
                    </button>;
                })}
            </div>}
        </div>
    );
}
