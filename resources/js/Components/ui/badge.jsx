import { cn } from '../../lib/utils';

const variants = {
    default: 'border-[var(--border)] bg-[var(--card)] text-[var(--muted-foreground)]',
    success: 'border-emerald-500/70 bg-emerald-50 text-emerald-700 dark:border-emerald-400/70 dark:bg-emerald-950/40 dark:text-emerald-300',
    warning: 'border-amber-500/70 bg-amber-50 text-amber-700 dark:border-amber-400/70 dark:bg-amber-950/40 dark:text-amber-300',
    danger: 'border-rose-500/70 bg-rose-50 text-rose-700 dark:border-rose-400/70 dark:bg-rose-950/40 dark:text-rose-300',
    info: 'border-sky-500/70 bg-sky-50 text-sky-700 dark:border-sky-400/70 dark:bg-sky-950/40 dark:text-sky-300',
};

export function Badge({ className, variant = 'default', ...props }) {
    return <span data-slot="badge" className={cn('inline-flex items-center rounded-full border px-2.5 py-1 text-[0.72rem] font-medium leading-none', variants[variant] || variants.default, className)} {...props} />;
}
