import { cn } from '../../lib/utils';

const variants = {
    default: 'border-[var(--border)] bg-[var(--muted)] text-[var(--foreground)]',
    success: 'border-emerald-200/50 bg-emerald-100/80 text-emerald-900 dark:border-emerald-300/30 dark:bg-emerald-900/45 dark:text-emerald-200',
    warning: 'border-amber-200/50 bg-amber-100/80 text-amber-900 dark:border-amber-300/30 dark:bg-amber-900/45 dark:text-amber-200',
    danger: 'border-rose-200/50 bg-rose-100/80 text-rose-900 dark:border-rose-300/30 dark:bg-rose-900/45 dark:text-rose-200',
    info: 'border-sky-200/50 bg-sky-100/80 text-sky-900 dark:border-sky-300/30 dark:bg-sky-900/45 dark:text-sky-200',
};

export function Badge({ className, variant = 'default', ...props }) {
    return <span data-slot="badge" className={cn('inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-medium backdrop-blur-sm', variants[variant] || variants.default, className)} {...props} />;
}
