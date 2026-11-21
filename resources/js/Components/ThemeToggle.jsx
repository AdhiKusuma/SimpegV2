import { Moon, Sun } from 'lucide-react';
import { useEffect, useState } from 'react';

export const THEME_STORAGE_KEY = 'simpeg-theme';
export const THEME_PREFERENCE_KEY = 'simpeg-theme-explicit-choice-v1';
export const THEME_MODE_STORAGE_KEY = 'simpeg-theme-mode';
export const DEFAULT_THEME = 'light';

export const THEME_OPTIONS = [
    { value: 'light', label: 'Terang', swatches: ['#F5F8FE', '#0D5FE0'] },
    { value: 'dark', label: 'Gelap', swatches: ['#1B2A4A', '#0D5FE0'] },
];

const THEME_VALUES = new Set(THEME_OPTIONS.map((option) => option.value));

function resolveSystemTheme() {
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

function configuredTheme(theme) {
    return theme === 'system' ? resolveSystemTheme() : THEME_VALUES.has(theme) ? theme : DEFAULT_THEME;
}

export function resolveTheme({ fallbackTheme = DEFAULT_THEME, allowUserTheme = true } = {}) {
    if (typeof window === 'undefined') {
        return configuredTheme(fallbackTheme);
    }

    if (!allowUserTheme) {
        return configuredTheme(fallbackTheme);
    }

    const storedMode = window.localStorage.getItem(THEME_MODE_STORAGE_KEY);
    const stored = window.localStorage.getItem(THEME_STORAGE_KEY);
    const hasUserChoice = window.localStorage.getItem(THEME_PREFERENCE_KEY) === '1';

    if (storedMode === 'system') {
        return resolveSystemTheme();
    }

    if (THEME_VALUES.has(stored) && hasUserChoice) {
        return stored;
    }

    // Remove the retired theme from existing browser sessions.
    if (stored === 'corporate') {
        window.localStorage.removeItem(THEME_STORAGE_KEY);
    }

    return configuredTheme(fallbackTheme);
}

export function applyTheme(theme) {
    if (typeof document === 'undefined') {
        return;
    }

    const nextTheme = THEME_VALUES.has(theme) ? theme : DEFAULT_THEME;

    document.documentElement.classList.toggle('dark', nextTheme === 'dark');
    document.documentElement.style.colorScheme = nextTheme === 'dark' ? 'dark' : 'light';
}

export function applyAppearancePreferences(config = {}) {
    if (typeof document === 'undefined') {
        return;
    }

    document.documentElement.toggleAttribute('data-simpeg-reduce-motion', Boolean(config.reduceMotion));
    if (config.reduceMotion) {
        document.documentElement.dataset.simpegReduceMotion = '1';
    } else {
        delete document.documentElement.dataset.simpegReduceMotion;
    }
}

export function applyConfiguredTheme(config = {}, { isLogin = false } = {}) {
    const loginThemeDisabled = isLogin && config.applyLoginTheme === false;
    const allowUserTheme = !loginThemeDisabled && config.allowUserTheme !== false;
    const fallbackTheme = loginThemeDisabled ? DEFAULT_THEME : config.defaultTheme || DEFAULT_THEME;
    const theme = resolveTheme({ fallbackTheme, allowUserTheme });

    applyTheme(theme);
    applyAppearancePreferences(config);

    return theme;
}

export default function ThemeToggle({ className = '', defaultTheme = DEFAULT_THEME, allowUserTheme = true }) {
    const [theme, setTheme] = useState(() => resolveTheme({ fallbackTheme: defaultTheme, allowUserTheme }));

    useEffect(() => {
        const initialTheme = resolveTheme({ fallbackTheme: defaultTheme, allowUserTheme });
        setTheme(initialTheme);
        applyTheme(initialTheme);

        const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
        const handleSystemThemeChange = () => {
            if (window.localStorage.getItem(THEME_MODE_STORAGE_KEY) !== 'system') {
                return;
            }

            const nextTheme = resolveSystemTheme();
            setTheme(nextTheme);
            applyTheme(nextTheme);
        };

        mediaQuery.addEventListener?.('change', handleSystemThemeChange);

        return () => mediaQuery.removeEventListener?.('change', handleSystemThemeChange);
    }, [allowUserTheme, defaultTheme]);

    if (!allowUserTheme) {
        return null;
    }

    function toggleTheme() {
        const nextTheme = theme === 'dark' ? 'light' : 'dark';
        setTheme(nextTheme);
        window.localStorage.setItem(THEME_STORAGE_KEY, nextTheme);
        window.localStorage.setItem(THEME_PREFERENCE_KEY, '1');
        window.localStorage.setItem(THEME_MODE_STORAGE_KEY, 'explicit');
        applyTheme(nextTheme);
    }

    const activeOption = THEME_OPTIONS.find((option) => option.value === theme) || THEME_OPTIONS[0];
    const nextOption = THEME_OPTIONS.find((option) => option.value !== activeOption.value) || THEME_OPTIONS[0];
    const label = `Ganti ke mode ${nextOption.label}. Mode aktif: ${activeOption.label}`;

    return (
        <button
            type="button"
            onClick={toggleTheme}
            className={`simpeg-theme-toggle inline-flex min-h-11 min-w-11 items-center justify-center rounded-xl border transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--simpeg-hover-border)] ${className}`}
            aria-label={label}
            title={theme === 'dark' ? undefined : label}
            data-tooltip={label}
        >
            {theme === 'dark' ? <Moon className="h-5 w-5" strokeWidth={1.8} aria-hidden="true" /> : <Sun className="h-5 w-5" strokeWidth={1.8} aria-hidden="true" />}
        </button>
    );
}
